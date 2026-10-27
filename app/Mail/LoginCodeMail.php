<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LoginCodeMail extends Mailable
{
    public function __construct(public string $code, public int $minutes, public string $purpose = 'login') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->purpose === 'delete' ? "Confirm deleting your Kindo account: {$this->code}" : "Your Kindo sign-in code: {$this->code}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.login-code');
    }
}
