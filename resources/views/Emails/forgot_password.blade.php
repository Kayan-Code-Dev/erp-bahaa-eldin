<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعادة كلمة المرور</title>
</head>

<body
    style="margin:0; padding:0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color:#f4f4f7; direction:rtl; text-align:right;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f7; padding: 40px 0;">
        <tr>
            <td align="center">
                <!-- الصندوق الرئيسي -->
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1); direction:rtl; text-align:right;">

                    <!-- رأس البريد -->
                    <tr>
                        <td style="background-color:#1a73e8; padding: 20px; text-align:center; color:#ffffff;">
                            <h1 style="margin:0; font-size:24px;">مرحباً {{ $name }}!</h1>
                        </td>
                    </tr>

                    <!-- جسم البريد -->
                    <tr>
                        <td
                            style="padding:30px; color:#333333; font-size:16px; line-height:1.6; text-align:right; direction:rtl;">
                            <p>لقد تلقينا طلب استعادة كلمة المرور لحسابك.</p>
                            <p>رمز استعادة كلمة المرور هو:</p>
                            <div style="text-align:center; margin:20px 0; direction:rtl;">
                                <span
                                    style="font-size:20px; font-weight:bold; background-color:#e8f0fe; padding:15px 25px; border-radius:6px; letter-spacing:2px; display:inline-block;">
                                    {{ $tempPassword }}
                                </span>
                            </div>
                            <p>يمكنك استخدام هذا الرمز لإكمال العملية.</p>
                            <p>إذا لم تطلب هذا، يمكنك تجاهل الرسالة بأمان.</p>
                        </td>
                    </tr>

                    <!-- تذييل البريد -->
                    <tr>
                        <td
                            style="background-color:#f4f4f7; text-align:center; padding:15px; font-size:12px; color:#888888; direction:rtl;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. جميع الحقوق محفوظة.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>
