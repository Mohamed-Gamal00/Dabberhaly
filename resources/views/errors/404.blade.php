<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ app()->getLocale() === 'ar' ? 'الصفحة غير موجودة' : 'Page Not Found' }} — {{ config('app.name', 'Dabberhaly') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #F7F0E5;
            color: #302F2B;
            font-family: system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif;
            padding: 24px 16px;
        }
        .card {
            width: 100%;
            max-width: 480px;
            background: #FFFFFF;
            border: 1px solid #D7CDBD;
            border-radius: 20px;
            padding: 48px 32px 40px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(118, 84, 61, 0.10);
        }
        .code {
            font-size: 72px;
            font-weight: 800;
            line-height: 1;
            color: #76543D;
        }
        .icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #EFE7D8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
        }
        h1 { font-size: 22px; font-weight: 800; margin: 16px 0 8px; }
        p { font-size: 14px; line-height: 1.9; color: #77736B; margin-bottom: 28px; }
        .btn {
            display: inline-block;
            background: #76543D;
            color: #FFFFFF;
            text-decoration: none;
            font-size: 15px;
            font-weight: 700;
            padding: 12px 32px;
            border-radius: 12px;
            transition: background 0.15s;
        }
        .btn:hover { background: #5E4331; }
        .btn-outline {
            background: #FFFFFF;
            color: #76543D;
            border: 1.5px solid #D7CDBD;
            margin-inline-start: 8px;
        }
        .btn-outline:hover { border-color: #76543D; background: #F7F0E5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&#128270;</div>
        <div class="code">404</div>
        <h1>{{ app()->getLocale() === 'ar' ? 'الصفحة غير موجودة' : 'Page Not Found' }}</h1>
        <p>
            {{ app()->getLocale() === 'ar'
                ? 'عذرًا، الصفحة التي تبحث عنها غير موجودة أو تم نقلها. تأكد من الرابط أو عُد للرئيسية.'
                : 'Sorry, the page you are looking for does not exist or has been moved. Check the link or go back home.' }}
        </p>
        <a class="btn" href="{{ url('/') }}">{{ app()->getLocale() === 'ar' ? 'العودة للرئيسية' : 'Back to Home' }}</a>
        <a class="btn btn-outline" href="{{ url('/products') }}">{{ app()->getLocale() === 'ar' ? 'تصفح المنتجات' : 'Browse Products' }}</a>
    </div>
</body>
</html>
