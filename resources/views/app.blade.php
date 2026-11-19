<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        use App\Models\Setting;
        $s = cache()->remember('site_settings', 3600, fn() => Setting::first());
    @endphp

    {{-- ── Robots + Canonical ──
         Owned by SeoHead.vue (via @inertiaHead) so there is exactly one
         of each per page. The old blade tags emitted a second canonical
         and, when canonical_url was set in settings, forced the same URL
         onto every page. --}}

    {{-- ── Google Site Verification ── --}}
    @if($s?->google_site_verification)
        <meta name="google-site-verification" content="{{ $s->google_site_verification }}">
    @endif

    {{-- ── Favicon ── --}}
    @if($s?->image)
        <link rel="icon" type="image/x-icon" href="{{ asset('storage/' . $s->image) }}">
    @endif

    {{-- ── Google Tag Manager (head) ── --}}
    @if($s?->google_tag_manager_id)
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','{{ $s->google_tag_manager_id }}');</script>
    @endif

    {{-- ── Google Analytics (GA4) ── --}}
    @if($s?->google_analytics_id)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $s->google_analytics_id }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $s->google_analytics_id }}');
    </script>
    @endif

    @routes
    @vite('resources/js/app.js')
    {{-- app.css is already imported by resources/js/app.js, so no separate @vite entry for it: a separate entry is not in the Vite manifest and breaks the production build with a 500. --}}
    @inertiaHead
</head>
<body>
    {{-- ── Google Tag Manager (body) ── --}}
    @if($s?->google_tag_manager_id)
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $s->google_tag_manager_id }}"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif

    @inertia
</body>
</html>
