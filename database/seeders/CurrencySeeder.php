<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        // The store is Egyptian and sells in Egyptian pounds only, so EGP
        // is the only seeded currency and the default (rate 1 — stored
        // prices are EGP amounts, no conversion). The currency switcher
        // reads this table (GET /api/v1/all-currencies returns the
        // status='used' rows, and App\currency\Currency::getCurrencyApi()
        // appends the default currency's code to API prices), so seeding
        // EGP only means the switcher offers EGP only.
        //
        // SAR was removed deliberately: nothing in the store reads it.
        // Moyasar does NOT read this table — it hardcodes 'SAR' in the
        // payment form, the callback/webhook verification and the
        // payment_transactions default — so removing SAR here cannot break
        // card payments. The delete below also removes SAR (and the other
        // old rows) from databases seeded before this change, since
        // updateOrCreate alone would leave them behind.
        Currency::whereIn('code', ['SAR', 'USD', 'EUR', 'GBP', 'AED'])->delete();

        Currency::updateOrCreate(
            ['code' => 'EGP'],
            [
                'name'                      => 'Egyptian Pound',
                'name_ar'                   => 'جنيه مصري',
                'code'                      => 'EGP',
                'symbol'                    => 'جنيه',
                'price_in_default_currency' => 1,
                'default_currency'          => true,
                'status'                    => 'used',
            ]
        );
    }
}
