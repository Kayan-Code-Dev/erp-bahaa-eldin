<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Admin;

class WelcomeAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public Admin $admin;
    public $otp;
    public $activationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Admin $admin, $otp, $activationUrl)
    {
        $this->admin = $admin;
        $this->otp = $otp;
        $this->activationUrl = $activationUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Admin Panel',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'Emails.welcome_admin', // لاحقًا تنشئ هذا الفيو
            with: [
                'name' => $this->admin->name,
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
