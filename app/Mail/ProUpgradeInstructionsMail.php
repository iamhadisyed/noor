<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent to a parent who asked to be made Pro: how to pay and what to send back. */
class ProUpgradeInstructionsMail extends Mailable
{
    public function __construct(public string $name, public string $instructions, public string $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'How to get Kindo Pro');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.pro-instructions');
    }
}
