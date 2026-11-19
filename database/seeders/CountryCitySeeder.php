<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Seeder;

class CountryCitySeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'country' => ['name_ar' => 'مصر', 'name_en' => 'Egypt', 'code' => 'EG', 'phone_code' => '+20', 'status' => 'used'],
                'cities'  => [
                    ['name_ar' => 'القاهرة', 'name_en' => 'Cairo', 'shipping_price' => 20],
                    ['name_ar' => 'الجيزة', 'name_en' => 'Giza', 'shipping_price' => 20],
                    ['name_ar' => 'الإسكندرية', 'name_en' => 'Alexandria', 'shipping_price' => 30],
                    ['name_ar' => 'الدقهلية', 'name_en' => 'Dakahlia', 'shipping_price' => 30],
                    ['name_ar' => 'البحيرة', 'name_en' => 'Beheira', 'shipping_price' => 30],
                    ['name_ar' => 'الفيوم', 'name_en' => 'Fayoum', 'shipping_price' => 35],
                    ['name_ar' => 'الغربية', 'name_en' => 'Gharbia', 'shipping_price' => 30],
                    ['name_ar' => 'الإسماعيلية', 'name_en' => 'Ismailia', 'shipping_price' => 35],
                    ['name_ar' => 'المنوفية', 'name_en' => 'Menofia', 'shipping_price' => 30],
                    ['name_ar' => 'المنيا', 'name_en' => 'Minya', 'shipping_price' => 40],
                    ['name_ar' => 'القليوبية', 'name_en' => 'Qalubia', 'shipping_price' => 25],
                    ['name_ar' => 'الوادي الجديد', 'name_en' => 'New Valley', 'shipping_price' => 50],
                    ['name_ar' => 'السويس', 'name_en' => 'Suez', 'shipping_price' => 35],
                    ['name_ar' => 'أسوان', 'name_en' => 'Aswan', 'shipping_price' => 50],
                    ['name_ar' => 'أسيوط', 'name_en' => 'Assiut', 'shipping_price' => 40],
                    ['name_ar' => 'بني سويف', 'name_en' => 'Beni Suef', 'shipping_price' => 35],
                    ['name_ar' => 'بورسعيد', 'name_en' => 'Port Said', 'shipping_price' => 35],
                    ['name_ar' => 'دمياط', 'name_en' => 'Damietta', 'shipping_price' => 35],
                    ['name_ar' => 'الشرقية', 'name_en' => 'Sharkia', 'shipping_price' => 30],
                    ['name_ar' => 'جنوب سيناء', 'name_en' => 'South Sinai', 'shipping_price' => 50],
                    ['name_ar' => 'كفر الشيخ', 'name_en' => 'Kafr El Sheikh', 'shipping_price' => 35],
                    ['name_ar' => 'مطروح', 'name_en' => 'Matrouh', 'shipping_price' => 50],
                    ['name_ar' => 'الأقصر', 'name_en' => 'Luxor', 'shipping_price' => 45],
                    ['name_ar' => 'قنا', 'name_en' => 'Qena', 'shipping_price' => 45],
                    ['name_ar' => 'شمال سيناء', 'name_en' => 'North Sinai', 'shipping_price' => 50],
                    ['name_ar' => 'سوهاج', 'name_en' => 'Sohag', 'shipping_price' => 40],
                    ['name_ar' => 'البحر الأحمر', 'name_en' => 'Red Sea', 'shipping_price' => 45],
                ],
            ],
            [
                'country' => ['name_ar' => 'المملكة العربية السعودية', 'name_en' => 'Saudi Arabia', 'code' => 'SA', 'phone_code' => '+966', 'status' => 'used'],
                'cities'  => [
                    ['name_ar' => 'الرياض',  'name_en' => 'Riyadh',  'shipping_price' => 20],
                    ['name_ar' => 'جدة',     'name_en' => 'Jeddah',  'shipping_price' => 25],
                    ['name_ar' => 'مكة المكرمة', 'name_en' => 'Mecca','shipping_price' => 25],
                    ['name_ar' => 'المدينة المنورة', 'name_en' => 'Medina', 'shipping_price' => 30],
                    ['name_ar' => 'الدمام',  'name_en' => 'Dammam',  'shipping_price' => 30],
                    ['name_ar' => 'الخبر',   'name_en' => 'Khobar',  'shipping_price' => 30],
                    ['name_ar' => 'أبها',    'name_en' => 'Abha',    'shipping_price' => 35],
                    ['name_ar' => 'تبوك',    'name_en' => 'Tabuk',   'shipping_price' => 35],
                ],
            ],
            [
                'country' => ['name_ar' => 'الإمارات العربية المتحدة', 'name_en' => 'United Arab Emirates', 'code' => 'AE', 'phone_code' => '+971', 'status' => 'used'],
                'cities'  => [
                    ['name_ar' => 'دبي',      'name_en' => 'Dubai',      'shipping_price' => 40],
                    ['name_ar' => 'أبوظبي',   'name_en' => 'Abu Dhabi',  'shipping_price' => 40],
                    ['name_ar' => 'الشارقة',  'name_en' => 'Sharjah',    'shipping_price' => 45],
                    ['name_ar' => 'عجمان',    'name_en' => 'Ajman',      'shipping_price' => 45],
                ],
            ],
            [
                'country' => ['name_ar' => 'الكويت', 'name_en' => 'Kuwait', 'code' => 'KW', 'phone_code' => '+965', 'status' => 'used'],
                'cities'  => [
                    ['name_ar' => 'مدينة الكويت', 'name_en' => 'Kuwait City', 'shipping_price' => 50],
                    ['name_ar' => 'حولي',         'name_en' => 'Hawalli',     'shipping_price' => 50],
                    ['name_ar' => 'الفروانية',    'name_en' => 'Farwaniya',   'shipping_price' => 50],
                ],
            ],
            [
                'country' => ['name_ar' => 'البحرين', 'name_en' => 'Bahrain', 'code' => 'BH', 'phone_code' => '+973', 'status' => 'used'],
                'cities'  => [
                    ['name_ar' => 'المنامة', 'name_en' => 'Manama',   'shipping_price' => 50],
                    ['name_ar' => 'المحرق',  'name_en' => 'Muharraq', 'shipping_price' => 50],
                ],
            ],
            [
                'country' => ['name_ar' => 'قطر', 'name_en' => 'Qatar', 'code' => 'QA', 'phone_code' => '+974', 'status' => 'used'],
                'cities'  => [
                    ['name_ar' => 'الدوحة',   'name_en' => 'Doha',      'shipping_price' => 50],
                    ['name_ar' => 'الريان',   'name_en' => 'Al Rayyan', 'shipping_price' => 50],
                ],
            ],
        ];

        foreach ($data as $entry) {
            $country = Country::updateOrCreate(
                ['code' => $entry['country']['code']],
                $entry['country']
            );

            foreach ($entry['cities'] as $city) {
                    City::updateOrCreate(
                    ['name_en' => $city['name_en'], 'country_id' => $country->id],
                    array_merge($city, ['country_id' => $country->id, 'status' => 'used'])
                );
            }
        }
    }
}
