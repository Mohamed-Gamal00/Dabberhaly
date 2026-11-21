<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأكيد استلام طلبك #{{ $order->number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background: #F7F0E5;
            color: #302F2B;
            direction: rtl;
        }
        .wrapper { max-width: 620px; margin: 32px auto; padding: 0 16px 40px; }

        /* Header */
        .header {
            background: linear-gradient(135deg, #76543D 0%, #8d6b51 100%);
            border-radius: 16px 16px 0 0;
            padding: 32px 32px 24px;
            text-align: center;
        }
        .header-icon {
            width: 56px; height: 56px;
            background: rgba(255,255,255,0.15);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            font-size: 28px;
        }
        .header h1 {
            color: #fff;
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .header p {
            color: rgba(255,255,255,0.8);
            font-size: 13px;
        }

        /* Body */
        .body {
            background: #fff;
            padding: 28px 32px;
        }

        /* Alert banner */
        .alert-banner {
            background: #F7F0E5;
            border: 1px solid #D7CDBD;
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            font-size: 14px;
            color: #76543D;
            font-weight: 600;
        }

        /* Section title */
        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #77736B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #E6D3BB;
        }

        /* Info grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }
        .info-item {
            background: #F7F0E5;
            border-radius: 10px;
            padding: 12px 14px;
        }
        .info-label {
            font-size: 11px;
            color: #77736B;
            font-weight: 600;
            margin-bottom: 3px;
        }
        .info-value {
            font-size: 14px;
            font-weight: 700;
            color: #302F2B;
        }

        /* Customer/Shipping address box */
        .address-box {
            background: #F7F0E5;
            border: 1px solid #D7CDBD;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 24px;
            font-size: 13px;
        }
        .address-box .row {
            display: flex;
            gap: 8px;
            margin-bottom: 6px;
        }
        .address-box .row:last-child { margin-bottom: 0; }
        .address-box .lbl { color: #77736B; min-width: 90px; }
        .address-box .val { color: #302F2B; font-weight: 600; }

        /* Products table */
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 13px;
        }
        .products-table th {
            background: #F7F0E5;
            padding: 10px 12px;
            text-align: right;
            font-weight: 700;
            color: #302F2B;
            font-size: 12px;
        }
        .products-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #E6D3BB;
            color: #302F2B;
        }
        .products-table tr:last-child td { border-bottom: none; }

        /* Totals */
        .totals {
            background: #F7F0E5;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 24px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #77736B;
            padding: 4px 0;
        }
        .totals-row.total {
            font-size: 16px;
            font-weight: 800;
            color: #76543D;
            border-top: 1px solid #D7CDBD;
            margin-top: 8px;
            padding-top: 10px;
        }

        /* CTA button */
        .cta {
            text-align: center;
            margin: 24px 0 8px;
        }
        .cta a {
            display: inline-block;
            background: #76543D;
            color: #fff;
            text-decoration: none;
            padding: 13px 32px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        /* Footer */
        .footer {
            background: #F7F0E5;
            border-radius: 0 0 16px 16px;
            padding: 20px 32px;
            text-align: center;
            font-size: 12px;
            color: #77736B;
            border-top: 1px solid #D7CDBD;
        }
        .footer a { color: #76543D; text-decoration: none; }

        @media (max-width: 480px) {
            .info-grid { grid-template-columns: 1fr; }
            .body { padding: 20px 16px; }
        }
    </style>
</head>
<body>
<div class="wrapper">

    <!-- Header -->
    <div class="header">
        <div class="header-icon">🛍️</div>
        <h1>شكراً لتسوقك معنا!</h1>
        <p>{{ config('app.name') }} — {{ $order->created_at ? $order->created_at->format('d/m/Y — H:i') : now()->format('d/m/Y — H:i') }}</p>
    </div>

    <!-- Body -->
    <div class="body">

        <!-- Alert banner -->
        <div class="alert-banner">
            🎉 أهلاً {{ $customerName }}، تم استلام طلبك برقم <strong>#{{ $order->number }}</strong> بنجاح وجارٍ العمل على تجهيزه وشحنه لك.
        </div>

        <!-- Order info grid -->
        <div class="section-title">تفاصيل الطلب</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">رقم الطلب</div>
                <div class="info-value">#{{ $order->number }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">تاريخ الطلب</div>
                <div class="info-value">{{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">طريقة الدفع</div>
                <div class="info-value">
                    {{ $order->payment_method === 'cash_on_delivery' ? 'الدفع عند الاستلام' : 'بطاقة دفع' }}
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">حالة الدفع</div>
                <div class="info-value">
                    @if($order->payment_status === 'paid')
                        ✅ مدفوع
                    @elseif($order->payment_status === 'failed')
                        ❌ غير مدفوع
                    @else
                        ⏳ بانتظار الدفع / عند الاستلام
                    @endif
                </div>
            </div>
        </div>

        <!-- Shipping address -->
        @php
            $addr = $order->shippingAddress ?? $order->addresses->first();
        @endphp
        @if($addr)
        <div class="section-title">عنوان الشحن والتوصيل</div>
        <div class="address-box">
            <div class="row">
                <span class="lbl">المستلم:</span>
                <span class="val">{{ $addr->first_name }} {{ $addr->last_name }}</span>
            </div>
            @if($addr->phone_number)
            <div class="row">
                <span class="lbl">رقم الجوال:</span>
                <span class="val" dir="ltr">{{ $addr->phone_number }}</span>
            </div>
            @endif
            @if($addr->address)
            <div class="row">
                <span class="lbl">العنوان:</span>
                <span class="val">
                    {{ $addr->address }}
                    @if($addr->city) — {{ $addr->city->name_ar ?? $addr->city->name }} @endif
                    @if($addr->country) — {{ $addr->country->name_ar ?? $addr->country->name }} @endif
                </span>
            </div>
            @endif
        </div>
        @endif

        <!-- Products table -->
        <div class="section-title">المنتجات المطلوبة</div>
        <table class="products-table">
            <thead>
                <tr>
                    <th>المنتج</th>
                    <th style="text-align:center">الكمية</th>
                    <th style="text-align:left">السعر</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->orderItems as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td style="text-align:center">{{ $item->quantity }}</td>
                    <td style="text-align:left; font-weight:700; color:#76543D">
                        {{ number_format($item->price, 2) }} جنيه
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        @php
            // So the numbers add up: subtotal − discount + shipping + tax = total.
            $emailTax = (float) ($order->tax_amount ?? 0);
            $emailSubtotal = (float) ($order->totalBeforeDiscount ?? $order->total_price);
            $emailDiscount = max(0, $emailSubtotal - ((float) $order->total_price - (float) ($order->shipping_price ?? 0) - $emailTax));
        @endphp
        <div class="totals">
            <div class="totals-row">
                <span>المجموع</span>
                <span>{{ number_format($order->totalBeforeDiscount ?? $order->total_price, 2) }} جنيه</span>
            </div>
            @if($emailDiscount > 0)
            <div class="totals-row">
                <span>الخصم</span>
                <span>− {{ number_format($emailDiscount, 2) }} جنيه</span>
            </div>
            @endif
            <div class="totals-row">
                <span>تكلفة الشحن</span>
                <span>{{ number_format($order->shipping_price ?? 0, 2) }} جنيه</span>
            </div>
            @if($emailTax > 0)
            <div class="totals-row">
                <span>الضريبة (القيمة المضافة)</span>
                <span>{{ number_format($emailTax, 2) }} جنيه</span>
            </div>
            @endif
            <div class="totals-row total">
                <span>الإجمالي الكلي</span>
                <span>{{ number_format($order->total_price, 2) }} جنيه</span>
            </div>
        </div>

        <!-- CTA -->
        <div class="cta">
            <a href="{{ url('/') }}">
                تصفح متجرنا والمزيد من العروض →
            </a>
        </div>

    </div>

    <!-- Footer -->
    <div class="footer">
        <p>شكراً لثقتكم بمتجر {{ config('app.name') }}.</p>
        <p style="margin-top:6px">إذا كان لديكم أي استفسار، لا تترددوا في التواصل معنا عبر الموقع.</p>
        <p style="margin-top:10px">
            <a href="{{ url('/') }}">{{ config('app.name') }}</a>
        </p>
    </div>

</div>
</body>
</html>
