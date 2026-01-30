<?php

namespace App\Mail;

use App\Models\Branch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeBranchMail extends Mailable
{
    use Queueable, SerializesModels;

    public Branch $branch;
    public $otp;
    public $activationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Branch $branch, $otp, $activationUrl)
    {
        $this->branch = $branch;
        $this->otp = $otp;
        $this->activationUrl = $activationUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Branch Panel',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'Emails.welcome_branch', // اعمل فيو جديد هنا
            with: [
                'name' => $this->branch->name,
                'otp' => $this->otp,
                'activationUrl' => $this->activationUrl,
            ],
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
