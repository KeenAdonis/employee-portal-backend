<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SecureDocumentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $filePath;
    public string $password;

    /**
     * Create a new message instance.
     */
    public function __construct(string $filePath, string $password)
    {
        $this->filePath = $filePath;
        $this->password = $password;
    }

    /**
     * Email subject
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Secure Document',
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
            ],
        );
    }

    /**
     * Attach the PDF
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->filePath)
                ->as('SecureDocument.pdf')
                ->withMime('application/pdf'),
        ];
    }
}