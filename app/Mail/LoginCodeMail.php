<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LoginCodeMail extends Mailable
{
    public function __construct(public string $code, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your Kindo sign-in code: {$this->code}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.login-code');
    }
}
