<?php

namespace Database\Seeders;

use App\Models\Color;
use App\Models\Company;
use App\Models\MainCategory;
use App\Models\Product;
use App\Models\ProductAvailability;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Availabilities
        $inStock  = ProductAvailability::updateOrCreate(['name' => 'متاح'],    ['name_en' => 'In Stock']);
        $outStock = ProductAvailability::updateOrCreate(['name' => 'غير متاح'], ['name_en' => 'Out of Stock']);

        // Colors
        $red   = Color::updateOrCreate(['color_code' => '#FF0000'], ['name' => 'أحمر']);
        $blue  = Color::updateOrCreate(['color_code' => '#0000FF'], ['name' => 'أزرق']);
        $black = Color::updateOrCreate(['color_code' => '#000000'], ['name' => 'أسود']);
        $white = Color::updateOrCreate(['color_code' => '#FFFFFF'], ['name' => 'أبيض']);

        // Company
        $company = Company::updateOrCreate(
            ['name' => 'الشركة الافتراضية'],
            ['name_en' => 'Default Company']
        );

        // Categories — exactly three: gifts, household essentials,
        // accessories. The old demo categories (electronics, clothing,
        // home & kitchen) and their products are removed below.
        Product::whereIn('slug', ['Smartphone', 'Laptop', 'Cotton-T-Shirt', 'Winter-Jacket'])->delete();
        MainCategory::whereIn('slug', ['electronics', 'clothing', 'home-kitchen'])->delete();

        $gifts = MainCategory::updateOrCreate(
            ['name' => 'هدايا'],
            ['name_en' => 'Gifts', 'slug' => 'gifts', 'description' => 'هدايا مختارة بعناية لمن تحب', 'description_en' => 'Thoughtfully picked gifts for your loved ones']
        );

        $household = MainCategory::updateOrCreate(
            ['name' => 'مستلزمات منزلية'],
            ['name_en' => 'Household Essentials', 'slug' => 'household-essentials', 'description' => 'كل ما يحتاجه بيتك من مستلزمات يومية', 'description_en' => 'Everything your home needs, day after day']
        );

        $accessories = MainCategory::updateOrCreate(
            ['name' => 'اكسسوارات'],
            ['name_en' => 'Accessories', 'slug' => 'accessories', 'description' => 'اكسسوارات ولمسات تكمل بيتك', 'description_en' => 'Accessories and finishing touches for your home']
        );

        // Products
        $products = [
            [
                'name'                   => 'علبة هدايا فاخرة',
                'name_en'                => 'Luxury Gift Box',
                'description'            => 'علبة هدايا فاخرة بتغليف أنيق وجاهزة للإهداء',
                'price'                  => 249.00,
                'discount_price'         => 199.00,
                'quantity'               => 40,
                'status'                 => "active",
                'is_special'             => true,
                'weight'                 => 0.8,
                'category_id'            => $gifts->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $inStock->id,
                'colors'                 => [$red->id, $black->id],
            ],
            [
                'name'                   => 'سلة هدايا منزلية',
                'name_en'                => 'Home Gift Basket',
                'description'            => 'سلة هدايا منزلية مشكلة بأصناف مختارة بعناية',
                'price'                  => 349.00,
                'discount_price'         => null,
                'quantity'               => 25,
                'status'                 => "active",
                'is_special'             => false,
                'weight'                 => 1.5,
                'category_id'            => $gifts->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $inStock->id,
                'colors'                 => [],
            ],
            [
                'name'                   => 'مكنسة كهربائية',
                'name_en'                => 'Vacuum Cleaner',
                'description'            => 'مكنسة كهربائية قوية',
                'price'                  => 450.00,
                'discount_price'         => 399.00,
                'quantity'               => 30,
                'status'                 => "active",
                'is_special'             => false,
                'weight'                 => 3.5,
                'category_id'            => $household->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $inStock->id,
                'colors'                 => [$white->id],
            ],
            [
                'name'                   => 'طقم أواني طبخ',
                'name_en'                => 'Cookware Set',
                'description'            => 'طقم أواني طبخ من الستانلس ستيل',
                'price'                  => 299.00,
                'discount_price'         => null,
                'quantity'               => 0,
                'status'                 => "active",
                'is_special'             => false,
                'weight'                 => 4.0,
                'category_id'            => $household->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $outStock->id,
                'colors'                 => [],
            ],
            [
                'name'                   => 'ساعة حائط',
                'name_en'                => 'Wall Clock',
                'description'            => 'ساعة حائط بتصميم عصري يناسب كل غرف البيت',
                'price'                  => 120.00,
                'discount_price'         => 99.00,
                'quantity'               => 60,
                'status'                 => "active",
                'is_special'             => false,
                'weight'                 => 0.6,
                'category_id'            => $accessories->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $inStock->id,
                'colors'                 => [$black->id, $white->id],
            ],
            [
                'name'                   => 'إطار صور خشبي',
                'name_en'                => 'Wooden Photo Frame',
                'description'            => 'إطار صور خشبي أنيق لذكرياتك المفضلة',
                'price'                  => 85.00,
                'discount_price'         => null,
                'quantity'               => 100,
                'status'                 => "active",
                'is_special'             => true,
                'weight'                 => 0.4,
                'category_id'            => $accessories->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $inStock->id,
                'colors'                 => [$black->id],
            ],
        ];

        foreach ($products as $data) {
            $colors = $data['colors'];
            unset($data['colors']);

            $slug = str_replace(' ', '-', $data['name_en']);
            $product = Product::updateOrCreate(
                ['slug' => $slug],
                array_merge($data, ['slug' => $slug])
            );

            if (!empty($colors)) {
                $product->colors()->syncWithoutDetaching($colors);
            }
        }
    }
}
