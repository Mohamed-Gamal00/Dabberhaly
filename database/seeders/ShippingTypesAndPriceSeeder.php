<?php

namespace Database\Seeders;

use App\Models\ShippingTypesAndPrice;
use Illuminate\Database\Seeder;

class ShippingTypesAndPriceSeeder extends Seeder
{
    /**
     * Seed the shipping_types_and_price table with default settings.
     */
    public function run(): void
    {
        ShippingTypesAndPrice::updateOrCreate(
            ['id' => 1],
            [
                'add_pickup_from_store'      => true,
                'add_wight_price'            => true,
                'add_normal_price'           => true,
                'add_price_based_on_city'    => false,
                'weight_price'               => 5.00,
                'normal_shipping_price'      => 20.00,
            ]
        );
    }
}
