<!DOCTYPE html>
<html lang="ar">

<head>
    <meta charset="UTF-8">
    <title>مرحبا بالمشرف</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            direction: rtl;
        }

        .email-wrapper {
            width: 100%;
            background-color: #f0f2f5;
            padding: 40px 0;
        }

        .email-container {
            max-width: 700px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #ddd;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .email-header {
            background-color: #1a73e8;
            color: #fff;
            text-align: center;
            padding: 25px 0;
            font-size: 28px;
            font-weight: bold;
        }

        .email-body {
            padding: 30px 40px;
            text-align: right;
            color: #333;
            line-height: 1.6;
            font-size: 17px;
        }

        .email-body h2 {
            font-size: 24px;
            font-weight: bold;
            color: #1a73e8;
            margin: 0 0 20px 0;
        }

        .content-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 14px 30px;
            background-color: #1a73e8;
            color: #fff !important;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
            border-radius: 6px;
            margin: 15px 0;
        }

        .otp {
            font-size: 22px;
            font-weight: bold;
            color: #d9534f;
            background-color: #f9f2f2;
            border: 1px solid #d9534f;
            border-radius: 6px;
            padding: 10px 20px;
            display: inline-block;
        }

        .footer {
            background-color: #f9f9f9;
            padding: 20px 40px;
            font-size: 13px;
            color: #777;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="email-wrapper">
        <div class="email-container">
            <div class="email-header">
                مرحباً بك في لوحة المشرفين
            </div>
            <div class="email-body">
                <h2>مرحباً {{ $name }}،</h2>
                <p>تم إنشاء حسابك في لوحة تحكم المشرفين بنجاح.</p>
                <p>لتفعيل حسابك، اضغط على الزر التالي:</p>
                <a href="{{ $activationUrl }}" class="btn">تفعيل الحساب</a>

                <div class="content-row">
                    <span>كود التفعيل الخاص بك هو:</span>
                    <span class="otp">{{ $otp }}</span>
                </div>

                <p>شكراً لانضمامك إلينا!</p>
            </div>
            <div class="footer">
                إذا لم تقم بإنشاء هذا الحساب، يمكنك تجاهل هذه الرسالة بأمان.
            </div>
        </div>
    </div>
</body>

</html>
