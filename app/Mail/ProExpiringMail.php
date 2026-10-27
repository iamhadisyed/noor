<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ProExpiringMail extends Mailable
{
    public function __construct(public string $name, public string $date, public int $days) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your Kindo Pro ends on {$this->date}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.pro-expiring');
    }
}
