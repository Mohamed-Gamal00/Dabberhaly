<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Choice;
use App\Models\Color;
use App\Models\Country;
use App\Models\Currency;
use App\Models\DiscountCode;
use App\Models\HeaderBanner;
use App\Models\MainCategory;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingTypesAndPrice;
use App\Models\StoreFatuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Validation gaps in the admin (Blade) forms: one test per rule fixed.
 *
 * Every test posts the invalid value and asserts the validation error,
 * and posts the valid value and asserts the request succeeds.
 */
class AdminFormValidationTest extends TestCase
{
    use RefreshDatabase;

    private int $countryId;
    private int $cityId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->countryId = DB::table('countries')->insertGetId([
            'name_ar' => 'مصر', 'name_en' => 'Egypt', 'status' => 'used',
        ]);
        $this->cityId = DB::table('cities')->insertGetId([
            'name_ar' => 'القاهرة', 'name_en' => 'Cairo', 'status' => 'used',
            'country_id' => $this->countryId, 'shipping_price' => 25, 'code' => 1,
        ]);
    }

    private ?Admin $admin = null;

    private function makeAdmin(): Admin
    {
        return $this->admin ??= Admin::forceCreate([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'super_admin' => true,
        ]);
    }

    private function asAdmin()
    {
        return $this->actingAs($this->makeAdmin(), 'admin');
    }

    private function validClientPayload(User $client, array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'محمد',
            'family_name' => 'أحمد',
            'email' => $client->email,
            'phone_number' => '01012345678',
        ], $overrides);
    }

    // ===== Clients (ClientRequest) =====

    public function test_client_phone_accepts_egyptian_plus20_number(): void
    {
        $client = User::factory()->create(['password' => 'password']);

        // The old `numeric` rule rejected valid + phones; the
        // Egyptian-mobile rule accepts the +20 form.
        $response = $this->asAdmin()->put(
            route('clients.update', $client),
            $this->validClientPayload($client, ['phone_number' => '+201011234567'])
        );

        $response->assertSessionHasNoErrors();
        $this->assertSame('+201011234567', $client->fresh()->phone_number);
    }

    public function test_client_phone_rejects_garbage(): void
    {
        $client = User::factory()->create(['password' => 'password']);

        $response = $this->asAdmin()->put(
            route('clients.update', $client),
            $this->validClientPayload($client, ['phone_number' => 'not-a-phone'])
        );

        $response->assertSessionHasErrors('phone_number');
    }

    public function test_client_email_must_be_unique(): void
    {
        $client = User::factory()->create(['password' => 'password']);
        $other = User::factory()->create(['password' => 'password']);

        $response = $this->asAdmin()->put(
            route('clients.update', $client),
            $this->validClientPayload($client, ['email' => $other->email])
        );

        $response->assertSessionHasErrors('email');
    }

    // ===== Bulk orders / representative orders (phone rule) =====

    public function test_bulk_order_phone_must_be_egyptian_mobile(): void
    {
        $response = $this->asAdmin()->post(route('bulk_orders.store'), [
            'name' => 'شركة', 'phone' => 'abc', 'company_name' => 'شركة',
            'description' => 'منتجات',
        ]);
        $response->assertSessionHasErrors('phone');

        $response = $this->asAdmin()->post(route('bulk_orders.store'), [
            'name' => 'شركة', 'phone' => '+20 101 123 4567', 'company_name' => 'شركة',
            'description' => 'منتجات',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bulk_orders', ['phone' => '+20 101 123 4567']);
    }

    public function test_representative_order_phone_must_be_egyptian_mobile(): void
    {
        $response = $this->asAdmin()->post(route('representatives_orders.store'), [
            'name' => 'مندوب', 'phone' => '12', 'description' => 'طلب',
        ]);
        $response->assertSessionHasErrors('phone');

        $response = $this->asAdmin()->post(route('representatives_orders.store'), [
            'name' => 'مندوب', 'phone' => '01512345678', 'description' => 'طلب',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('representatives_orders', ['phone' => '01512345678']);
    }

    public function test_bulk_order_edit_form_shows_phone_error_by_the_field(): void
    {
        $order = DB::table('bulk_orders')->insertGetId([
            'name' => 'شركة', 'phone' => '01012345678', 'company_name' => 'شركة',
            'description' => 'منتجات', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->asAdmin()->from(route('bulk_orders.edit', $order))
            ->followingRedirects()->put(
            route('bulk_orders.update', $order),
            ['name' => 'شركة', 'phone' => 'abc', 'company_name' => 'شركة', 'description' => 'منتجات']
        );

        $response->assertOk();
        $response->assertSee('is-invalid', false);
        $response->assertSee('name="phone"', false);
    }

    // ===== Discount codes (DiscountCodeRequest) =====

    private function validDiscountPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'خصم', 'code' => 'SAVE10', 'price' => 25,
            'status' => 'active', 'number_of_used' => 10,
            'discount_type' => 'percentage',
        ], $overrides);
    }

    public function test_discount_number_of_used_must_be_a_whole_non_negative_number(): void
    {
        $response = $this->asAdmin()->post(
            route('discount_code.store'),
            $this->validDiscountPayload(['number_of_used' => 1.5])
        );
        $response->assertSessionHasErrors('number_of_used');

        $response = $this->asAdmin()->post(
            route('discount_code.store'),
            $this->validDiscountPayload(['number_of_used' => -1])
        );
        $response->assertSessionHasErrors('number_of_used');
    }

    public function test_discount_percentage_over_100_is_rejected_but_fixed_amount_is_not(): void
    {
        $response = $this->asAdmin()->post(
            route('discount_code.store'),
            $this->validDiscountPayload(['price' => 150, 'discount_type' => 'percentage'])
        );
        $response->assertSessionHasErrors('price');

        $response = $this->asAdmin()->post(
            route('discount_code.store'),
            $this->validDiscountPayload(['price' => 500, 'discount_type' => 'price', 'code' => 'SAVE500'])
        );
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('discount_codes', ['code' => 'SAVE500']);
    }

    public function test_discount_percentage_100_is_accepted(): void
    {
        $response = $this->asAdmin()->post(
            route('discount_code.store'),
            $this->validDiscountPayload(['price' => 100])
        );
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('discount_codes', ['code' => 'SAVE10']);
    }

    public function test_discount_product_ids_must_exist(): void
    {
        $response = $this->asAdmin()->post(
            route('discount_code.store'),
            $this->validDiscountPayload(['product_ids' => [99999]])
        );
        $response->assertSessionHasErrors('product_ids.0');
    }

    // ===== Reports (ReportsController::index — GET with period filter) =====

    public function test_report_dates_must_be_dates_and_end_after_start(): void
    {
        $response = $this->asAdmin()->get(route('reports.index', [
            'period' => 'custom',
            'start_at' => 'not-a-date',
            'end_at' => '2026-01-02',
        ]));
        $response->assertSessionHasErrors('start_at');

        $response = $this->asAdmin()->get(route('reports.index', [
            'period' => 'custom',
            'start_at' => '2026-01-10',
            'end_at' => '2026-01-02',
        ]));
        $response->assertSessionHasErrors('end_at');
    }

    public function test_report_period_must_be_a_known_preset_and_custom_range_is_capped(): void
    {
        $response = $this->asAdmin()->get(route('reports.index', [
            'period' => 'bogus',
        ]));
        $response->assertSessionHasErrors('period');

        // A custom range over 92 days is rejected.
        $response = $this->asAdmin()->get(route('reports.index', [
            'period' => 'custom',
            'start_at' => '2026-01-01',
            'end_at' => '2026-04-15',
        ]));
        $response->assertSessionHasErrors('end_at');

        $response = $this->asAdmin()->get(route('reports.index', [
            'period' => 'custom',
            'start_at' => '2026-01-01',
            'end_at' => '2026-01-02',
        ]));
        $response->assertOk();
    }

    // ===== Cities (CityController) =====

    public function test_city_shipping_price_cannot_be_negative(): void
    {
        $payload = [
            'country_id' => $this->countryId,
            'name_ar' => 'الجيزة', 'name_en' => 'Giza', 'code' => 2,
            'status' => 'used', 'shipping_price' => -5,
        ];

        $response = $this->asAdmin()->post(route('cities.store'), $payload);
        $response->assertSessionHasErrors('shipping_price');

        $response = $this->asAdmin()->post(
            route('cities.store'),
            array_merge($payload, ['shipping_price' => 10])
        );
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cities', ['name_ar' => 'الجيزة']);
    }

    // ===== Products (ProductRequest) =====

    private function makeCategory(): MainCategory
    {
        return MainCategory::create(['name' => 'قسم', 'slug' => 'cat']);
    }

    private function validProductPayload(MainCategory $category, array $overrides = []): array
    {
        return array_merge([
            'name' => 'منتج اختبار',
            'price' => 100,
            'status' => 'active',
            'parent_id' => $category->id,
            'quantity' => 5,
        ], $overrides);
    }

    public function test_product_price_cannot_be_negative(): void
    {
        $category = $this->makeCategory();

        $response = $this->asAdmin()->post(
            route('products.store'),
            $this->validProductPayload($category, ['price' => -10])
        );
        $response->assertSessionHasErrors('price');
    }

    public function test_product_choice_and_color_ids_must_exist(): void
    {
        $category = $this->makeCategory();

        $response = $this->asAdmin()->post(
            route('products.store'),
            $this->validProductPayload($category, ['choice_id' => [99999]])
        );
        $response->assertSessionHasErrors('choice_id.0');

        $response = $this->asAdmin()->post(
            route('products.store'),
            $this->validProductPayload($category, ['colors' => [99999]])
        );
        $response->assertSessionHasErrors('colors.0');
    }

    public function test_product_store_succeeds_with_valid_choice_and_color(): void
    {
        $category = $this->makeCategory();
        $choice = Choice::forceCreate(['name' => 'لون']);
        $color = Color::forceCreate(['name' => 'أحمر', 'color_code' => '#ff0000']);

        $response = $this->asAdmin()->post(
            route('products.store'),
            $this->validProductPayload($category, [
                'choice_id' => [$choice->id],
                'colors' => [$color->id],
            ])
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['name' => 'منتج اختبار']);
    }

    // ===== Main categories (MainCategoryRequest) =====

    public function test_category_choice_ids_must_exist(): void
    {
        $response = $this->asAdmin()->post(route('main_categories.store'), [
            'name' => 'قسم جديد', 'type' => 'main', 'choice_id' => [99999],
        ]);
        $response->assertSessionHasErrors('choice_id.0');

        $response = $this->asAdmin()->post(route('main_categories.store'), [
            'name' => 'قسم جديد', 'type' => 'main',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('main_categories', ['name' => 'قسم جديد']);
    }

    // ===== Countries (CountryRequest) =====

    public function test_country_city_ids_must_exist(): void
    {
        $response = $this->asAdmin()->post(route('countries.store'), [
            'country_id' => $this->countryId, 'city' => [99999],
        ]);
        $response->assertSessionHasErrors('city.0');

        $response = $this->asAdmin()->post(route('countries.store'), [
            'country_id' => $this->countryId, 'city' => [$this->cityId],
        ]);
        $response->assertSessionHasNoErrors();
    }

    // ===== Currencies (CurrencygRequest) =====

    public function test_currency_id_is_required(): void
    {
        $response = $this->asAdmin()->post(route('currencies.store'), [
            'price_in_default_currency' => 3.75,
        ]);
        $response->assertSessionHasErrors('currency_id');

        $currency = Currency::forceCreate([
            'name' => 'US Dollar', 'code' => 'USD', 'status' => 'not_used',
        ]);

        $response = $this->asAdmin()->post(route('currencies.store'), [
            'price_in_default_currency' => 3.75,
            'currency_id' => $currency->id,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertSame('used', $currency->fresh()->status);
    }

    // ===== Settings (SettingRequest) =====

    private function makeSetting(): Setting
    {
        return Setting::forceCreate(['id' => 1, 'website_name' => 'Test Store']);
    }

    private function updateSettings(array $overrides): \Illuminate\Testing\TestResponse
    {
        return $this->asAdmin()->put(
            route('settings.update', 1),
            array_merge(['website_name' => 'Test Store'], $overrides)
        );
    }

    public function test_settings_phone_must_match_canonical_rule(): void
    {
        $this->makeSetting();

        $response = $this->updateSettings(['phone_number' => 'abc']);
        $response->assertSessionHasErrors('phone_number');

        $response = $this->updateSettings(['phone_number' => '+966555123456']);
        $response->assertSessionHasNoErrors();
        $this->assertSame('+966555123456', Setting::first()->phone_number);
    }

    public function test_settings_vat_is_capped_at_100(): void
    {
        $this->makeSetting();

        $response = $this->updateSettings(['value_added_tax' => 150]);
        $response->assertSessionHasErrors('value_added_tax');

        $response = $this->updateSettings(['value_added_tax' => 15]);
        $response->assertSessionHasNoErrors();
        $this->assertEquals(15, (float) Setting::first()->value_added_tax);
    }

    public function test_settings_social_and_store_links_must_be_urls(): void
    {
        $this->makeSetting();

        $response = $this->updateSettings(['facebook' => 'not-a-url']);
        $response->assertSessionHasErrors('facebook');

        $response = $this->updateSettings(['google_play' => 'not-a-url']);
        $response->assertSessionHasErrors('google_play');

        $response = $this->updateSettings([
            'facebook' => 'https://facebook.com/test',
            'google_play' => 'https://play.google.com/store/apps/details?id=test',
        ]);
        $response->assertSessionHasNoErrors();
    }

    public function test_settings_form_shows_phone_error_by_the_field(): void
    {
        $this->makeSetting();

        $response = $this->asAdmin()->from(route('front_settings'))
            ->followingRedirects()->put(
            route('settings.update', 1),
            ['website_name' => 'Test Store', 'phone_number' => 'abc']
        );

        $response->assertOk();
        $response->assertSee('is-invalid', false);
        $response->assertSee('name="phone_number"', false);
    }

    // ===== Shipping (ShippingController) =====

    public function test_shipping_per_city_price_must_be_numeric_and_non_negative(): void
    {
        ShippingTypesAndPrice::forceCreate([
            'id' => 1, 'add_pickup_from_store' => 1,
            'add_normal_price' => 1, 'normal_shipping_price' => 25,
        ]);

        $payload = [
            'weight_price' => 5, 'normal_shipping_price' => 25,
            'price' => [$this->cityId => -5],
        ];

        $response = $this->asAdmin()->put(route('shipping_data.update', 1), $payload);
        $response->assertSessionHasErrors('price.' . $this->cityId);

        $payload['price'] = [$this->cityId => 15];
        $response = $this->asAdmin()->put(route('shipping_data.update', 1), $payload);
        $response->assertSessionHasNoErrors();
        $this->assertEquals(15, (float) DB::table('cities')->where('id', $this->cityId)->value('shipping_price'));
    }

    // ===== Header banners (HeaderBanerController) =====

    public function test_header_banner_link_changes_are_validated(): void
    {
        $banner = HeaderBanner::forceCreate([
            'header_image' => 'banners/a.jpg',
            'image_link' => 'https://example.com',
        ]);

        $response = $this->asAdmin()->post(route('header_banner.sotreAndUpdate'), [
            'change_image_link' => [$banner->id => str_repeat('x', 1001)],
        ]);
        $response->assertSessionHasErrors('change_image_link.' . $banner->id);

        $response = $this->asAdmin()->post(route('header_banner.sotreAndUpdate'), [
            'change_image_link' => [$banner->id => 'https://example.com/new'],
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertSame('https://example.com/new', $banner->fresh()->image_link);
    }

    // ===== Admin groups / rules (RulesController) =====

    public function test_rule_ability_values_must_be_allow(): void
    {
        $response = $this->asAdmin()->post(route('rules.store'), [
            'name' => 'مجموعة', 'abilities' => ['product.view' => 'hack'],
        ]);
        $response->assertSessionHasErrors('abilities.product.view');

        $response = $this->asAdmin()->post(route('rules.store'), [
            'name' => 'مجموعة', 'abilities' => ['product.view' => 'allow'],
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rules', ['name' => 'مجموعة']);
        $this->assertDatabaseHas('rule_abilities', ['ability' => 'product.view', 'type' => 'allow']);
    }

    // ===== Store features (StoreFatuerController) =====

    public function test_store_feature_update_rejects_svg_image(): void
    {
        Storage::fake('public');
        $feature = StoreFatuer::create([
            'title' => 'ميزة', 'description' => 'وصف', 'image' => 'old.jpg',
        ]);

        $response = $this->asAdmin()->put(route('store_featuers.update', $feature), [
            'title' => 'ميزة',
            'description' => 'وصف',
            'image' => UploadedFile::fake()->create('evil.svg', 100, 'image/svg+xml'),
        ]);
        $response->assertSessionHasErrors('image');

        $response = $this->asAdmin()->put(route('store_featuers.update', $feature), [
            'title' => 'ميزة',
            'description' => 'وصف',
            'image' => UploadedFile::fake()->image('feature.jpg'),
        ]);
        $response->assertSessionHasNoErrors();
    }

    // ===== Orders (OrderController) =====

    public function test_order_status_must_be_a_valid_status(): void
    {
        $order = Order::forceCreate(['status' => 'pending', 'payment_status' => 'unpaid']);

        $response = $this->asAdmin()->put(
            route('orders.update', $order),
            ['status' => 'bogus']
        );
        $response->assertSessionHasErrors('status');
    }

    // ===== Static pages (StaticPage request) =====

    public function test_static_page_edit_form_shows_title_error_by_the_field(): void
    {
        $page = Page::create(['title' => 'الشروط', 'content' => 'محتوى']);

        $response = $this->asAdmin()->from(route('pages.edit', $page))
            ->followingRedirects()->put(
            route('pages.update', $page),
            ['title' => '', 'content' => 'محتوى']
        );

        $response->assertOk();
        $response->assertSee('is-invalid', false);
        $response->assertSee('name="title"', false);
    }

    // ===== Advertisements (AdvertisementRequest) =====

    public function test_advertisement_english_title_must_be_a_string(): void
    {
        // The rule used to be written as ' nullable|...' (leading
        // space), so the string rule never applied to title_en.
        $response = $this->asAdmin()->post(route('advertisements.store'), [
            'title' => 'عرض', 'title_en' => ['not', 'a', 'string'],
        ]);
        $response->assertSessionHasErrors('title_en');

        $response = $this->asAdmin()->post(route('advertisements.store'), [
            'title' => 'عرض', 'title_en' => 'Offer',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('advertisements', ['title' => 'عرض']);
    }
}
