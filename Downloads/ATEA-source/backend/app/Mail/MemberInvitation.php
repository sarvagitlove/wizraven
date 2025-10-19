<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public $memberName;
    public $signupLink;
    public $membershipId;
    public $adminName;

    /**
     * Create a new message instance.
     */
    public function __construct($memberName, $signupLink, $membershipId = null, $adminName = 'ATEA Admin')
    {
        $this->memberName = $memberName;
        $this->signupLink = $signupLink;
        $this->membershipId = $membershipId;
        $this->adminName = $adminName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ATEA Seattle - Complete Your Member Profile',
            from: env('MAIL_FROM_ADDRESS', 'hello@atea.org'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.member-invitation',
            text: 'emails.member-invitation-text',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
