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
        $query = SecureDocument::with([
            'recipients',
            'batch'
        ]);

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {

            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->where('employee_name', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%")
                    ->orWhereHas('recipients', function ($r) use ($search) {

                        $r->where('email', 'like', "%{$search}%");
                    });
            });
        }

        /*
        |--------------------------------------------------------------------------
        | STATUS FILTER
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled('status') &&
            $request->status !== 'All'
        ) {

            $query->where('status', $request->status);
        }

        /*
        |--------------------------------------------------------------------------
        | FETCH ALL
        |--------------------------------------------------------------------------
        */

        $documents = $query
            ->latest()
            ->get();

        /*
        |--------------------------------------------------------------------------
        | GROUP DOCUMENTS
        |--------------------------------------------------------------------------
        */

        $grouped = $documents->groupBy(function ($item) {

            $emails = $item->recipients
                ->pluck('email')
                ->sort()
                ->implode(',');

            return $item->employee_name . '-' . $emails;
        });

        /*
        |--------------------------------------------------------------------------
        | FORMAT GROUPS
        |--------------------------------------------------------------------------
        */

        $formatted = $grouped->map(function ($items) {

            $first = $items->first();

            return [
                'id' => $first->id,
                'employee_name' => $first->employee_name,
                'status' => $first->status,
                'created_at' => $first->created_at,

                'ids' => $items->pluck('id')->values(),

                'files' => $items->pluck('file_name')->values(),

                'allEmails' => $items
                    ->flatMap(function ($doc) {
                        return $doc->recipients->pluck('email');
                    })
                    ->unique()
                    ->values(),
            ];
        })->values();

        /*
        |--------------------------------------------------------------------------
        | MANUAL PAGINATION
        |--------------------------------------------------------------------------
        */

        $page = max((int) request()->get('page', 1), 1);

        $perPage = 10;

        $currentItems = $formatted
            ->slice(($page - 1) * $perPage, $perPage)
            ->values();

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentItems,
            $formatted->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $paginated,
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

        $documents = SecureDocument::with([
            'recipients',
            'batch'
        ])
            ->whereIn('id', $validated['ids'])
            ->whereIn('status', ['Draft', 'Failed'])
            ->get();

        dispatch(function () use ($documents) {

            app(SecureDocumentController::class)
                ->processBulkRecipientSend($documents);

        });

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
        $doc = SecureDocument::with([
            'recipients',
            'batch'
        ])->findOrFail($id);

        if (!in_array($doc->status, ['Draft', 'Failed'])) {

            return response()->json([
                'success' => false,
                'message' => 'Document cannot be sent'
            ], 400);
        }

        dispatch(function () use ($doc) {

            app(SecureDocumentController::class)
                ->processAndSend($doc);

        });

        return response()->json([
            'success' => true,
            'message' => 'Email queued'
        ]);
    }

    /* =========================
   GROUPED SEND
========================= */
    public function sendGrouped($id)
    {
        $doc = SecureDocument::with([
            'recipients',
            'batch'
        ])->findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | GET ALL DOCUMENTS FROM SAME BATCH
        |--------------------------------------------------------------------------
        */

        $documents = SecureDocument::with([
            'recipients',
            'batch'
        ])
            ->where('batch_id', $doc->batch_id)
            ->whereIn('status', ['Draft', 'Failed'])
            ->get();

        if ($documents->isEmpty()) {

            return response()->json([
                'success' => false,
                'message' => 'Documents cannot be sent'
            ], 400);
        }

        dispatch(function () use ($documents) {

            app(SecureDocumentController::class)
                ->processBulkRecipientSend($documents);

        });

        return response()->json([
            'success' => true,
            'message' => 'Grouped email queued'
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

            $qpdf = env('QPDF_PATH', 'qpdf');

            if (
                strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
            ) {

                $qpdf = '"' . $qpdf . '"';
            }

            $command = sprintf(
                '%s --encrypt %s %s 256 -- %s %s',
                $qpdf,
                escapeshellarg($password),
                escapeshellarg($password),
                escapeshellarg($originalPath),
                escapeshellarg($encryptedPath)
            );

            $output = [];
            $resultCode = null;

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

    private function processBulkRecipientSend($documents)
    {
        try {

            $recipientGroups = [];

            foreach ($documents as $doc) {

                $doc->loadMissing([
                    'recipients',
                    'batch'
                ]);

                $doc->markAsQueued();

                $this->logAction(
                    $doc,
                    'Send',
                    'Processing',
                    'Processing started'
                );

                $originalPath = storage_path(
                    'app/public/' . $doc->file_path
                );

                if (!file_exists($originalPath)) {

                    throw new \Exception(
                        "File not found: {$doc->file_name}"
                    );
                }

                $password = $this->decryptPassword($doc);

                if (!$password) {

                    throw new \Exception(
                        "Invalid password for {$doc->file_name}"
                    );
                }

                $encryptedDir = storage_path(
                    'app/public/secure_documents/encrypted'
                );

                if (!file_exists($encryptedDir)) {

                    mkdir($encryptedDir, 0777, true);
                }

                $encryptedPath =
                    $encryptedDir . '/' . Str::uuid() . '.pdf';

                $qpdf = env('QPDF_PATH', 'qpdf');

                if (
                    strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
                ) {

                    $qpdf = '"' . $qpdf . '"';
                }

                $command = sprintf(
                    '%s --encrypt %s %s 256 -- %s %s',
                    $qpdf,
                    escapeshellarg($password),
                    escapeshellarg($password),
                    escapeshellarg($originalPath),
                    escapeshellarg($encryptedPath)
                );

                exec($command . ' 2>&1', $output, $resultCode);

                clearstatcache();

                usleep(300000);

                if (
                    $resultCode !== 0 ||
                    !is_file($encryptedPath) ||
                    filesize($encryptedPath) === 0
                ) {

                    throw new \Exception(
                        "PDF encryption failed for {$doc->file_name}: " .
                        implode("\n", $output)
                    );
                }

                foreach ($doc->recipients as $recipient) {

                    $recipientGroups[$recipient->email][] = [
                        'path' => $encryptedPath,
                        'file_name' => $doc->file_name,
                        'document_id' => $doc->id,
                        'recipient_id' => $recipient->id,
                        'password' => $password,
                    ];
                }
            }

            /*
            |--------------------------------------------------------------------------
            | SEND ONE EMAIL PER RECIPIENT
            |--------------------------------------------------------------------------
            */

            foreach ($recipientGroups as $email => $files) {

                try {

                    $password = $files[0]['password'];

                    Mail::to($email)->send(
                        new \App\Mail\BulkSecureDocumentMail(
                            $files,
                            $password
                        )
                    );

                    foreach ($files as $file) {

                        $recipient =
                            \App\Models\SecureDocumentRecipient::find(
                                $file['recipient_id']
                            );

                        if ($recipient) {

                            $recipient->markAsSent();
                        }

                        $doc = SecureDocument::find(
                            $file['document_id']
                        );

                        if ($doc) {

                            $this->logAction(
                                (object) [
                                    'id' => $doc->id,
                                    'email' => $email,
                                    'employee_name' => $doc->employee_name,
                                    'file_name' => $doc->file_name,
                                ],
                                'Send',
                                'Success',
                                'Bulk email sent successfully'
                            );
                        }
                    }

                } catch (\Throwable $e) {

                    foreach ($files as $file) {

                        $recipient =
                            \App\Models\SecureDocumentRecipient::find(
                                $file['recipient_id']
                            );

                        if ($recipient) {

                            $recipient->markAsFailed(
                                $e->getMessage()
                            );
                        }

                        $doc = SecureDocument::find(
                            $file['document_id']
                        );

                        if ($doc) {

                            $doc->markAsFailed(
                                $e->getMessage()
                            );

                            $this->logAction(
                                (object) [
                                    'id' => $doc->id,
                                    'email' => $email,
                                    'employee_name' => $doc->employee_name,
                                    'file_name' => $doc->file_name,
                                ],
                                'Send',
                                'Failed',
                                $e->getMessage()
                            );
                        }
                    }

                    Log::error('Bulk Email Send Error', [
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE FINAL STATUS
            |--------------------------------------------------------------------------
            */

            foreach ($documents as $doc) {

                $hasFailed =
                    $doc->recipients()
                        ->where('status', 'Failed')
                        ->exists();

                $allSent =
                    $doc->recipients()
                        ->where('status', '!=', 'Sent')
                        ->doesntExist();

                if ($hasFailed) {

                    $doc->status = 'Failed';

                } elseif ($allSent) {

                    $doc->status = 'Sent';

                } else {

                    $doc->status = 'Processing';
                }

                $doc->save();
            }

        } catch (\Throwable $e) {

            Log::error('Bulk Process Error', [
                'error' => $e->getMessage(),
            ]);

            foreach ($documents as $doc) {

                $doc->markAsFailed(
                    $e->getMessage()
                );

                $this->logAction(
                    $doc,
                    'Send',
                    'Failed',
                    $e->getMessage()
                );
            }
        }
    }

    /* =========================
       RESEND
    ========================= */
    public function resend($id)
    {
        $doc = SecureDocument::with([
            'recipients',
            'batch'
        ])->findOrFail($id);

        try {

            $doc->update([
                'status' => 'Queued',
                'error_message' => null,
            ]);

            $doc->increment('resend_count');

            $this->logAction(
                $doc,
                'Resend',
                'Processing',
                'Resend queued'
            );

            dispatch(function () use ($doc) {

                app(SecureDocumentController::class)
                    ->processAndSend($doc);

            });

            return response()->json([
                'message' => 'Resend queued successfully'
            ]);

        } catch (\Exception $e) {

            $this->logAction(
                $doc,
                'Resend',
                'Failed',
                $e->getMessage()
            );

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
        $doc->loadMissing('batch');

        if (
            !$doc->batch ||
            !$doc->batch->password_encrypted
        ) {
            return null;
        }

        return Crypt::decryptString(
            $doc->batch->password_encrypted
        );
    }
}






