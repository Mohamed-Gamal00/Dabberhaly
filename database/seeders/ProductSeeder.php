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
            [
                'name'                   => 'بوكس هدايا شوكولاتة فاخر',
                'name_en'                => 'Premium Chocolate Gift Box',
                'description'            => 'بوكس شوكولاتة فاخر بتغليف أنيق جاهز للإهداء',
                'price'                  => 320.00,
                'discount_price'         => 279.00,
                'quantity'               => 35,
                'status'                 => "active",
                'is_special'             => true,
                'weight'                 => 1.0,
                'category_id'            => $gifts->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $inStock->id,
                'colors'                 => [$red->id, $black->id],
            ],
            [
                'name'                   => 'ورد مجفف في فازة زجاج',
                'name_en'                => 'Dried Flowers in Glass Vase',
                'description'            => 'ورد مجفف طبيعي في فازة زجاج أنيقة لديكور البيت',
                'price'                  => 180.00,
                'discount_price'         => null,
                'quantity'               => 45,
                'status'                 => "active",
                'is_special'             => false,
                'weight'                 => 0.7,
                'category_id'            => $gifts->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $inStock->id,
                'colors'                 => [],
            ],
            [
                'name'                   => 'طقم مجات هدايا',
                'name_en'                => 'Gift Mug Set',
                'description'            => 'طقم مجات سيراميك بألوان هادية مناسب للهدايا',
                'price'                  => 240.00,
                'discount_price'         => 199.00,
                'quantity'               => 50,
                'status'                 => "active",
                'is_special'             => false,
                'weight'                 => 1.2,
                'category_id'            => $gifts->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $inStock->id,
                'colors'                 => [$red->id, $white->id],
            ],
            [
                'name'                   => 'علبة عطور هدايا',
                'name_en'                => 'Perfume Gift Set',
                'description'            => 'علبة عطور مختارة بعناية في تغليف هدايا فاخر',
                'price'                  => 550.00,
                'discount_price'         => 499.00,
                'quantity'               => 0,
                'status'                 => "active",
                'is_special'             => false,
                'weight'                 => 0.9,
                'category_id'            => $gifts->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $outStock->id,
                'colors'                 => [],
            ],
            [
                'name'                   => 'خلاط كهربائي',
                'name_en'                => 'Electric Blender',
                'description'            => 'خلاط كهربائي بسرعات متعددة لتحضير العصائر والصلصات',
                'price'                  => 650.00,
                'discount_price'         => 599.00,
                'quantity'               => 20,
                'status'                 => "active",
                'is_special'             => true,
                'weight'                 => 2.5,
                'category_id'            => $household->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $inStock->id,
                'colors'                 => [$white->id, $black->id],
            ],
            [
                'name'                   => 'طقم ملايات قطن',
                'name_en'                => 'Cotton Bed Sheet Set',
                'description'            => 'طقم ملايات قطن 100% ناعم ومريح لنوم أفضل',
                'price'                  => 420.00,
                'discount_price'         => null,
                'quantity'               => 30,
                'status'                 => "active",
                'is_special'             => false,
                'weight'                 => 1.8,
                'category_id'            => $household->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $inStock->id,
                'colors'                 => [$white->id, $blue->id],
            ],
            [
                'name'                   => 'طقم مناشف حمام',
                'name_en'                => 'Bath Towel Set',
                'description'            => 'طقم مناشف حمام قطن بامتصاص عالي ونعومة تدوم',
                'price'                  => 280.00,
                'discount_price'         => 249.00,
                'quantity'               => 55,
                'status'                 => "active",
                'is_special'             => false,
                'weight'                 => 1.1,
                'category_id'            => $household->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $inStock->id,
                'colors'                 => [$white->id, $blue->id],
            ],
            [
                'name'                   => 'مصباح طاولة LED',
                'name_en'                => 'LED Table Lamp',
                'description'            => 'مصباح طاولة LED بإضاءة دافئة وتصميم عصري',
                'price'                  => 160.00,
                'discount_price'         => null,
                'quantity'               => 70,
                'status'                 => "active",
                'is_special'             => false,
                'weight'                 => 0.9,
                'category_id'            => $household->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $inStock->id,
                'colors'                 => [$black->id, $white->id],
            ],
            [
                'name'                   => 'مرآة حائط ديكور',
                'name_en'                => 'Decorative Wall Mirror',
                'description'            => 'مرآة حائط بإطار خشبي تضيف لمسة أنيقة لأي غرفة',
                'price'                  => 350.00,
                'discount_price'         => 299.00,
                'quantity'               => 15,
                'status'                 => "active",
                'is_special'             => false,
                'weight'                 => 2.0,
                'category_id'            => $accessories->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $inStock->id,
                'colors'                 => [$black->id],
            ],
            [
                'name'                   => 'شموع معطرة طقم',
                'name_en'                => 'Scented Candle Set',
                'description'            => 'طقم شموع معطرة بروائح هادية لأجواء مريحة في البيت',
                'price'                  => 140.00,
                'discount_price'         => null,
                'quantity'               => 80,
                'status'                 => "active",
                'is_special'             => false,
                'weight'                 => 0.5,
                'category_id'            => $accessories->id,
                'company_id'             => $company->id,
                'product_availability_id'=> $inStock->id,
                'colors'                 => [],
            ],
        ];

        foreach ($products as $data) {
            $colors = $data['colors'];
            unset($data['colors']);

            // Match by the English name, not the slug: rows seeded before
            // the slug fix carry Arabic slugs, so matching by slug never
            // found them and every re-seed duplicated the products.
            // The slug is set to the English slug on create/update.
            $slug = str_replace(' ', '-', $data['name_en']);
            $product = Product::updateOrCreate(
                ['name_en' => $data['name_en']],
                array_merge($data, ['slug' => $slug])
            );

            if (!empty($colors)) {
                $product->colors()->syncWithoutDetaching($colors);
            }
        }
    }
}
