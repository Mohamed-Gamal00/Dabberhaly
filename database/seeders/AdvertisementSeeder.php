<?php

namespace Database\Seeders;

use App\Models\Advertisement;
use Illuminate\Database\Seeder;

class AdvertisementSeeder extends Seeder
{
    public function run(): void
    {
        // Remove the old ad whose copy used the Saudi currency.
        Advertisement::where('title', 'شحن مجاني للطلبات فوق 200 ريال')->delete();

        $ads = [
            ['title' => 'شحن مجاني للطلبات فوق 200 جنيه',  'title_en' => 'Free shipping on orders over 200 EGP', 'is_active' => true],
            ['title' => 'خصم 10% على أول طلب',              'title_en' => '10% off your first order',            'is_active' => true],
            ['title' => 'تسوق الآن واستمتع بأفضل العروض',  'title_en' => 'Shop now and enjoy the best deals',   'is_active' => true],
        ];

        foreach ($ads as $ad) {
            Advertisement::updateOrCreate(['title' => $ad['title']], $ad);
        }
    }
}
