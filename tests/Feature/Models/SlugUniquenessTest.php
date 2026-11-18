<?php

namespace Tests\Feature\Models;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SlugUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_product_names_get_unique_slugs(): void
    {
        $first = Product::factory()->create(['name' => 'Test Product']);
        $second = Product::factory()->create(['name' => 'Test Product']);

        $this->assertSame('Test-Product', $first->slug);
        $this->assertSame('Test-Product-2', $second->slug);
    }

    public function test_orders_status_column_is_a_real_enum(): void
    {
        $row = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::getDatabaseName(), 'orders', 'status']
        );

        $this->assertStringStartsWith('enum(', $row->COLUMN_TYPE);
        $this->assertStringContainsString("'delivered'", $row->COLUMN_TYPE);
    }
}
