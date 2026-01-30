<?php

namespace App\Mail;

use App\Models\BranchManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BranchManagerPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public BranchManager $branchManager;
    public string $password;
    public string $loginUrl;

    public function __construct(BranchManager $branchManager, string $password, string $loginUrl)
    {
        $this->branchManager = $branchManager;
        $this->password = $password;
        $this->loginUrl = $loginUrl;
    }

    public function build()
    {
        return $this->subject('كلمة المرور الخاصة بك — لوحة مدير الفرع')
            ->view('Emails.branch_manager_password') // اعمل فيو جديد
            ->with([
                'name' => $this->branchManager->name,
                'password' => $this->password,
                'loginUrl' => $this->loginUrl,
            ]);
    }
}
