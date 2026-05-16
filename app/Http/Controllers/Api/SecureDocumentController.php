<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecureDocument;
use App\Models\SecureDocumentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\SecureDocumentMail;

class SecureDocumentController extends Controller
{
    /* =========================
       CREATE (UPLOAD → DRAFT)
    ========================= */
    public function store(Request $request)
    {
        try {

            $validated = $request->validate([
                'employee_name' => 'required|string|max:255',
                'emails' => 'required|array|min:1',
                'emails.*' => 'email',
                'password' => 'required|string|min:6|confirmed',

                'pdfs' => 'required|array|min:1|max:50',
                'pdfs.*' => 'file|mimes:pdf|max:5120',
            ]);

            Storage::disk('public')->makeDirectory('secure_documents/original');

            // ✅ CREATE BATCH
            $batch = \App\Models\SecureDocumentBatch::create([
                'employee_name' => $validated['employee_name'],
                'password_encrypted' => Crypt::encryptString($validated['password']),
                'created_by' => Auth::id(),
            ]);

            $createdDocuments = [];

            foreach ($request->file('pdfs') as $file) {

                $fileName = Str::uuid() . '.pdf';

                $path = $file->storeAs(
                    'secure_documents/original',
                    $fileName,
                    'public'
                );

                // ✅ CREATE DOCUMENT PER FILE
                $document = new SecureDocument();
                $document->batch_id = $batch->id;
                $document->employee_name = $validated['employee_name'];
                $document->file_name = $file->getClientOriginalName();
                $document->file_path = $path;
                $document->created_by = Auth::id();
                $document->status = 'Draft';

                // reuse mutator
                $document->password = $validated['password'];

                $document->save();

                // ✅ CREATE RECIPIENTS
                foreach ($validated['emails'] as $email) {
                    \App\Models\SecureDocumentRecipient::create([
                        'document_id' => $document->id,
                        'email' => $email,
                        'status' => 'Pending',
                    ]);
                }

                // LOG PER FILE
                $this->logAction($document, 'Upload', 'Success', 'Document uploaded');

                $createdDocuments[] = $document;
            }

            return response()->json([
                'success' => true,
                'message' => 'Batch uploaded successfully',
                'batch_id' => $batch->id,
                'documents_count' => count($createdDocuments)
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);

        } catch (\Throwable $e) {

            Log::error('Upload Error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Upload failed',
                'debug' => $e->getMessage() // remove in production
            ], 500);
        }
    }

    /* =========================
       LIST
    ========================= */
    public function index(Request $request)
    {
        $query = SecureDocument::with(['recipients', 'batch']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('employee_name', 'like', '%' . $request->search . '%')
                    ->orWhere('file_name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->status && $request->status !== 'All') {
            $query->where('status', $request->status);
        }

        $documents = $query->latest()->paginate(10);

        $documents->getCollection()->transform(function ($doc) {
            $doc->recipient_count = $doc->recipients->count();
            return $doc;
        });

        return response()->json([
            'success' => true,
            'data' => $documents
        ]);
    }

    /* =========================
       BULK SEND
    ========================= */
    public function bulkSend(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:secure_documents,id',
        ]);

        $documents = SecureDocument::with('recipients')
            ->whereIn('id', $validated['ids'])
            ->whereIn('status', ['Draft', 'Failed'])
            ->get();

        foreach ($documents as $doc) {
            dispatch(function () use ($doc) {
                $this->processAndSend($doc);
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'Documents queued for sending'
        ]);
    }

    /* =========================
       SINGLE SEND
    ========================= */
    public function sendSingle($id)
    {
        $doc = SecureDocument::with('recipients')->findOrFail($id);

        if (!in_array($doc->status, ['Draft', 'Failed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Document cannot be sent'
            ], 400);
        }

        dispatch(function () use ($doc) {
            $this->processAndSend($doc);
        });

        return response()->json([
            'success' => true,
            'message' => 'Email queued'
        ]);
    }

    /* =========================
       PROCESS + ENCRYPT + EMAIL
    ========================= */
    private function processAndSend(SecureDocument $doc)
    {
        try {
            $doc->markAsQueued();

            // 🟡 PROCESSING LOG
            $this->logAction($doc, 'Send', 'Processing', 'Processing started');

            $originalPath = storage_path('app/public/' . $doc->file_path);

            if (!file_exists($originalPath)) {
                throw new \Exception('File not found');
            }

            $password = $this->decryptPassword($doc);

            if (!$password) {
                throw new \Exception('Invalid password');
            }

            $encryptedDir = storage_path('app/public/secure_documents/encrypted');

            if (!file_exists($encryptedDir)) {
                mkdir($encryptedDir, 0777, true);
            }

            $encryptedPath = $encryptedDir . '/' . Str::uuid() . '.pdf';

            $qpdf = '"C:\\Program Files\\qpdf 12.3.2\\bin\\qpdf.exe"';

            $command = sprintf(
                '%s --encrypt %s %s 256 -- "%s" "%s"',
                $qpdf,
                escapeshellarg($password),
                escapeshellarg($password),
                $originalPath,
                $encryptedPath
            );

            exec($command . ' 2>&1', $output, $resultCode);

            if ($resultCode !== 0 || !file_exists($encryptedPath)) {
                throw new \Exception('PDF encryption failed');
            }

            // 🔥 SEND PER RECIPIENT
            foreach ($doc->recipients as $recipient) {

                try {
                    Mail::to($recipient->email)
                        ->send(new SecureDocumentMail($encryptedPath, $password));

                    // ✅ update recipient
                    $recipient->markAsSent();

                    // ✅ log success
                    $this->logAction((object) [
                        'id' => $doc->id,
                        'email' => $recipient->email,
                        'employee_name' => $doc->employee_name,
                        'file_name' => $doc->file_name
                    ], 'Send', 'Success', 'Email sent successfully');

                } catch (\Throwable $e) {

                    // ❌ update failed
                    $recipient->markAsFailed($e->getMessage());

                    // ❌ log failed
                    $this->logAction((object) [
                        'id' => $doc->id,
                        'email' => $recipient->email,
                        'employee_name' => $doc->employee_name,
                        'file_name' => $doc->file_name
                    ], 'Send', 'Failed', $e->getMessage());
                }
            }

            // ✅ UPDATE DOCUMENT STATUS
            if ($doc->recipients()->where('status', 'Failed')->exists()) {
                $doc->status = 'Failed';
            } else {
                $doc->status = 'Sent';
            }

            $doc->save();

        } catch (\Throwable $e) {

            $doc->markAsFailed($e->getMessage());

            $this->logAction($doc, 'Send', 'Failed', $e->getMessage());

            Log::error('Process Error', [
                'doc_id' => $doc->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /* =========================
       RESEND
    ========================= */
    public function resend($id)
    {
        $doc = SecureDocument::findOrFail($id);

        try {
            $doc->update([
                'status' => 'Queued',
                'error_message' => null,
            ]);

            $doc->increment('resend_count');

            // 🟡 RESEND LOG
            $this->logAction($doc, 'Resend', 'Processing', 'Resend queued');

            dispatch(function () use ($doc) {
                app(SecureDocumentController::class)->processAndSend($doc);
            });

            return response()->json([
                'message' => 'Resend queued successfully'
            ]);

        } catch (\Exception $e) {

            $this->logAction($doc, 'Resend', 'Failed', $e->getMessage());

            return response()->json([
                'message' => 'Resend failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function history(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $logs = SecureDocumentLog::where('email', $request->email)
            ->whereIn('status', ['Success', 'Failed'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    /* =========================
       LOGGING CORE
    ========================= */
    protected function logAction($doc, $action, $status, $message = null)
    {
        SecureDocumentLog::create([
            'document_id' => $doc->id ?? null,
            'action' => $action,
            'status' => $status,
            'message' => $message,
            'email' => $doc->email ?? null,
            'employee_name' => $doc->employee_name ?? null,
            'file_name' => $doc->file_name ?? null, // ✅ ADD THIS
            'user_id' => auth()->id(),
        ]);
    }

    /* =========================
       PASSWORD DECRYPT
    ========================= */
    private function decryptPassword(SecureDocument $doc)
    {
        if (!$doc->password_encrypted) {
            return null;
        }

        return Crypt::decryptString($doc->password_encrypted);
    }
}






