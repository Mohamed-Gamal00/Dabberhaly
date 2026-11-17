<?php

namespace Database\Seeders;

use App\Models\Design;
use Illuminate\Database\Seeder;

class DesignSeeder extends Seeder
{
    public function run(): void
    {
        $designs = [
            // Home page banners
            [
                'title'       => 'البانر الرئيسي',
                'page_name'   => 'home',
                'description' => 'البانر الرئيسي للصفحة الرئيسية',
            ],
            [
                'title'       => 'بانر العروض',
                'page_name'   => 'home',
                'description' => 'بانر عروض الصفحة الرئيسية',
            ],
            [
                'title'       => 'بانر المنتجات المميزة',
                'page_name'   => 'home',
                'description' => 'بانر المنتجات المميزة',
            ],
            // Home page — mid section (band covers)
            [
                'title'       => 'home_band_left',
                'page_name'   => 'home',
                'description' => 'الصورة اليسرى في قسم البانرات الوسطى بالصفحة الرئيسية',
            ],
            [
                'title'       => 'home_band_right',
                'page_name'   => 'home',
                'description' => 'الصورة اليمنى في قسم البانرات الوسطى بالصفحة الرئيسية',
            ],
            // Home page — TV banner
            [
                'title'       => 'home_tv_banner',
                'page_name'   => 'home',
                'description' => 'بانر التلفزيون في الصفحة الرئيسية',
            ],
            // Home page — top offers
            [
                'title'       => 'top_offer_1',
                'page_name'   => 'home',
                'description' => 'العرض المميز الأول في الصفحة الرئيسية',
            ],
            [
                'title'       => 'top_offer_2',
                'page_name'   => 'home',
                'description' => 'العرض المميز الثاني في الصفحة الرئيسية',
            ],
            [
                'title'       => 'top_offer_3',
                'page_name'   => 'home',
                'description' => 'العرض المميز الثالث في الصفحة الرئيسية',
            ],
            [
                'title'       => 'top_offer_4',
                'page_name'   => 'home',
                'description' => 'العرض المميز الرابع في الصفحة الرئيسية',
            ],
            [
                'title'       => 'top_offer_5',
                'page_name'   => 'home',
                'description' => 'العرض المميز الخامس في الصفحة الرئيسية',
            ],
            // Home page — new products banner
            [
                'title'       => 'new_products_banner',
                'page_name'   => 'home',
                'description' => 'بانر المنتجات الجديدة في الصفحة الرئيسية',
            ],
            // Offers page banners
            [
                'title'       => 'بانر صفحة العروض',
                'page_name'   => 'offers-page',
                'description' => 'البانر الرئيسي لصفحة العروض',
            ],
            [
                'title'       => 'بانر التخفيضات',
                'page_name'   => 'offers-page',
                'description' => 'بانر التخفيضات الموسمية',
            ],
        ];

        foreach ($designs as $design) {
            Design::updateOrCreate(
                ['title' => $design['title'], 'page_name' => $design['page_name']],
                $design
            );
        }
    }
}
