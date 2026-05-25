<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BulkSecureDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $files;
    public string $password;

    /**
     * Create a new message instance.
     */
    public function __construct(array $files, string $password)
    {
        $this->files = $files;
        $this->password = $password;
    }

    /**
     * Email subject
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Secure Documents',
        );
    }

    /**
     * Email body
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.secure-document',
            with: [
                'password' => $this->password,
                'files' => $this->files,
            ],
        );
    }

    /**
     * Multiple Attachments
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->files as $file) {

            $attachments[] = Attachment::fromPath($file['path'])
                ->as($file['file_name'])
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}