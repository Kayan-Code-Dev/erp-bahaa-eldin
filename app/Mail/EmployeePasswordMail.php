<?php

namespace App\Mail;

use App\Models\EmployeeLogin;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeePasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public EmployeeLogin $employeeLogin;
    public string $password;
    public string $loginUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(EmployeeLogin $employeeLogin, string $password, string $loginUrl)
    {
        $this->employeeLogin = $employeeLogin;
        $this->password = $password;
        $this->loginUrl = $loginUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'كلمة المرور الخاصة بك — لوحة الموظف',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'Emails.employee_password', // اعمل فيو جديد هنا
            with: [
                'name' => $this->employeeLogin->employee->first_name . ' ' . $this->employeeLogin->employee->last_name,
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
