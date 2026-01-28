<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Admin;

class AdminPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public Admin $admin;
    public string $password;
    public string $loginUrl;

    public function __construct(Admin $admin, string $password, string $loginUrl)
    {
        $this->admin = $admin;
        $this->password = $password;
        $this->loginUrl = $loginUrl;
    }

    public function build()
    {
        return $this->subject('كلمة المرور الخاصة بك — لوحة المشرفين')
            ->view('Emails.admin_password')
            ->with(['name' => $this->admin->name, 'password' => $this->password, 'loginUrl' => $this->loginUrl,]);
    }
}
