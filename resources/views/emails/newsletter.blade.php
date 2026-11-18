<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background: #F7F0E5;
            color: #302F2B;
            direction: rtl;
        }
        .wrapper { max-width: 600px; margin: 32px auto; padding: 0 16px 40px; }
        .header {
            background: linear-gradient(135deg, #76543D 0%, #8d6b51 100%);
            border-radius: 16px 16px 0 0;
            padding: 32px 32px 24px;
            text-align: center;
        }
        .header .brand { color: #FFFFFF; font-size: 26px; font-weight: 900; margin-bottom: 4px; }
        .header .brand-en { color: rgba(255,255,255,0.75); font-size: 12px; letter-spacing: 3px; }
        .header .badge {
            display: inline-block;
            margin-top: 16px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.35);
            color: #FFFFFF;
            font-size: 12px;
            font-weight: 700;
            border-radius: 20px;
            padding: 6px 14px;
        }
        .body {
            background: #FFFFFF;
            border: 1px solid #D7CDBD;
            border-top: none;
            border-radius: 0 0 16px 16px;
            padding: 32px;
            box-shadow: 0 4px 24px rgba(118,84,61,0.08);
        }
        .greeting { font-size: 16px; font-weight: 700; color: #302F2B; margin-bottom: 8px; }
        .newsletter-title { font-size: 20px; font-weight: 800; color: #76543D; line-height: 1.5; margin-bottom: 12px; }
        .divider { height: 3px; width: 56px; background: #C69763; border-radius: 3px; margin-bottom: 20px; }
        .message { font-size: 15px; color: #302F2B; line-height: 1.9; white-space: pre-line; }
        .cta { text-align: center; margin: 28px 0 8px; }
        .cta a {
            display: inline-block;
            background: #76543D;
            color: #FFFFFF;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
            border-radius: 10px;
            padding: 13px 36px;
        }
        .cta-note { text-align: center; font-size: 13px; color: #77736B; margin-bottom: 8px; }
        .info-box {
            background: #F7F0E5;
            border: 1px solid #D7CDBD;
            border-radius: 12px;
            padding: 16px 18px;
            margin-top: 24px;
            font-size: 13px;
            color: #77736B;
            line-height: 1.8;
        }
        .info-box strong { color: #76543D; }
        .footer { text-align: center; font-size: 12px; color: #77736B; margin-top: 24px; line-height: 1.8; }
        .footer a { color: #76543D; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <div class="brand">دبّرها</div>
        <div class="brand-en">DABBERHALY</div>
        <div class="badge">النشرة البريدية</div>
    </div>
    <div class="body">
        <p class="greeting">مرحباً!</p>
        <h1 class="newsletter-title">{{ $title }}</h1>
        <div class="divider"></div>

        <div class="message">{{ $body }}</div>

        <div class="cta">
            <a href="{{ url('/') }}">تسوق الآن</a>
        </div>
        <p class="cta-note">اكتشف أحدث المنتجات والعروض من دبّرها — مستلزمات بيتك العملية في مكان واحد.</p>

        <div class="info-box">
            <strong>لماذا تصلك هذه الرسالة؟</strong><br>
            لأنك اشتركت في النشرة البريدية لموقع دبّرها
            @if(!empty($subscriberEmail))
                بالبريد <span dir="ltr">{{ $subscriberEmail }}</span>
            @endif
            ، سواء من الفوتر أو أثناء إتمام طلبك.
        </div>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} {{ config('app.name', 'Dabberhaly') }} — جميع الحقوق محفوظة<br>
        <a href="{{ url('/') }}">{{ url('/') }}</a>
    </div>
</div>
</body>
</html>
