<?php

namespace App\currency;

use Illuminate\Support\Facades\DB;

class Currency
{
    public function getCurrency($price, $currentCurrency = 1)
    {
        $sessionCurrency = DB::table('currencies')->where('id', session('currency_id'))->first();

        $defaultCurrency = DB::table('currencies')->where('default_currency', true)->first();
        $code = '';


        if ($sessionCurrency) {
            $currentCurrency = $sessionCurrency->price_in_default_currency;
            $code = $sessionCurrency->code;
        } else {
            $currentCurrency = 1;
            $code = $defaultCurrency->code;
        }

        if (session('currency_id') == $defaultCurrency->id) {
            $currentCurrency = 1;
            $code = $defaultCurrency->code;
        }

        return $price * $currentCurrency . $code;
    }

    /**
     * Request-scoped cache of the currency rows getCurrencyApi() needs,
     * kept on the current request (so it dies with the request and can
     * never leak into another request or test). It used to run 2 queries
     * per call — 3 calls per product in the product resources — so a
     * 10-product list ran 60+ currency queries.
     */
    public static function getCurrencyApi($price, $currentCurrency = 1, $showCurrency = true)
    {
        $currencyId = request()->header('currencyId');
        $bag = request()->attributes;
        $cache = $bag->get('currency_api_cache', []);

        if (!array_key_exists($currencyId ?? '', $cache)) {
            $cache[$currencyId ?? ''] = [
                'header'  => DB::table('currencies')->where('id', $currencyId)->first(),
                'default' => DB::table('currencies')->where('default_currency', true)->first(),
            ];
            $bag->set('currency_api_cache', $cache);
        }

        $headerCurrency = $cache[$currencyId ?? '']['header'];
        $defaultCurrency = $cache[$currencyId ?? '']['default'];
        $code = '';


        if ($headerCurrency) {
            $currentCurrency = $headerCurrency->price_in_default_currency;
            $code = $headerCurrency->code;
        } else {
            $currentCurrency = 1;
            $code = $defaultCurrency?->code ?? '';
        }

        if ($defaultCurrency && request()->header('currencyId') == $defaultCurrency->id) {
            $currentCurrency = 1;
            $code = $defaultCurrency->code;
        }

        if ($showCurrency) {
            return $price * $currentCurrency . $code;
        }
        return $price * $currentCurrency;
    }
}