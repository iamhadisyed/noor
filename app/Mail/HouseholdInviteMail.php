<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class HouseholdInviteMail extends Mailable
{
    public function __construct(public string $inviterName, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->inviterName} invited you to share their children on Kindo");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.household-invite');
    }
}
