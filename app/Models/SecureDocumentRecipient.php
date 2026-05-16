<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecureDocumentRecipient extends Model
{
    protected $table = 'secure_document_recipients';

    protected $fillable = [
        'document_id',
        'email',
        'status',
        'error_message',
    ];

    /* =========================
       RELATIONSHIP
    ========================= */

    public function document()
    {
        return $this->belongsTo(SecureDocument::class, 'document_id');
    }

    /* =========================
       HELPERS (OPTIONAL BUT 🔥)
    ========================= */

    public function markAsSent()
    {
        $this->update([
            'status' => 'Sent',
            'error_message' => null,
        ]);
    }

    public function markAsFailed($message)
    {
        $this->update([
            'status' => 'Failed',
            'error_message' => $message,
        ]);
    }
}