<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Reports rebuild — tests written against the rebuild contract
 * (GET /admin/reports, route `reports.index`, Gate `report.create`).
 *
 * View variables asserted here: $period (['preset','start','end']),
 * $kpis (revenue, orders_count, avg_order_value, new_customers),
 * $trend (labels/revenue/orders), $statusBreakdown (list of
 * ['status','label','count'] rows), $paymentSplit
 * (['cod'=>['count'=>..],'card'=>['count'=>..],'paid'=>..,'unpaid'=>..]),
 * $bestSellers (rows: product_id, product_name, quantity_sold, revenue),
 * $lowStock (+$stockThreshold), $topCustomers (rows: user_id, name,
 * email, phone, orders_count, total_spent, last_order_at), $returns
 * (GROUPED BY PRODUCT: rows product_id, product_name,
 * quantity_returned — not a list of orders), $returnRate,
 * $returnedOrders, $totalOrders, $coupons (rows: id, code, name,
 * times_used, discount_given, revenue), $activeTab ('best_sellers'
 * by default, ?tab=... to switch). Paginators use the page names
 * best_page, stock_page, customers_page, returns_page, coupons_page
 * (15 per page). CSV export via ?export=<section> returns a streamed
 * text/csv download.
 *
 * Revenue definition under test: SUM(total_price) over orders with
 * payment_status = 'paid', status != 'cancelled', return_order = false,
 * inside the requested period. Period presets are inclusive:
 * 30d = today + the 29 days before it (30 trend labels).
 *
 * NOTE: these tests were adapted to the backend implementation as
 * actually written (ReportsController::index + ReportsRepository,
 * uncommitted working-tree changes when this file was written), not
 * just to the brief — where a shape differed from the brief (notably
 * $period is an array, $paymentSplit nests cod/card counts, $returns
 * is grouped by product), the code wins. overview() and dailyTrend()
 * are cached for 600s under period-based keys, so setUp() flushes the
 * cache between tests.
 */
class ReportsRebuildTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Defensive: if the reports implementation caches per-period
        // results, a stale entry must never leak from one test into the
        // next (phpunit.xml uses the array cache driver).
        Cache::flush();
    }

    private ?Admin $superAdmin = null;

    private function makeSuperAdmin(): Admin
    {
        // forceCreate: super_admin is not in Admin::$fillable, and the
        // Gate::before bypass in AuthServiceProvider depends on it.
        // Memoized: admins.email is unique, so a test that hits the
        // reports page twice must reuse the same admin row.
        return $this->superAdmin ??= Admin::forceCreate([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'super_admin' => true,
        ]);
    }

    private function makeLimitedAdmin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Limited Admin',
            'email' => 'limited@example.com',
            'password' => 'password',
            'super_admin' => false,
        ]);
    }

    private function makeUser(): User
    {
        // phpunit.xml sets BCRYPT_ROUNDS=4; pass the password explicitly
        // like the other feature tests do.
        return User::factory()->create(['password' => 'password']);
    }

    /**
     * Create an order directly (no checkout/carts/shipping setup needed).
     * The Order `creating` hook auto-generates the order number.
     * $at backdates created_at/updated_at AFTER creation: created_at is
     * not in Order::$fillable, so passing it to ::create would be ignored.
     */
    private function makeOrder(array $attributes = [], ?Carbon $at = null): Order
    {
        $order = Order::create(array_merge([
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'paid',
            'status' => OrderStatus::Delivered->value,
            'total_price' => 100,
            'shipping_price' => 0,
            'tax_amount' => 0,
        ], $attributes));

        if ($at !== null) {
            $order->created_at = $at;
            $order->updated_at = $at;
            $order->save();
        }

        return $order->fresh();
    }

    private function makeOrderItem(Order $order, Product $product, float $price, int $quantity = 1): OrderItem
    {
        // OrderItem extends Pivot, which has $guarded = [], so ::create
        // with these fields works (same pattern as the Order tests).
        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $price,
            'quantity' => $quantity,
        ]);
    }

    private function makeDiscountCode(string $code): DiscountCode
    {
        // discount_codes.status is ENUM('active','inactive') since
        // migration 2026_04_01_190146 — a boolean will not work.
        return DiscountCode::create([
            'name' => 'Test code '.$code,
            'code' => $code,
            'price' => 0,
            'status' => 'active',
            'discount_type' => 'fixed',
            'number_of_used' => 0,
        ]);
    }

    private function getReports(array $params = [])
    {
        return $this->actingAs($this->makeSuperAdmin(), 'admin')
            ->get(route('reports.index', $params));
    }

    /** Read a view variable, failing loudly if the view did not share it. */
    private function viewVar($response, string $key): mixed
    {
        $response->assertOk();
        $value = $response->viewData($key);
        $this->assertNotNull($value, "Expected the reports view to share \${$key}.");

        return $value;
    }

    /** Plain-array normalisation for arrays, Collections and Arrayables. */
    private function toPlainArray(mixed $data): ?array
    {
        if ($data instanceof \Illuminate\Contracts\Support\Arrayable) {
            return $data->toArray();
        }
        if ($data instanceof \Traversable) {
            return iterator_to_array($data);
        }
        if (is_object($data)) {
            return get_object_vars($data);
        }
        if (is_array($data)) {
            return $data;
        }

        return null;
    }

    /**
     * Read a value from an array row, stdClass row or Eloquent model by
     * trying several possible key names (the contract does not pin down
     * every nested key, so the test accepts the natural aliases).
     */
    private function rowValue(mixed $row, array $keys, mixed $default = null): mixed
    {
        if ($row instanceof \Illuminate\Contracts\Support\Arrayable) {
            $row = $row->toArray();
        }
        if (is_array($row)) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $row) && $row[$key] !== null) {
                    return $row[$key];
                }
            }

            return $default;
        }
        if (is_object($row)) {
            foreach ($keys as $key) {
                if (isset($row->{$key})) {
                    return $row->{$key};
                }
            }
        }

        return $default;
    }

    /**
     * Find a numeric count in flat assoc data (['cod' => 1]), nested
     * assoc data (['by_method' => ['cod' => 1]]) or a list of rows
     * (['status' => 'delivered', 'count' => 3]). Returns null when the
     * key is absent, so the caller can fail with a clear message.
     */
    private function findCount(mixed $data, array $aliases): ?float
    {
        $data = $this->toPlainArray($data);
        if ($data === null) {
            return null;
        }

        $aliases = array_map('strtolower', $aliases);

        foreach ($data as $key => $value) {
            if (! is_string($key) || ! in_array(strtolower($key), $aliases, true)) {
                continue;
            }
            if (is_numeric($value)) {
                return (float) $value;
            }
            // Nested shape: ['cod' => ['count' => 1]] (paymentSplit).
            if (is_array($value)) {
                $nested = $this->findCount($value, ['count', 'value', 'total']);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        foreach ($data as $value) {
            if (! is_array($value)) {
                continue;
            }
            $label = null;
            $count = null;
            foreach ($value as $k => $v) {
                if (is_string($v) && in_array(strtolower($v), $aliases, true)) {
                    $label = $v;
                }
                if (is_numeric($v) && in_array(strtolower((string) $k), ['count', 'value', 'total', 'orders', 'orders_count'], true)) {
                    $count = (float) $v;
                }
            }
            if ($label !== null && $count !== null) {
                return $count;
            }
            $found = $this->findCount($value, $aliases);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** Normalise $trend to ['labels'=>[], 'revenue'=>[], 'orders'=>[]]. */
    private function trendSeries(mixed $trend): array
    {
        $trend = $this->toPlainArray($trend) ?? [];

        if (isset($trend['labels'], $trend['revenue'])) {
            return [
                'labels' => array_values((array) $trend['labels']),
                'revenue' => array_values(array_map('floatval', (array) $trend['revenue'])),
                'orders' => array_values(array_map('intval', (array) ($trend['orders'] ?? []))),
            ];
        }

        // List-of-rows shape: ['date' => ..., 'revenue' => ..., 'orders' => ...].
        $labels = [];
        $revenue = [];
        $orders = [];
        foreach ($trend as $row) {
            if (! is_array($row)) {
                continue;
            }
            $labels[] = $row['date'] ?? $row['label'] ?? $row['day'] ?? '';
            $revenue[] = (float) ($row['revenue'] ?? $row['total_revenue'] ?? 0);
            $orders[] = (int) ($row['orders'] ?? $row['orders_count'] ?? $row['count'] ?? 0);
        }

        return compact('labels', 'revenue', 'orders');
    }

    private function pageItems(mixed $page): array
    {
        if ($page instanceof \Illuminate\Contracts\Pagination\Paginator) {
            return $page->items();
        }
        if ($page instanceof \Illuminate\Support\Collection) {
            return $page->all();
        }
        if (is_array($page)) {
            return $page;
        }

        return [];
    }

    // ------------------------------------------------------------------
    // 1. Revenue math
    // ------------------------------------------------------------------

    public function test_revenue_counts_only_paid_non_cancelled_non_returned_orders(): void
    {
        $user = $this->makeUser();

        // In scope: two paid, delivered, non-returned orders.
        $this->makeOrder(['user_id' => $user->id, 'total_price' => 100]);
        $this->makeOrder(['user_id' => $user->id, 'total_price' => 200]);
        // Out of scope: unpaid, cancelled-paid, returned-paid.
        $this->makeOrder(['user_id' => $user->id, 'payment_status' => 'unpaid', 'status' => 'pending', 'total_price' => 999]);
        $this->makeOrder(['user_id' => $user->id, 'status' => 'cancelled', 'total_price' => 888]);
        $this->makeOrder(['user_id' => $user->id, 'return_order' => true, 'total_price' => 777]);

        $response = $this->getReports();

        // $period is ['preset','start','end']; 30d = today + 29 days.
        $period = $this->viewVar($response, 'period');
        $this->assertIsArray($period);
        $this->assertSame('30d', $period['preset'], 'Default period preset is 30d.');
        $this->assertSame(Carbon::now()->subDays(29)->toDateString(), $period['start']);
        $this->assertSame(Carbon::now()->toDateString(), $period['end']);

        $kpis = $this->viewVar($response, 'kpis');
        $this->assertIsArray($kpis);
        $this->assertEqualsWithDelta(300.0, (float) $kpis['revenue'], 0.01, 'Revenue must be only the paid, non-cancelled, non-returned sum (100 + 200).');
        $this->assertSame(2, (int) $kpis['orders_count'], 'orders_count must count only the in-scope orders.');
        $this->assertEqualsWithDelta(150.0, (float) $kpis['avg_order_value'], 0.01, 'avg_order_value must be revenue / orders_count (300 / 2).');
        $this->assertSame(1, (int) $kpis['new_customers'], 'Exactly one customer was created in the period.');

        // Status breakdown covers all orders in the period (not just the
        // paid scope): 3 delivered, 1 pending, 1 cancelled.
        $breakdown = $this->viewVar($response, 'statusBreakdown');
        $this->assertEqualsWithDelta(3.0, $this->findCount($breakdown, ['delivered']), 0.0, 'Status breakdown must show 3 delivered orders.');
        $this->assertEqualsWithDelta(1.0, $this->findCount($breakdown, ['cancelled']), 0.0, 'Status breakdown must show 1 cancelled order.');
        $this->assertEqualsWithDelta(1.0, $this->findCount($breakdown, ['pending']), 0.0, 'Status breakdown must show 1 pending order.');

        $response->assertViewHas('activeTab');
        $this->assertSame('best_sellers', $this->viewVar($response, 'activeTab'), 'Default tab is best_sellers.');
    }

    public function test_tab_param_sets_active_tab(): void
    {
        $response = $this->getReports(['tab' => 'coupons']);

        $this->assertSame('coupons', $this->viewVar($response, 'activeTab'));
    }

    public function test_stock_threshold_param_overrides_default(): void
    {
        $three = Product::factory()->create(['name' => 'Three Stock', 'quantity' => 3]);
        $five = Product::factory()->create(['name' => 'Five Stock', 'quantity' => 5]);

        $response = $this->getReports(['stock_threshold' => 3]);

        $this->assertSame(3, (int) $this->viewVar($response, 'stockThreshold'));
        $lowStock = $this->viewVar($response, 'lowStock');
        $ids = array_map(
            fn ($row) => (int) $this->rowValue($row, ['id']),
            $this->pageItems($lowStock)
        );
        $this->assertContains($three->id, $ids);
        $this->assertNotContains($five->id, $ids, 'With threshold 3, quantity 5 is no longer low stock.');
    }

    // ------------------------------------------------------------------
    // 2. Date filter — 30d window
    // ------------------------------------------------------------------

    public function test_date_filter_excludes_orders_outside_the_period(): void
    {
        $user = $this->makeUser();

        $this->makeOrder(['user_id' => $user->id, 'total_price' => 100]); // today
        $this->makeOrder(
            ['user_id' => $user->id, 'total_price' => 999],
            Carbon::now()->subDays(60)->setTime(12, 0)
        );

        $response = $this->getReports(['period' => '30d']);

        $kpis = $this->viewVar($response, 'kpis');
        $this->assertEqualsWithDelta(100.0, (float) $kpis['revenue'], 0.01, 'A paid order from 60 days ago must not change the 30-day revenue.');
        $this->assertSame(1, (int) $kpis['orders_count']);
    }

    // ------------------------------------------------------------------
    // 2b. Date filter — today
    // ------------------------------------------------------------------

    public function test_today_period_counts_only_todays_orders(): void
    {
        $user = $this->makeUser();

        $this->makeOrder(['user_id' => $user->id, 'total_price' => 100]); // today
        $this->makeOrder(
            ['user_id' => $user->id, 'total_price' => 50],
            Carbon::yesterday()->setTime(12, 0)
        );

        $response = $this->getReports(['period' => 'today']);

        $period = $this->viewVar($response, 'period');
        $this->assertSame('today', $period['preset']);
        $this->assertSame(Carbon::now()->toDateString(), $period['start']);
        $this->assertSame(Carbon::now()->toDateString(), $period['end']);

        $kpis = $this->viewVar($response, 'kpis');
        $this->assertEqualsWithDelta(100.0, (float) $kpis['revenue'], 0.01, 'period=today must exclude yesterday\'s order.');
        $this->assertSame(1, (int) $kpis['orders_count']);
    }

    // ------------------------------------------------------------------
    // 2c. Custom period
    // ------------------------------------------------------------------

    public function test_custom_period_filters_by_start_and_end_dates(): void
    {
        $user = $this->makeUser();

        $this->makeOrder(
            ['user_id' => $user->id, 'total_price' => 100],
            Carbon::now()->subDays(10)->setTime(12, 0)
        );
        $this->makeOrder(
            ['user_id' => $user->id, 'total_price' => 200],
            Carbon::now()->subDays(5)->setTime(12, 0)
        );

        $response = $this->getReports([
            'period' => 'custom',
            'start_at' => Carbon::now()->subDays(6)->toDateString(),
            'end_at' => Carbon::now()->toDateString(),
        ]);

        $period = $this->viewVar($response, 'period');
        $this->assertSame('custom', $period['preset']);
        $this->assertSame(Carbon::now()->subDays(6)->toDateString(), $period['start']);
        $this->assertSame(Carbon::now()->toDateString(), $period['end']);

        $kpis = $this->viewVar($response, 'kpis');
        $this->assertEqualsWithDelta(200.0, (float) $kpis['revenue'], 0.01, 'Only the order inside the custom window (5 days ago) counts.');
        $this->assertSame(1, (int) $kpis['orders_count']);
    }

    // ------------------------------------------------------------------
    // 3. Best sellers ordering
    // ------------------------------------------------------------------

    public function test_best_sellers_are_ordered_by_revenue_descending(): void
    {
        $user = $this->makeUser();
        $lowProduct = Product::factory()->create(['name' => 'Low Seller', 'price' => 10, 'quantity' => 50]);
        $highProduct = Product::factory()->create(['name' => 'High Seller', 'price' => 100, 'quantity' => 50]);

        $lowOrder = $this->makeOrder(['user_id' => $user->id, 'total_price' => 10]);
        $this->makeOrderItem($lowOrder, $lowProduct, 10, 1); // revenue 10

        $highOrder = $this->makeOrder(['user_id' => $user->id, 'total_price' => 200]);
        $this->makeOrderItem($highOrder, $highProduct, 100, 2); // revenue 200

        $response = $this->getReports();

        $bestSellers = $this->viewVar($response, 'bestSellers');
        $this->assertInstanceOf(LengthAwarePaginator::class, $bestSellers);
        $items = $this->pageItems($bestSellers);
        $this->assertCount(2, $items);

        $first = $items[0];
        $firstProductId = $this->rowValue($first, ['product_id']);
        if ($firstProductId === null) {
            $product = $this->rowValue($first, ['product']);
            $firstProductId = is_array($product) ? ($product['id'] ?? null) : ($product->id ?? null);
        }
        $this->assertSame($highProduct->id, (int) $firstProductId, 'The higher-revenue product must be first.');
        $this->assertEqualsWithDelta(
            200.0,
            (float) $this->rowValue($first, ['total_revenue', 'revenue', 'total', 'total_sales']),
            0.01,
            'First best seller revenue must be SUM(price * quantity) = 100 * 2.'
        );
    }

    // ------------------------------------------------------------------
    // 4. Low stock
    // ------------------------------------------------------------------

    public function test_low_stock_lists_products_at_or_below_threshold(): void
    {
        $zero = Product::factory()->create(['name' => 'Zero Stock', 'quantity' => 0]);
        $three = Product::factory()->create(['name' => 'Three Stock', 'quantity' => 3]);
        $five = Product::factory()->create(['name' => 'Five Stock', 'quantity' => 5]);
        $plenty = Product::factory()->create(['name' => 'Plenty Stock', 'quantity' => 50]);

        $response = $this->getReports();

        $this->assertSame(5, (int) $this->viewVar($response, 'stockThreshold'), 'Default low-stock threshold is 5.');

        $lowStock = $this->viewVar($response, 'lowStock');
        $this->assertInstanceOf(LengthAwarePaginator::class, $lowStock);
        $ids = array_map(
            fn ($row) => (int) $this->rowValue($row, ['id']),
            $this->pageItems($lowStock)
        );

        $this->assertContains($zero->id, $ids, 'Quantity 0 must be listed as low stock.');
        $this->assertContains($three->id, $ids, 'Quantity 3 must be listed as low stock.');
        $this->assertContains($five->id, $ids, 'Quantity 5 (equal to the threshold) must be listed as low stock.');
        $this->assertNotContains($plenty->id, $ids, 'Quantity 50 must not be listed as low stock.');

        // The zero-stock product must be identifiable as out of stock.
        $zeroRow = collect($this->pageItems($lowStock))
            ->first(fn ($row) => (int) $this->rowValue($row, ['id']) === $zero->id);
        $this->assertNotNull($zeroRow);
        $this->assertSame(0, (int) $this->rowValue($zeroRow, ['quantity']));
    }

    // ------------------------------------------------------------------
    // 5. Top customers
    // ------------------------------------------------------------------

    public function test_top_customers_are_ordered_by_total_spent(): void
    {
        $bigSpender = $this->makeUser();
        $smallSpender = $this->makeUser();

        $this->makeOrder(['user_id' => $bigSpender->id, 'total_price' => 500]);
        $this->makeOrder(['user_id' => $bigSpender->id, 'total_price' => 300]);
        $this->makeOrder(['user_id' => $smallSpender->id, 'total_price' => 100]);

        $response = $this->getReports();

        $topCustomers = $this->viewVar($response, 'topCustomers');
        $this->assertInstanceOf(LengthAwarePaginator::class, $topCustomers);
        $items = $this->pageItems($topCustomers);
        $this->assertCount(2, $items);

        $first = $items[0];
        $firstUserId = $this->rowValue($first, ['user_id', 'customer_id', 'id']);
        $this->assertSame($bigSpender->id, (int) $firstUserId, 'The biggest spender must be first.');
        $this->assertEqualsWithDelta(
            800.0,
            (float) $this->rowValue($first, ['total_spent', 'total', 'revenue', 'total_revenue']),
            0.01,
            'First customer total_spent must be 500 + 300.'
        );
        $this->assertSame(2, (int) $this->rowValue($first, ['orders_count', 'orders', 'count']), 'First customer orders_count must be 2.');

        $second = $items[1];
        $this->assertSame($smallSpender->id, (int) $this->rowValue($second, ['user_id', 'customer_id', 'id']));
        $this->assertEqualsWithDelta(100.0, (float) $this->rowValue($second, ['total_spent', 'total', 'revenue', 'total_revenue']), 0.01);
        $this->assertSame(1, (int) $this->rowValue($second, ['orders_count', 'orders', 'count']));
    }

    // ------------------------------------------------------------------
    // 6. Return rate
    // ------------------------------------------------------------------

    public function test_return_rate_is_returned_over_non_cancelled_orders(): void
    {
        $user = $this->makeUser();
        $returnedProduct = Product::factory()->create(['name' => 'Returned Product', 'price' => 50, 'quantity' => 50]);
        $keptProduct = Product::factory()->create(['name' => 'Kept Product', 'price' => 50, 'quantity' => 50]);

        $returned = $this->makeOrder(['user_id' => $user->id, 'total_price' => 100, 'return_order' => true]);
        $this->makeOrderItem($returned, $returnedProduct, 50, 2);
        $this->makeOrder(['user_id' => $user->id, 'total_price' => 100]);
        $this->makeOrder(['user_id' => $user->id, 'total_price' => 100]);
        $kept = $this->makeOrder(['user_id' => $user->id, 'total_price' => 100]);
        $this->makeOrderItem($kept, $keptProduct, 50, 1);
        // Cancelled orders are outside the denominator.
        $this->makeOrder(['user_id' => $user->id, 'status' => 'cancelled', 'total_price' => 100]);

        $response = $this->getReports();

        $this->assertEqualsWithDelta(0.25, (float) $this->viewVar($response, 'returnRate'), 0.001, 'Return rate must be 1 returned / 4 non-cancelled.');
        $this->assertSame(1, (int) $this->viewVar($response, 'returnedOrders'));
        $this->assertSame(4, (int) $this->viewVar($response, 'totalOrders'));

        // $returns is GROUPED BY PRODUCT (rows: product_id, product_name,
        // quantity_returned) — it is not a list of orders.
        $returns = $this->viewVar($response, 'returns');
        $this->assertInstanceOf(LengthAwarePaginator::class, $returns);
        $returnRows = $this->pageItems($returns);
        $this->assertCount(1, $returnRows, 'The returns list must contain one product group (the returned product).');
        $this->assertSame(1, $returns->total());
        $this->assertSame($returnedProduct->id, (int) $this->rowValue($returnRows[0], ['product_id']));
        $this->assertSame(2, (int) $this->rowValue($returnRows[0], ['quantity_returned']), '2 units of the returned product were returned.');
    }

    // ------------------------------------------------------------------
    // 7. Coupons
    // ------------------------------------------------------------------

    public function test_coupons_report_times_used_and_discount_given(): void
    {
        $user = $this->makeUser();
        $used = $this->makeDiscountCode('USED10');
        $unused = $this->makeDiscountCode('NEVERUSED');

        // discount = totalBeforeDiscount - (total_price - shipping - tax)
        // Order 1: 200 - (170 - 10 - 0) = 40
        $this->makeOrder([
            'user_id' => $user->id,
            'discount_code_id' => $used->id,
            'totalBeforeDiscount' => 200,
            'total_price' => 170,
            'shipping_price' => 10,
            'tax_amount' => 0,
        ]);
        // Order 2: 300 - (250 - 20 - 10) = 80
        $this->makeOrder([
            'user_id' => $user->id,
            'discount_code_id' => $used->id,
            'totalBeforeDiscount' => 300,
            'total_price' => 250,
            'shipping_price' => 20,
            'tax_amount' => 10,
        ]);

        $response = $this->getReports();

        $coupons = $this->viewVar($response, 'coupons');
        $this->assertInstanceOf(LengthAwarePaginator::class, $coupons);
        $items = $this->pageItems($coupons);

        $findByCode = fn (string $code) => collect($items)
            ->first(fn ($row) => $this->rowValue($row, ['code']) === $code);

        $usedRow = $findByCode('USED10');
        $this->assertNotNull($usedRow, 'The used code must appear in the coupons report.');
        $this->assertSame(2, (int) $this->rowValue($usedRow, ['times_used', 'used_count', 'usage_count', 'orders_count', 'count']), 'Code USED10 was used by 2 orders.');
        $this->assertEqualsWithDelta(
            120.0,
            (float) $this->rowValue($usedRow, ['discount_given', 'total_discount', 'discount_total', 'total_discount_given']),
            0.01,
            'discount_given must be 40 + 80 from the contract formula.'
        );

        $unusedRow = $findByCode('NEVERUSED');
        $this->assertNotNull($unusedRow, 'A never-used code must still appear in the coupons report.');
        $this->assertSame(0, (int) $this->rowValue($unusedRow, ['times_used', 'used_count', 'usage_count', 'orders_count', 'count']));
        $this->assertEqualsWithDelta(
            0.0,
            (float) $this->rowValue($unusedRow, ['discount_given', 'total_discount', 'discount_total', 'total_discount_given']),
            0.01
        );
    }

    // ------------------------------------------------------------------
    // 8. Pagination
    // ------------------------------------------------------------------

    public function test_best_sellers_pagination_spans_multiple_pages(): void
    {
        $user = $this->makeUser();

        // 21 products with sales: more than one page for any sane
        // per-page value (10, 15 or 20), so the assertion does not depend
        // on the exact per-page the backend chose.
        for ($i = 1; $i <= 21; $i++) {
            $product = Product::factory()->create(['name' => 'Paged Seller '.$i, 'price' => 100, 'quantity' => 50]);
            $order = $this->makeOrder(['user_id' => $user->id, 'total_price' => 100]);
            $this->makeOrderItem($order, $product, 100, 1);
        }

        $response = $this->getReports();

        $bestSellers = $this->viewVar($response, 'bestSellers');
        $this->assertInstanceOf(LengthAwarePaginator::class, $bestSellers);
        $this->assertSame(21, $bestSellers->total());
        $this->assertGreaterThan(1, $bestSellers->lastPage(), '21 best sellers must span more than one page.');
        $response->assertSee('best_page', false);

        // Page 2 is reachable through the contract's page name and is
        // not empty.
        $pageTwo = $this->getReports(['best_page' => 2]);
        $pageTwoBest = $this->viewVar($pageTwo, 'bestSellers');
        $this->assertInstanceOf(LengthAwarePaginator::class, $pageTwoBest);
        $this->assertSame(2, $pageTwoBest->currentPage());
        $this->assertNotEmpty($this->pageItems($pageTwoBest));
    }

    // ------------------------------------------------------------------
    // 9. Gate / auth
    // ------------------------------------------------------------------

    public function test_guest_is_redirected_to_admin_login(): void
    {
        // The `admin` middleware redirects unauthenticated dashboard
        // access to the admin login route (see App\Http\Middleware\Admin).
        $this->get(route('reports.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_without_report_ability_gets_forbidden(): void
    {
        $this->actingAs($this->makeLimitedAdmin(), 'admin')
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_reports(): void
    {
        $this->getReports()->assertOk();
    }

    // ------------------------------------------------------------------
    // 10. CSV export
    // ------------------------------------------------------------------

    public function test_csv_export_returns_best_sellers_csv(): void
    {
        $user = $this->makeUser();
        $product = Product::factory()->create(['name' => 'Csv Seller', 'price' => 100, 'quantity' => 50]);
        $order = $this->makeOrder(['user_id' => $user->id, 'total_price' => 100]);
        $this->makeOrderItem($order, $product, 100, 1);

        $response = $this->actingAs($this->makeSuperAdmin(), 'admin')
            ->get(route('reports.index', ['export' => 'best_sellers']));

        $response->assertOk();
        // Exact header value varies by response type (a download adds a
        // charset); assert the media type, not the full header string.
        $this->assertStringContainsString(
            'text/csv',
            (string) $response->headers->get('Content-Type'),
            'CSV export must be served with a text/csv content type.'
        );

        // Streamed downloads do not expose their body through
        // getContent(); TestResponse::streamedContent() captures it.
        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            $content = $response->streamedContent();
        }

        // The file starts with a UTF-8 BOM: strip it before parsing,
        // otherwise the first header field carries the BOM bytes.
        if (is_string($content) && str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $lines = array_values(array_filter(
            array_map('trim', explode("\n", (string) $content)),
            fn ($line) => $line !== ''
        ));
        $this->assertGreaterThanOrEqual(2, count($lines), 'CSV must have a header row plus at least one data row.');
        $this->assertStringContainsString('Csv Seller', (string) $content, 'CSV must contain the sold product.');

        // The file is semicolon-delimited (Excel on Arabic Windows
        // uses ';' as the list separator): every row must split into
        // its own columns, not land in a single cell.
        $header = str_getcsv($lines[0], ';');
        $this->assertCount(4, $header, 'CSV header must split into 4 columns on the semicolon delimiter.');
        $this->assertSame('المنتج', $header[0]);
        $data = str_getcsv($lines[1], ';');
        $this->assertCount(4, $data, 'CSV data rows must split into 4 columns on the semicolon delimiter.');
        $this->assertSame('Csv Seller', $data[0]);
    }

    // ------------------------------------------------------------------
    // 11. Trend
    // ------------------------------------------------------------------

    public function test_trend_covers_the_period_and_sums_to_kpi_revenue(): void
    {
        $user = $this->makeUser();

        $this->makeOrder(['user_id' => $user->id, 'total_price' => 100]); // today
        $this->makeOrder(['user_id' => $user->id, 'total_price' => 200]); // today
        $this->makeOrder(
            ['user_id' => $user->id, 'total_price' => 50],
            Carbon::now()->subDays(5)->setTime(12, 0)
        );

        $response = $this->getReports(['period' => '30d']);

        $kpis = $this->viewVar($response, 'kpis');
        $trend = $this->trendSeries($this->viewVar($response, 'trend'));

        $this->assertCount(count($trend['labels']), $trend['revenue'], 'Trend revenue must have one value per label.');
        $this->assertCount(count($trend['labels']), $trend['orders'], 'Trend orders must have one value per label.');
        // 30d = today + the 29 days before it: exactly 30 daily buckets,
        // labelled Y-m-d from the period start to today.
        $this->assertCount(30, $trend['labels'], 'Trend must cover the requested 30 days.');
        $this->assertSame(Carbon::now()->subDays(29)->toDateString(), $trend['labels'][0]);
        $this->assertSame(Carbon::now()->toDateString(), $trend['labels'][29]);
        $this->assertEqualsWithDelta(
            (float) $kpis['revenue'],
            array_sum($trend['revenue']),
            0.01,
            'Trend revenue must sum to the KPI revenue (100 + 200 + 50).'
        );
        $this->assertSame(3, array_sum($trend['orders']), 'Trend orders must sum to the in-scope order count.');
    }

    // ------------------------------------------------------------------
    // 12. Payment split
    // ------------------------------------------------------------------

    public function test_payment_split_counts_methods_and_paid_status(): void
    {
        $user = $this->makeUser();

        $this->makeOrder(['user_id' => $user->id, 'payment_method' => 'cash_on_delivery', 'payment_status' => 'paid', 'total_price' => 100]);
        $this->makeOrder(['user_id' => $user->id, 'payment_method' => 'card_payment', 'payment_status' => 'paid', 'total_price' => 200]);
        // Unpaid order with no payment method, so it cannot be mistaken
        // for a COD or card order under either counting scope.
        $this->makeOrder(['user_id' => $user->id, 'payment_method' => null, 'payment_status' => 'unpaid', 'status' => 'pending', 'total_price' => 300]);

        $response = $this->getReports();

        $split = $this->viewVar($response, 'paymentSplit');

        $this->assertEqualsWithDelta(1.0, $this->findCount($split, ['cod', 'cash_on_delivery']), 0.0, 'Payment split must count 1 COD order.');
        $this->assertEqualsWithDelta(1.0, $this->findCount($split, ['card', 'card_payment']), 0.0, 'Payment split must count 1 card order.');
        $this->assertEqualsWithDelta(2.0, $this->findCount($split, ['paid']), 0.0, 'Payment split must count 2 paid orders.');
        $this->assertEqualsWithDelta(1.0, $this->findCount($split, ['unpaid']), 0.0, 'Payment split must count 1 unpaid order.');
    }
}
