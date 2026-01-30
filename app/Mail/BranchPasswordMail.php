<?php

namespace App\Mail;

use App\Models\Branch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BranchPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public Branch $branch;
    public string $password;
    public string $loginUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Branch $branch, string $password, string $loginUrl)
    {
        $this->branch = $branch;
        $this->password = $password;
        $this->loginUrl = $loginUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'كلمة المرور الخاصة بك — لوحة الفرع',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'Emails.branch_password',
            with: [
                'name' => $this->branch->name,
                'password' => $this->password,
                'loginUrl' => $this->loginUrl,
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
