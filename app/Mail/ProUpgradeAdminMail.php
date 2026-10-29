<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent to the owner: someone asked for Pro, or says they have paid. */
class ProUpgradeAdminMail extends Mailable
{
    public function __construct(public string $kind, public string $email, public string $name, public ?string $phone, public ?string $detail) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->kind === 'paid' ? "Kindo: {$this->email} says they have paid for Pro" : "Kindo: {$this->email} wants Pro");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.pro-admin');
    }
}
