<?php

namespace Tests\Feature\Seeders;

use App\Models\Admin;
use App\Models\Advertisement;
use App\Models\Cart;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Design;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingTypesAndPrice;
use App\Models\User;
use App\Models\User_verfication;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CountryCitySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionSeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('ADMIN_EMAIL');
        putenv('ADMIN_PASSWORD');

        parent::tearDown();
    }

    public function test_country_city_seeder_creates_egypt_with_governorates(): void
    {
        $this->seed(CountryCitySeeder::class);

        $egypt = Country::where('code', 'EG')->first();
        $this->assertNotNull($egypt);
        $this->assertSame('مصر', $egypt->name_ar);
        $this->assertSame('+20', $egypt->phone_code);

        $this->assertTrue(City::where('country_id', $egypt->id)->where('name_en', 'Cairo')->exists());
        $this->assertTrue(City::where('country_id', $egypt->id)->where('name_en', 'Giza')->exists());
        $this->assertTrue(City::where('country_id', $egypt->id)->where('name_en', 'Alexandria')->exists());
        $this->assertGreaterThanOrEqual(20, City::where('country_id', $egypt->id)->count());

        // Gulf countries are kept.
        $this->assertTrue(Country::where('code', 'SA')->exists());
    }

    public function test_currency_seeder_creates_egp_and_keeps_sar_default(): void
    {
        $this->seed(CurrencySeeder::class);

        $sar = Currency::where('code', 'SAR')->first();
        $this->assertNotNull($sar);
        $this->assertTrue((bool) $sar->default_currency);

        $egp = Currency::where('code', 'EGP')->first();
        $this->assertNotNull($egp);
        $this->assertSame('جنيه مصري', $egp->name_ar);
        $this->assertFalse((bool) $egp->default_currency);
        $this->assertSame(1, Currency::where('default_currency', true)->count());
    }

    public function test_database_seeder_in_non_local_env_creates_core_data_but_no_demo_data(): void
    {
        // phpunit.xml sets APP_ENV=testing, so the local-only demo
        // seeders must not run.
        $this->assertNotSame('local', app()->environment());

        $this->seed(DatabaseSeeder::class);

        // Core data exists.
        $this->assertTrue(Country::where('code', 'EG')->exists());
        $this->assertTrue(Currency::where('code', 'EGP')->exists());
        $this->assertNotNull(ShippingTypesAndPrice::find(1));
        $setting = Setting::find(1);
        $this->assertNotNull($setting);
        $this->assertSame('دبّرهالي', $setting->website_name);
        $this->assertNull($setting->email);
        $this->assertNull($setting->phone);
        $this->assertNull($setting->address);

        // No demo data: no fake products, ads or designs, no test customer.
        $this->assertSame(0, Product::count());
        $this->assertSame(0, Advertisement::count());
        $this->assertSame(0, Design::count());
        $this->assertFalse(User::where('email', 'user@user.com')->exists());

        // No known admin credentials.
        $this->assertFalse(Admin::where('email', 'admin@admin.com')->exists());
        $legacy = Admin::where('email', 'admin@admin.com')->first();
        $this->assertNull($legacy);
    }

    public function test_admin_seeder_with_env_credentials_uses_them(): void
    {
        putenv('ADMIN_EMAIL=owner@example.com');
        putenv('ADMIN_PASSWORD=MySecretPass123!');

        $this->seed(AdminSeeder::class);

        $admin = Admin::where('email', 'owner@example.com')->first();
        $this->assertNotNull($admin);
        $this->assertTrue((bool) $admin->super_admin);
        $this->assertTrue(Hash::check('MySecretPass123!', $admin->password));
        $this->assertFalse(Admin::where('email', 'admin@admin.com')->exists());
    }

    public function test_admin_seeder_without_env_credentials_never_uses_known_password(): void
    {
        $this->seed(AdminSeeder::class);

        $this->assertFalse(Admin::where('email', 'admin@admin.com')->exists());

        $admin = Admin::first();
        $this->assertNotNull($admin);
        $this->assertFalse(Hash::check('12345678', $admin->password));
    }

    public function test_prune_expired_data_command_deletes_expired_codes_and_old_carts(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $expired = User_verfication::create([
            'user_id' => $user->id,
            'code' => '1111',
            'verification_code_expires_at' => now()->subHour(),
        ]);
        $fresh = User_verfication::create([
            'user_id' => $user->id,
            'code' => '2222',
            'verification_code_expires_at' => now()->addHour(),
        ]);

        $product = Product::factory()->create();
        $oldCart = Cart::create([
            'cookie_id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $oldCart->timestamps = false;
        $oldCart->updated_at = now()->subDays(40);
        $oldCart->save();

        $recentCart = Cart::create([
            'cookie_id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->artisan('app:prune-expired-data')->assertSuccessful();

        $this->assertNull(User_verfication::find($expired->id));
        $this->assertNotNull(User_verfication::find($fresh->id));
        // NB: use the query builder for carts — the Cart model has a
        // global `cookie_id` scope that hides every cart except the
        // current guest's, so Cart::find() cannot see these rows at all.
        $this->assertFalse(DB::table('carts')->where('id', $oldCart->id)->exists());
        $this->assertTrue(DB::table('carts')->where('id', $recentCart->id)->exists());
    }

    public function test_up_health_endpoint_returns_200_json(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }
}
