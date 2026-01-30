<!DOCTYPE html>
<html lang="ar">

<head>
    <meta charset="utf-8">
    <title>تفعيل الحساب</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            direction: rtl;
            background: #f4f6f8;
            margin: 0;
            padding: 20px;
        }

        .card {
            max-width: 600px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            text-align: right;
        }

        h1 {
            color: #1a73e8;
            margin: 0 0 10px 0;
        }

        p {
            color: #333;
            line-height: 1.6;
        }

        .otp-box {
            display: inline-block;
            padding: 12px 18px;
            background: #fff7f7;
            border: 1px solid #f1c0c0;
            color: #d9534f;
            font-weight: 700;
            border-radius: 6px;
            font-size: 20px;
        }

        .form-row {
            margin-top: 18px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        input[type="text"] {
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            flex: 1;
        }

        button {
            padding: 12px 20px;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
        }

        .error {
            color: #d9534f;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>تفعيل حساب الموظف</h1>

        <p>أهلاً <strong>{{ $employee->full_name }}</strong>، نحتاج منك إدخال رمز التفعيل لإكمال تفعيل الحساب.</p>

        <form method="POST" action="{{ route('employee.activate.verify', $employee->uuid) }}">
            @csrf
            <div class="form-row">
                <input type="text" name="otp" placeholder="أدخل كود التفعيل هنا" value="{{ old('otp', '') }}"
                    required>
                <button type="submit">تفعيل الآن</button>
            </div>

            @if ($errors->has('otp'))
                <div class="error">{{ $errors->first('otp') }}</div>
            @endif
        </form>

        <p style="margin-top:18px; color:#777;">إذا لم يصلك الرمز، تأكد من بريدك الإلكتروني أو اطلب إعادة إرسال الكود من
            لوحة الإدارة.</p>
    </div>
</body>

</html>
