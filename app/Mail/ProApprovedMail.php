<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent when the owner has checked the payment and switched the account to Pro. */
class ProApprovedMail extends Mailable
{
    public function __construct(public string $name, public string $until) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Kindo Pro is on');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.pro-approved');
    }
}
