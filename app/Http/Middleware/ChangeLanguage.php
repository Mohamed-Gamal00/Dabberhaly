<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChangeLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        // API requests are stateless (no session middleware), so the
        // locale comes from the Accept-Language header there; web
        // requests keep reading it from the session as before.
        $locale = $request->hasSession()
            ? $request->session()->get('locale', config('app.locale', 'ar'))
            : substr((string) $request->header('Accept-Language', ''), 0, 2);

        if (!in_array($locale, ['ar', 'en'])) {
            $locale = 'ar';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
