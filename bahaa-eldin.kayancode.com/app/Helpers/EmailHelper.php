<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Mail;

class EmailHelper
{
    public static function sendEmail($to, $subject, $body, $attachments = [])
    {
        Mail::send([], [], function ($message) use ($to, $subject, $body, $attachments) {
            $message->to($to)
                ->subject($subject)
                ->setBody($body, 'text/html');

            foreach ($attachments as $file) {
                $message->attach($file);
            }
        });
    }



    public static function sendTemporaryPassword($email, $tempPassword, $name = null)
    {
        $subject = 'استعادة كلمة المرور';

        Mail::send('Emails.forgot_password', [
            'tempPassword' => $tempPassword,
            'name'         => $name,
            'email'        => $email
        ], function ($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });
    }
}
