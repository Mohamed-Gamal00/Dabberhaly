<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إلغاء الاشتراك — دبّرها</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background: #F7F0E5;
            color: #302F2B;
            direction: rtl;
        }
        .wrapper { max-width: 560px; margin: 64px auto; padding: 0 16px 40px; text-align: center; }
        .card {
            background: #FFFFFF;
            border: 1px solid #D7CDBD;
            border-radius: 16px;
            padding: 40px 32px;
            box-shadow: 0 4px 24px rgba(118,84,61,0.08);
        }
        .brand { color: #76543D; font-size: 26px; font-weight: 900; margin-bottom: 8px; }
        h1 { font-size: 20px; font-weight: 800; color: #302F2B; margin-bottom: 12px; }
        p { font-size: 15px; color: #77736B; line-height: 1.9; margin-bottom: 24px; }
        a.btn {
            display: inline-block;
            background: #76543D;
            color: #FFFFFF;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
            border-radius: 10px;
            padding: 13px 36px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="brand">دبّرها</div>
        <h1>تم إلغاء اشتراكك بنجاح</h1>
        <p>
            لن تصلك نشرتنا البريدية بعد الآن
            @if(!empty($email))
                على <span dir="ltr">{{ $email }}</span>
            @endif
            .<br>
            نتمنى أن نراك قريباً في متجرنا.
        </p>
        <a class="btn" href="{{ url('/') }}">العودة للمتجر</a>
    </div>
</div>
</body>
</html>
