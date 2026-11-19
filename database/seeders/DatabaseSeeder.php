<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Core seeders — safe (and required) in every environment.
        // They create the skeleton the store cannot run without:
        // admin account, settings row, currencies, countries/cities,
        // shipping row (id 1 — checkout does ShippingTypesAndPrice::find(1)),
        // static pages and store features.
        $this->call(AdminSeeder::class);
        $this->call(SettingsSeeder::class);
        $this->call(CurrencySeeder::class);
        $this->call(CountryCitySeeder::class);
        $this->call(ShippingTypesAndPriceSeeder::class);
        $this->call(StaticPagesSeeder::class);
        $this->call(StoreFeaturesSeeder::class);

        // Demo / content seeders — local development only. In production
        // they would publish fake products, ads and banners as if they
        // were real, and create a test customer account.
        if (app()->environment('local')) {
            $this->call(CompanySeeder::class);
            $this->call(AdvertisementSeeder::class);
            $this->call(ProductSeeder::class);
            $this->call(DesignSeeder::class);
            $this->call(UserSeeder::class);
        }

        // NOTE: TopOffersSeeder is intentionally not called — it duplicates
        // the top_offer_* / new_products_banner rows DesignSeeder already
        // creates, and points at design images that do not exist.
        // NOTE: there is no seeder for Rule / RuleAbility rows (admin
        // permissions); a non-super-admin starts with no abilities until
        // they are assigned from the admin panel.
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
