<?php

namespace App\Repositories\Reports;

use App\Enums\OrderStatus;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsRepository implements ReportInterface
{
    /**
     * THE paid-order scope — the single definition of "revenue" used by
     * every revenue-related query in this repository:
     *
     *   revenue = SUM(total_price) over orders with payment_status =
     *   'paid', excluding cancelled orders and returned orders, within
     *   the period on created_at.
     *
     * Delivered COD orders count as paid, per the existing
     * delivered→paid rule (OrderRepository marks payment_status 'paid'
     * when an order becomes delivered). total_price already includes
     * shipping and VAT, so it is summed as stored. Soft-deleted orders
     * are excluded (Eloquent default; the raw query-builder joins add
     * an explicit deleted_at check).
     */
    private function applyPaidScope(EloquentBuilder|QueryBuilder $query, Carbon $start, Carbon $end, string $table = 'orders'): EloquentBuilder|QueryBuilder
    {
        return $query
            ->where("{$table}.payment_status", 'paid')
            ->where("{$table}.status", '!=', OrderStatus::Cancelled->value)
            ->where("{$table}.return_order", false)
            ->whereBetween("{$table}.created_at", [$start, $end])
            ->whereNull("{$table}.deleted_at");
    }

    /** Eloquent base query for the paid-order scope. */
    private function paidOrders(Carbon $start, Carbon $end): EloquentBuilder
    {
        return $this->applyPaidScope(Order::query(), $start, $end);
    }

    public function overview(Carbon $start, Carbon $end): array
    {
        $key = 'reports_overview_' . $start->toDateString() . '_' . $end->toDateString();

        return cache()->remember($key, 600, function () use ($start, $end) {
            // orders_count = PAID orders only (same scope as revenue),
            // so avg_order_value = revenue / paid orders.
            $revenue = (float) $this->paidOrders($start, $end)->sum('total_price');
            $ordersCount = $this->paidOrders($start, $end)->count();
            $newCustomers = User::query()->whereBetween('created_at', [$start, $end])->count();

            return [
                'revenue' => $revenue,
                'orders_count' => (int) $ordersCount,
                'avg_order_value' => $ordersCount > 0 ? round($revenue / $ordersCount, 2) : 0.0,
                'new_customers' => (int) $newCustomers,
            ];
        });
    }

    public function dailyTrend(Carbon $start, Carbon $end): array
    {
        $key = 'reports_trend_' . $start->toDateString() . '_' . $end->toDateString();

        return cache()->remember($key, 600, function () use ($start, $end) {
            $rows = $this->paidOrders($start, $end)
                ->selectRaw('DATE(created_at) as day, SUM(total_price) as revenue, COUNT(*) as orders')
                ->groupByRaw('DATE(created_at)')
                ->orderBy('day')
                ->get()
                ->keyBy('day');

            // Fill missing days with 0 so the chart is continuous.
            // The period is capped at 92 days, so this is at most ~92 points.
            $labels = [];
            $revenue = [];
            $orders = [];
            $cursor = $start->copy()->startOfDay();
            $last = $end->copy()->startOfDay();

            while ($cursor->lte($last)) {
                $day = $cursor->toDateString();
                $labels[] = $day;
                $revenue[] = isset($rows[$day]) ? (float) $rows[$day]->revenue : 0.0;
                $orders[] = isset($rows[$day]) ? (int) $rows[$day]->orders : 0;
                $cursor->addDay();
            }

            return ['labels' => $labels, 'revenue' => $revenue, 'orders' => $orders];
        });
    }

    public function ordersByStatus(Carbon $start, Carbon $end): array
    {
        // ALL orders in the period, not just paid ones — a status
        // breakdown must show cancelled orders too. Raw query builder
        // (not Eloquent): Order casts status to the OrderStatus enum,
        // which cannot be used as a pluck key.
        $counts = DB::table('orders')
            ->whereBetween('created_at', [$start, $end])
            ->whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        // Every status is returned, with 0 where it has no orders.
        return array_map(
            fn (OrderStatus $status) => [
                'status' => $status->value,
                'label' => $status->labelAr(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ],
            OrderStatus::cases()
        );
    }

    public function paymentSplit(Carbon $start, Carbon $end): array
    {
        // COD = cash_on_delivery / legacy 'cash'; card = 'card' and the
        // value the checkout actually stores, 'card_payment'
        // (Setting::enabledPaymentMethods()); anything else is grouped
        // under its raw value.
        $rows = $this->paidOrders($start, $end)
            ->selectRaw("COALESCE(CASE WHEN payment_method IN ('cash_on_delivery', 'cash') THEN 'cod' WHEN payment_method IN ('card', 'card_payment') THEN 'card' ELSE payment_method END, 'other') as method, COUNT(*) as count")
            ->groupByRaw("COALESCE(CASE WHEN payment_method IN ('cash_on_delivery', 'cash') THEN 'cod' WHEN payment_method IN ('card', 'card_payment') THEN 'card' ELSE payment_method END, 'other')")
            ->get()
            ->pluck('count', 'method');

        $split = [
            'cod' => ['count' => (int) ($rows['cod'] ?? 0)],
            'card' => ['count' => (int) ($rows['card'] ?? 0)],
        ];

        foreach ($rows as $method => $count) {
            if ($method !== 'cod' && $method !== 'card') {
                $split[$method] = ['count' => (int) $count];
            }
        }

        $paid = $this->paidOrders($start, $end)->count();
        $unpaid = Order::query()
            ->where('payment_status', '!=', 'paid')
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->where('return_order', false)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $split['paid'] = (int) $paid;
        $split['unpaid'] = (int) $unpaid;

        return $split;
    }

    /**
     * NOTE: item prices are pre-coupon offer prices; the coupon
     * discount lives at order level (see coupons()), so per-product
     * revenue can slightly overstate when a coupon was used.
     */
    private function bestSellersQuery(Carbon $start, Carbon $end): QueryBuilder
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'order_items.product_id',
                DB::raw('MIN(order_items.product_name) as product_name'),
                DB::raw('SUM(order_items.quantity) as quantity_sold'),
                DB::raw('SUM(order_items.price * order_items.quantity) as revenue'),
            )
            ->groupBy('order_items.product_id')
            ->orderByDesc('revenue');

        return $this->applyPaidScope($query, $start, $end, 'orders');
    }

    public function bestSellers(Carbon $start, Carbon $end, int $perPage = 15, string $pageName = 'best_page'): LengthAwarePaginator
    {
        $paginator = $this->bestSellersQuery($start, $end)->paginate($perPage, ['*'], $pageName);
        $this->attachCategoryNames($paginator->getCollection());

        return $paginator;
    }

    /**
     * Category name from the live product (via product->parent) when the
     * product still exists; the display name always comes from the
     * item's denormalized product_name, so a deleted product still
     * shows. Products are loaded in ONE query — no N+1.
     */
    private function attachCategoryNames(Collection $rows): void
    {
        $ids = $rows->pluck('product_id')->filter()->unique()->values();
        $products = $ids->isNotEmpty()
            ? Product::with('parent')->whereIn('id', $ids)->get()->keyBy('id')
            : collect();

        foreach ($rows as $row) {
            $product = $row->product_id !== null ? ($products[$row->product_id] ?? null) : null;
            $row->category_name = $product?->parent?->name ?? null;
            $row->quantity_sold = (int) $row->quantity_sold;
            $row->revenue = (float) $row->revenue;
        }
    }

    private function lowStockQuery(int $threshold): EloquentBuilder
    {
        return Product::query()
            ->select('products.*')
            ->selectSub(
                OrderItem::query()
                    ->selectRaw('MAX(orders.created_at)')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->whereColumn('order_items.product_id', 'products.id')
                    ->where('orders.payment_status', 'paid')
                    ->where('orders.status', '!=', OrderStatus::Cancelled->value)
                    ->where('orders.return_order', false)
                    ->whereNull('orders.deleted_at')
                    ->toBase(),
                'last_sold_at'
            )
            ->where('quantity', '<=', $threshold)
            ->with('parent.category')
            ->orderBy('quantity');
    }

    public function lowStock(int $threshold = 5, int $perPage = 15, string $pageName = 'stock_page'): LengthAwarePaginator
    {
        // Current snapshot — NO period filter. last_sold_at counts paid
        // orders only (no period: it is the last time the product sold,
        // ever); NULL when the product was never sold.
        return $this->lowStockQuery($threshold)->paginate($perPage, ['*'], $pageName);
    }

    private function topCustomersQuery(Carbon $start, Carbon $end): QueryBuilder
    {
        $query = DB::table('orders')
            // Inner join: guest orders have user_id NULL and are excluded.
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->select(
                'users.id as user_id',
                DB::raw("CONCAT_WS(' ', users.first_name, users.family_name) as name"),
                'users.email',
                'users.phone_number as phone',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(orders.total_price) as total_spent'),
                DB::raw('MAX(orders.created_at) as last_order_at'),
            )
            ->groupBy('users.id', 'users.first_name', 'users.family_name', 'users.email', 'users.phone_number')
            ->orderByDesc('total_spent');

        return $this->applyPaidScope($query, $start, $end, 'orders');
    }

    public function topCustomers(Carbon $start, Carbon $end, int $perPage = 15, string $pageName = 'customers_page'): LengthAwarePaginator
    {
        return $this->topCustomersQuery($start, $end)->paginate($perPage, ['*'], $pageName);
    }

    private function returnsQuery(Carbon $start, Carbon $end): QueryBuilder
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.return_order', true)
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereNull('orders.deleted_at')
            ->select(
                'order_items.product_id',
                DB::raw('MIN(order_items.product_name) as product_name'),
                DB::raw('SUM(order_items.quantity) as quantity_returned'),
            )
            ->groupBy('order_items.product_id')
            ->orderByDesc('quantity_returned');
    }

    public function returns(Carbon $start, Carbon $end, int $perPage = 15, string $pageName = 'returns_page'): LengthAwarePaginator
    {
        return $this->returnsQuery($start, $end)->paginate($perPage, ['*'], $pageName);
    }

    public function returnCounts(Carbon $start, Carbon $end): array
    {
        $base = Order::query()->whereBetween('created_at', [$start, $end]);

        return [
            'returned' => (int) (clone $base)->where('return_order', true)->count(),
            // Denominator excludes cancelled orders: a cancelled order
            // cannot be returned.
            'total' => (int) (clone $base)->where('status', '!=', OrderStatus::Cancelled->value)->count(),
        ];
    }

    public function returnRate(Carbon $start, Carbon $end): float
    {
        $counts = $this->returnCounts($start, $end);

        return $counts['total'] > 0 ? $counts['returned'] / $counts['total'] : 0.0;
    }

    /**
     * Discount amount reuses the OrderResource formula exactly:
     * max(0, totalBeforeDiscount - (total_price - shipping_price -
     * tax_amount)), only where totalBeforeDiscount IS NOT NULL.
     *
     * Codes with zero uses still appear (LEFT JOIN). Usage is counted
     * over PAID, non-cancelled, non-returned orders in the period —
     * the same paid scope as every other revenue figure in these
     * reports, so times_used, discount_given and revenue all describe
     * the same set of orders and reconcile with the overview KPIs.
     */
    private function couponsQuery(Carbon $start, Carbon $end): EloquentBuilder
    {
        return DiscountCode::query()
            ->leftJoin('orders', function (JoinClause $join) use ($start, $end) {
                $join->on('orders.discount_code_id', '=', 'discount_codes.id')
                    ->whereBetween('orders.created_at', [$start, $end])
                    ->where('orders.payment_status', 'paid')
                    ->where('orders.status', '!=', OrderStatus::Cancelled->value)
                    ->where('orders.return_order', false)
                    ->whereNull('orders.deleted_at');
            })
            ->select(
                'discount_codes.id',
                'discount_codes.code',
                'discount_codes.name',
                DB::raw('COUNT(orders.id) as times_used'),
                DB::raw('COALESCE(SUM(CASE WHEN orders.totalBeforeDiscount IS NOT NULL THEN GREATEST(0, orders.totalBeforeDiscount - (orders.total_price - COALESCE(orders.shipping_price, 0) - COALESCE(orders.tax_amount, 0))) ELSE 0 END), 0) as discount_given'),
                DB::raw('COALESCE(SUM(orders.total_price), 0) as revenue'),
            )
            ->groupBy('discount_codes.id', 'discount_codes.code', 'discount_codes.name')
            ->orderByDesc('times_used');
    }

    public function coupons(Carbon $start, Carbon $end, int $perPage = 15, string $pageName = 'coupons_page'): LengthAwarePaginator
    {
        return $this->couponsQuery($start, $end)->paginate($perPage, ['*'], $pageName);
    }

    public function exportCsv(string $type, Carbon $start, Carbon $end, int $threshold = 5): StreamedResponse
    {
        $filename = 'reports-' . $type . '-' . $start->toDateString() . '-' . $end->toDateString() . '.csv';

        return response()->streamDownload(function () use ($type, $start, $end, $threshold) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens Arabic correctly.
            fwrite($out, "\xEF\xBB\xBF");

            [$headers, $rows] = $this->exportRows($type, $start, $end, $threshold);
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Same base queries as the paginated methods, without pagination, capped at 5000 rows. */
    private function exportRows(string $type, Carbon $start, Carbon $end, int $threshold = 5): array
    {
        return match ($type) {
            'best_sellers' => $this->bestSellersExportRows($start, $end),
            'low_stock' => $this->lowStockExportRows($threshold),
            'top_customers' => $this->topCustomersExportRows($start, $end),
            'returns' => $this->returnsExportRows($start, $end),
            'coupons' => $this->couponsExportRows($start, $end),
            default => throw new \InvalidArgumentException("Unknown report export type: {$type}"),
        };
    }

    private function bestSellersExportRows(Carbon $start, Carbon $end): array
    {
        $rows = $this->bestSellersQuery($start, $end)->limit(5000)->get();
        $this->attachCategoryNames($rows);

        return [
            ['المنتج', 'التصنيف', 'الكمية المباعة', 'الإيراد (ر.س)'],
            $rows->map(fn ($row) => [
                $row->product_name ?? '',
                $row->category_name ?? '',
                (int) $row->quantity_sold,
                (float) $row->revenue,
            ])->all(),
        ];
    }

    private function lowStockExportRows(int $threshold = 5): array
    {
        // Same threshold as the on-screen list (passed through from
        // the export URL), so the file matches what the admin sees.
        $rows = $this->lowStockQuery($threshold)->limit(5000)->get();

        return [
            ['المنتج', 'التصنيف', 'الكمية المتاحة', 'السعر (ر.س)', 'آخر عملية بيع'],
            $rows->map(fn (Product $product) => [
                $product->name ?? '',
                $product->parent?->name ?? '',
                (int) $product->quantity,
                (float) $product->price,
                $product->last_sold_at ?? '',
            ])->all(),
        ];
    }

    private function topCustomersExportRows(Carbon $start, Carbon $end): array
    {
        $rows = $this->topCustomersQuery($start, $end)->limit(5000)->get();

        return [
            ['العميل', 'البريد الإلكتروني', 'رقم الهاتف', 'عدد الطلبات', 'إجمالي الإنفاق (ر.س)', 'آخر طلب'],
            $rows->map(fn ($row) => [
                $row->name ?? '',
                $row->email ?? '',
                $row->phone ?? '',
                (int) $row->orders_count,
                (float) $row->total_spent,
                $row->last_order_at ?? '',
            ])->all(),
        ];
    }

    private function returnsExportRows(Carbon $start, Carbon $end): array
    {
        $rows = $this->returnsQuery($start, $end)->limit(5000)->get();

        return [
            ['المنتج', 'الكمية المرتجعة'],
            $rows->map(fn ($row) => [
                $row->product_name ?? '',
                (int) $row->quantity_returned,
            ])->all(),
        ];
    }

    private function couponsExportRows(Carbon $start, Carbon $end): array
    {
        $rows = $this->couponsQuery($start, $end)->limit(5000)->get();

        return [
            ['الكود', 'الاسم', 'مرات الاستخدام', 'قيمة الخصم (ر.س)', 'الإيراد (ر.س)'],
            $rows->map(fn ($row) => [
                $row->code ?? '',
                $row->name ?? '',
                (int) $row->times_used,
                (float) $row->discount_given,
                (float) $row->revenue,
            ])->all(),
        ];
    }
}
