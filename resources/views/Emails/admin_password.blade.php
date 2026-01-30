<!DOCTYPE html>
<html lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كلمة المرور الخاصة بك</title>
</head>

<body dir="rtl"
    style="margin:0; padding:0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color:#f4f4f7;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f7; padding: 40px 0;">
        <tr>
            <td align="center">
                <!-- الصندوق الرئيسي -->
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                    <!-- رأس البريد -->
                    <tr>
                        <td style="background-color:#1a73e8; padding: 20px; text-align:center; color:#ffffff;">
                            <h1 style="margin:0; font-size:24px;">مرحباً {{ $name }}!</h1>
                        </td>
                    </tr>
                    <!-- جسم البريد -->
                    <tr>
                        <td style="padding:30px; color:#333333; font-size:16px; line-height:1.6;">
                            <p>تم تفعيل حسابك في لوحة المشرفين بنجاح.</p>
                            <p>كلمة المرور الخاصة بك:</p>
                            <div style="text-align:center; margin:20px 0;">
                                <span
                                    style="font-size:20px; font-weight:bold; background-color:#e8f0fe; padding:15px 25px; border-radius:6px; letter-spacing:2px; display:inline-block;">{{ $password }}</span>
                            </div>
                            <p>يمكنك تسجيل الدخول عبر الزر أدناه:</p>
                            <div style="text-align:center; margin:30px 0;">
                                <a href="{{ $loginUrl }}"
                                    style="background-color:#1a73e8; color:#ffffff; text-decoration:none; font-weight:bold; padding:12px 25px; border-radius:6px; display:inline-block;">تسجيل
                                    الدخول</a>
                            </div>
                            <p>إذا لم تطلب هذا الحساب، يمكنك تجاهل هذه الرسالة بأمان.</p>
                        </td>
                    </tr>
                    <!-- تذييل البريد -->
                    <tr>
                        <td
                            style="background-color:#f4f4f7; text-align:center; padding:15px; font-size:12px; color:#888888;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}. جميع الحقوق محفوظة.<br>
                            <a href="#" style="color:#888888; text-decoration:underline;">سياسة الخصوصية</a> |
                            <a href="#" style="color:#888888; text-decoration:underline;">إلغاء الاشتراك</a>
                        </td>
                    </tr>
                </table>
                <!-- نهاية الصندوق -->
            </td>
        </tr>
    </table>
</body>

</html>
