<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Repositories\Reports\ReportsRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ReportsController extends Controller
{
    protected $reportRepository;

    public function __construct(ReportsRepository $reportRepository)
    {
        $this->reportRepository = $reportRepository;
    }

    public function index(Request $request)
    {
        Gate::authorize('report.create');

        $validated = $request->validate([
            'period' => 'nullable|in:today,7d,30d,custom',
            'start_at' => 'nullable|date_format:Y-m-d|required_if:period,custom',
            'end_at' => [
                'nullable',
                'date_format:Y-m-d',
                'required_if:period,custom',
                'after_or_equal:start_at',
                function (string $attribute, mixed $value, Closure $fail) use ($request) {
                    if ($request->input('period') === 'custom' && $request->filled('start_at') && $value) {
                        $start = Carbon::createFromFormat('Y-m-d', $request->input('start_at'), 'Africa/Cairo');
                        $end = Carbon::createFromFormat('Y-m-d', $value, 'Africa/Cairo');
                        // 92 days inclusive = 91 days apart at most.
                        if ($start && $end && $start->diffInDays($end) > 91) {
                            $fail('النطاق المخصص لا يمكن أن يتجاوز 92 يوماً.');
                        }
                    }
                },
            ],
            'tab' => 'nullable|in:best_sellers,low_stock,top_customers,returns,coupons',
            'export' => 'nullable|in:best_sellers,low_stock,top_customers,returns,coupons',
            'stock_threshold' => 'nullable|integer|min:0|max:100',
        ]);

        [$start, $end] = $this->resolvePeriod($validated);

        $period = [
            'preset' => $validated['period'] ?? '30d',
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ];

        if (! empty($validated['export'])) {
            return $this->reportRepository->exportCsv(
                $validated['export'],
                $start,
                $end,
                (int) ($validated['stock_threshold'] ?? 5)
            );
        }

        $kpis = $this->reportRepository->overview($start, $end);
        $trend = $this->reportRepository->dailyTrend($start, $end);
        $statusBreakdown = $this->reportRepository->ordersByStatus($start, $end);
        $paymentSplit = $this->reportRepository->paymentSplit($start, $end);
        $bestSellers = $this->reportRepository->bestSellers($start, $end);

        $stockThreshold = (int) ($validated['stock_threshold'] ?? 5);
        $lowStock = $this->reportRepository->lowStock($stockThreshold);

        $topCustomers = $this->reportRepository->topCustomers($start, $end);
        $returns = $this->reportRepository->returns($start, $end);

        $returnCounts = $this->reportRepository->returnCounts($start, $end);
        $returnedOrders = $returnCounts['returned'];
        $totalOrders = $returnCounts['total'];
        $returnRate = $totalOrders > 0 ? $returnedOrders / $totalOrders : 0.0;

        $coupons = $this->reportRepository->coupons($start, $end);
        $activeTab = $validated['tab'] ?? 'best_sellers';

        return view('dashboard.reports.index', compact(
            'period',
            'kpis',
            'trend',
            'statusBreakdown',
            'paymentSplit',
            'bestSellers',
            'lowStock',
            'stockThreshold',
            'topCustomers',
            'returns',
            'returnRate',
            'returnedOrders',
            'totalOrders',
            'coupons',
            'activeTab',
        ));
    }

    /**
     * Resolve the requested period to a [start, end] Carbon range in
     * Africa/Cairo (startOfDay / endOfDay). Presets are inclusive:
     * 7d = today + the 6 days before it, 30d = today + the 29 before it.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(array $validated): array
    {
        $preset = $validated['period'] ?? '30d';
        $today = Carbon::today('Africa/Cairo');

        return match ($preset) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            '7d' => [$today->copy()->subDays(6)->startOfDay(), $today->copy()->endOfDay()],
            'custom' => [
                Carbon::createFromFormat('Y-m-d', $validated['start_at'], 'Africa/Cairo')->startOfDay(),
                Carbon::createFromFormat('Y-m-d', $validated['end_at'], 'Africa/Cairo')->endOfDay(),
            ],
            default => [$today->copy()->subDays(29)->startOfDay(), $today->copy()->endOfDay()],
        };
    }
}
