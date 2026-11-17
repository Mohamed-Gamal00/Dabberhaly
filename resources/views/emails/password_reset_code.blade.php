<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رمز إعادة تعيين كلمة المرور</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background: #F7F0E5;
            color: #302F2B;
            direction: rtl;
        }
        .wrapper { max-width: 560px; margin: 32px auto; padding: 0 16px 40px; }
        .header {
            background: linear-gradient(135deg, #76543D 0%, #8d6b51 100%);
            border-radius: 16px 16px 0 0;
            padding: 32px 32px 24px;
            text-align: center;
        }
        .header h1 { color: #fff; font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        .header p  { color: rgba(255,255,255,0.8); font-size: 14px; }
        .body {
            background: #FFFFFF;
            border-radius: 0 0 16px 16px;
            padding: 32px;
            box-shadow: 0 4px 24px rgba(118,84,61,0.08);
        }
        .greeting { font-size: 16px; color: #302F2B; margin-bottom: 16px; }
        .message  { font-size: 15px; color: #77736B; line-height: 1.7; margin-bottom: 24px; }
        .code-box {
            background: #EFE3D2;
            border: 2px dashed #C69763;
            border-radius: 12px;
            text-align: center;
            padding: 20px;
            margin-bottom: 24px;
        }
        .code-box .code {
            font-size: 40px;
            font-weight: 900;
            letter-spacing: 12px;
            color: #76543D;
        }
        .expiry { font-size: 13px; color: #77736B; text-align: center; margin-bottom: 24px; }
        .footer { text-align: center; font-size: 12px; color: #77736B; margin-top: 24px; }
        .footer a { color: #76543D; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>🔑 إعادة تعيين كلمة المرور</h1>
        <p>{{ config('app.name') }}</p>
    </div>
    <div class="body">
        @if($name)
            <p class="greeting">مرحباً {{ $name }}،</p>
        @endif
        <p class="message">
            استخدم رمز التحقق أدناه لإعادة تعيين كلمة المرور.
            الرمز صالح لمدة <strong>10 دقائق</strong>.
        </p>
        <div class="code-box">
            <div class="code">{{ $code }}</div>
        </div>
        <p class="expiry">إذا لم تطلب إعادة تعيين كلمة المرور، تجاهل هذا البريد.</p>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} {{ config('app.name') }} — جميع الحقوق محفوظة
    </div>
</div>
</body>
</html>
