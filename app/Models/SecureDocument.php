<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use App\Models\SecureDocumentRecipient;
use App\Models\SecureDocumentBatch;

class SecureDocument extends Model
{
    protected $table = 'secure_documents';

    protected $fillable = [
        'batch_id',
        'employee_name',
        'file_name',
        'file_path',
        'password_encrypted', // ✔ only this
        'status',
        'created_by',
        'queued_at',
        'sent_at',
        'error_message',
        'resend_count',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /* =========================
       STATUS HELPERS
    ========================= */

    public function isDraft()
    {
        return $this->status === 'Draft';
    }

    public function isQueued()
    {
        return $this->status === 'Queued';
    }

    public function isSent()
    {
        return $this->status === 'Sent';
    }

    public function isFailed()
    {
        return $this->status === 'Failed';
    }

    /* =========================
       PASSWORD HANDLING
    ========================= */

    public function setPasswordAttribute($value)
    {
        $this->attributes['password_encrypted'] = Crypt::encryptString($value);
    }

    public function getDecryptedPassword()
    {
        if (!$this->password_encrypted) {
            return null;
        }

        return Crypt::decryptString($this->password_encrypted);
    }

    public function recipients()
    {
        return $this->hasMany(SecureDocumentRecipient::class, 'document_id');
    }

    public function batch()
    {
        return $this->belongsTo(SecureDocumentBatch::class, 'batch_id');
    }

    /* =========================
       STATUS TRANSITIONS
    ========================= */

    public function markAsQueued()
    {
        $this->update([
            'status' => 'Queued',
            'queued_at' => now(),
        ]);
    }

    public function markAsSent()
    {
        $this->update([
            'status' => 'Sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed($error = null)
    {
        $this->update([
            'status' => 'Failed',
            'error_message' => $error,
        ]);
    }

    public function incrementResend()
    {
        $this->increment('resend_count');
    }

    public function getPasswordAttribute()
    {
        return null; // prevent accidental exposure
    }
}