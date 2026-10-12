<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public int $expiresInMinutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu código de acceso: {$this->code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.login-code',
        );
    }
}
