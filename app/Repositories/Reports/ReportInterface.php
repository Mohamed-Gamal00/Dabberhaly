<?php

namespace App\Repositories\Reports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface ReportInterface
{
    public function overview(Carbon $start, Carbon $end): array;

    public function dailyTrend(Carbon $start, Carbon $end): array;

    /** @return array<int, array{status: string, label: string, count: int}> */
    public function ordersByStatus(Carbon $start, Carbon $end): array;

    public function paymentSplit(Carbon $start, Carbon $end): array;

    public function bestSellers(Carbon $start, Carbon $end, int $perPage = 15, string $pageName = 'best_page'): LengthAwarePaginator;

    public function lowStock(int $threshold = 5, int $perPage = 15, string $pageName = 'stock_page'): LengthAwarePaginator;

    public function topCustomers(Carbon $start, Carbon $end, int $perPage = 15, string $pageName = 'customers_page'): LengthAwarePaginator;

    public function returns(Carbon $start, Carbon $end, int $perPage = 15, string $pageName = 'returns_page'): LengthAwarePaginator;

    public function returnRate(Carbon $start, Carbon $end): float;

    /** @return array{returned: int, total: int} */
    public function returnCounts(Carbon $start, Carbon $end): array;

    public function coupons(Carbon $start, Carbon $end, int $perPage = 15, string $pageName = 'coupons_page'): LengthAwarePaginator;

    public function exportCsv(string $type, Carbon $start, Carbon $end, int $threshold = 5): StreamedResponse;
}
