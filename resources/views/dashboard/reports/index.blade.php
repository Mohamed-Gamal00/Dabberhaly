@extends('dashboard.index')

@section('css')
    <style>
        .report-kpi {
            border-inline-start: 4px solid #76543D;
        }
        .report-kpi .kpi-icon {
            width: 44px;
            height: 44px;
            background: #F7F0E5;
            color: #76543D;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .report-kpi .kpi-value {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
        }
        .report-kpi .kpi-label {
            font-size: 12px;
            color: #6b7280;
        }
        .report-chart-wrap {
            position: relative;
            height: 300px;
        }
        .report-period-btn.active {
            background-color: #76543D;
            border-color: #76543D;
            color: #fff;
        }
    </style>
@endsection

@section('title', 'التقارير')

@section('breadcrumb')
    @parent
    <li class="breadcrumb-item active" aria-current="page">التقارير</li>
@endsection

@section('section')
    @php
        $activeTab = $activeTab ?? 'best_sellers';
        $baseParams = request()->except(['period', 'start_at', 'end_at', 'page', 'export']);
        $tabUrl = fn($tab) => route('reports.index', array_merge($baseParams, ['period' => $period['preset'], 'start_at' => $period['start'], 'end_at' => $period['end'], 'tab' => $tab]));
        $presetUrl = fn($preset) => route('reports.index', array_merge($baseParams, ['period' => $preset, 'tab' => $activeTab]));
        $exportUrl = fn($tab) => route('reports.index', array_merge(request()->query(), ['export' => $tab]));
        $statusTotal = collect($statusBreakdown)->sum('count');
        $paymentTotal = ($paymentSplit['cod']['count'] ?? 0) + ($paymentSplit['card']['count'] ?? 0);
    @endphp

    <x-alert type="success" />

    {{-- ══ Period filter ══ --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h5 class="card-title mb-0 me-2">الفترة</h5>
                        <div class="btn-group" role="group" aria-label="الفترة">
                            <a href="{{ $presetUrl('today') }}" class="btn btn-outline-secondary report-period-btn {{ $period['preset'] === 'today' ? 'active' : '' }}">اليوم</a>
                            <a href="{{ $presetUrl('7d') }}" class="btn btn-outline-secondary report-period-btn {{ $period['preset'] === '7d' ? 'active' : '' }}">آخر 7 أيام</a>
                            <a href="{{ $presetUrl('30d') }}" class="btn btn-outline-secondary report-period-btn {{ $period['preset'] === '30d' ? 'active' : '' }}">آخر 30 يوم</a>
                        </div>
                        <form action="{{ route('reports.index') }}" method="get" class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                            <input type="hidden" name="period" value="custom">
                            <input type="hidden" name="tab" value="{{ $activeTab }}">
                            <label class="mb-0" for="start_at">من</label>
                            <input type="date" id="start_at" name="start_at" class="form-control w-auto" value="{{ $period['start'] }}" required>
                            <label class="mb-0" for="end_at">إلى</label>
                            <input type="date" id="end_at" name="end_at" class="form-control w-auto" value="{{ $period['end'] }}" required>
                            <button type="submit" class="btn btn-primary waves-effect waves-light">عرض</button>
                        </form>
                    </div>
                    <p class="text-muted mb-0 mt-2" style="font-size:13px">
                        من {{ date('d/m/Y', strtotime($period['start'])) }} إلى {{ date('d/m/Y', strtotime($period['end'])) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ KPIs ══ --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 report-kpi">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon"><i class="mdi mdi-cash-multiple"></i></div>
                    <div>
                        <div class="kpi-value">{{ number_format($kpis['revenue'], 2) }} ر.س</div>
                        <div class="kpi-label">الإيرادات</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 report-kpi">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon"><i class="mdi mdi-shopping-outline"></i></div>
                    <div>
                        <div class="kpi-value">{{ number_format($kpis['orders_count']) }}</div>
                        <div class="kpi-label">عدد الطلبات</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 report-kpi">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon"><i class="mdi mdi-receipt-text-outline"></i></div>
                    <div>
                        <div class="kpi-value">{{ number_format($kpis['avg_order_value'], 2) }} ر.س</div>
                        <div class="kpi-label">متوسط قيمة الطلب</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 report-kpi">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon"><i class="mdi mdi-account-plus-outline"></i></div>
                    <div>
                        <div class="kpi-value">{{ number_format($kpis['new_customers']) }}</div>
                        <div class="kpi-label">عملاء جدد</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Trend chart ══ --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom d-flex align-items-center justify-content-between">
                    <div class="fw-bold">
                        <i class="mdi mdi-chart-line me-2" style="color:#76543D"></i>
                        الإيرادات اليومية
                    </div>
                    <span class="text-muted" style="font-size:12px">ر.س</span>
                </div>
                <div class="card-body">
                    @if (!empty($trend['labels']))
                        <div class="report-chart-wrap">
                            <canvas id="trendChart"></canvas>
                        </div>
                    @else
                        <p class="text-muted text-center py-4 mb-0">لا توجد بيانات في هذه الفترة</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Breakdowns ══ --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom fw-bold">
                    <i class="mdi mdi-chart-donut me-2" style="color:#76543D"></i>
                    الطلبات حسب الحالة
                </div>
                <div class="card-body">
                    @forelse ($statusBreakdown as $row)
                        @php $pct = $statusTotal > 0 ? ($row['count'] / $statusTotal) * 100 : 0; @endphp
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span style="font-size:13px; color:#374151">{{ $row['label'] }}</span>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress" style="width:120px; height:6px; border-radius:3px">
                                    <div class="progress-bar" style="width:{{ $pct }}%; background-color:#76543D"></div>
                                </div>
                                <span class="fw-bold" style="font-size:13px; min-width:24px; text-align:left">{{ number_format($row['count']) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted text-center py-3 mb-0" style="font-size:13px">لا توجد بيانات في هذه الفترة</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom fw-bold">
                    <i class="mdi mdi-credit-card-outline me-2" style="color:#76543D"></i>
                    طرق الدفع
                </div>
                <div class="card-body">
                    @php
                        $codCount = $paymentSplit['cod']['count'] ?? 0;
                        $cardCount = $paymentSplit['card']['count'] ?? 0;
                        $codPct = $paymentTotal > 0 ? ($codCount / $paymentTotal) * 100 : 0;
                        $cardPct = $paymentTotal > 0 ? ($cardCount / $paymentTotal) * 100 : 0;
                    @endphp
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span style="font-size:13px; color:#374151">الدفع عند الاستلام</span>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress" style="width:120px; height:6px; border-radius:3px">
                                <div class="progress-bar" style="width:{{ $codPct }}%; background-color:#76543D"></div>
                            </div>
                            <span class="fw-bold" style="font-size:13px; min-width:24px; text-align:left">{{ number_format($codCount) }}</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span style="font-size:13px; color:#374151">الدفع بالبطاقة</span>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress" style="width:120px; height:6px; border-radius:3px">
                                <div class="progress-bar" style="width:{{ $cardPct }}%; background-color:#C69763"></div>
                            </div>
                            <span class="fw-bold" style="font-size:13px; min-width:24px; text-align:left">{{ number_format($cardCount) }}</span>
                        </div>
                    </div>
                    <div class="border-top pt-3 mt-2">
                        <div class="d-flex justify-content-between" style="font-size:12px; color:#6b7280">
                            <span>مدفوع</span>
                            <span class="fw-bold text-success">{{ number_format($paymentSplit['paid']) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mt-1" style="font-size:12px; color:#6b7280">
                            <span>غير مدفوع</span>
                            <span class="fw-bold text-danger">{{ number_format($paymentSplit['unpaid']) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Tables tabs ══ --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab === 'best_sellers' ? 'active' : '' }}" href="{{ $tabUrl('best_sellers') }}" role="tab">الأكثر مبيعًا</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab === 'low_stock' ? 'active' : '' }}" href="{{ $tabUrl('low_stock') }}" role="tab">مخزون منخفض</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab === 'top_customers' ? 'active' : '' }}" href="{{ $tabUrl('top_customers') }}" role="tab">أفضل العملاء</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab === 'returns' ? 'active' : '' }}" href="{{ $tabUrl('returns') }}" role="tab">المرتجعات</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab === 'coupons' ? 'active' : '' }}" href="{{ $tabUrl('coupons') }}" role="tab">الكوبونات</a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        {{-- Best sellers --}}
                        <div class="tab-pane {{ $activeTab === 'best_sellers' ? 'active' : '' }} p-3" role="tabpanel">
                            <div class="text-end mb-3">
                                <a href="{{ $exportUrl('best_sellers') }}" class="btn btn-outline-secondary btn-sm waves-effect waves-light">
                                    <i class="mdi mdi-download me-1"></i> تصدير CSV
                                </a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-nowrap align-middle table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>المنتج</th>
                                            <th>التصنيف</th>
                                            <th>الكمية المباعة</th>
                                            <th>الإيرادات (ر.س)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($bestSellers as $index => $item)
                                            <tr>
                                                <td>{{ $bestSellers->firstItem() + $index }}</td>
                                                <td>{{ $item->product_name }}</td>
                                                <td>{{ $item->category_name ?? '—' }}</td>
                                                <td>{{ number_format($item->quantity_sold) }}</td>
                                                <td>{{ number_format($item->revenue, 2) }} ر.س</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">لا توجد بيانات في هذه الفترة</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            {{ $bestSellers->withQueryString()->links() }}
                        </div>

                        {{-- Low stock --}}
                        <div class="tab-pane {{ $activeTab === 'low_stock' ? 'active' : '' }} p-3" role="tabpanel">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <form action="{{ route('reports.index') }}" method="get" class="d-flex flex-wrap align-items-center gap-2">
                                    <input type="hidden" name="period" value="{{ $period['preset'] }}">
                                    <input type="hidden" name="start_at" value="{{ $period['start'] }}">
                                    <input type="hidden" name="end_at" value="{{ $period['end'] }}">
                                    <input type="hidden" name="tab" value="low_stock">
                                    <label class="mb-0" for="stock_threshold">عرض المنتجات بكمية أقل من أو تساوي</label>
                                    <input type="number" id="stock_threshold" name="stock_threshold" class="form-control" style="width:90px" value="{{ $stockThreshold }}" min="0" max="100">
                                    <button type="submit" class="btn btn-primary btn-sm waves-effect waves-light">عرض</button>
                                </form>
                                <a href="{{ $exportUrl('low_stock') }}" class="btn btn-outline-secondary btn-sm waves-effect waves-light">
                                    <i class="mdi mdi-download me-1"></i> تصدير CSV
                                </a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-nowrap align-middle table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>المنتج</th>
                                            <th>التصنيف</th>
                                            <th>السعر (ر.س)</th>
                                            <th>الكمية المتبقية</th>
                                            <th>آخر عملية بيع</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($lowStock as $product)
                                            <tr>
                                                <td>{{ $product->CurrentNameLang }}</td>
                                                <td>{{ $product->parent?->CurrentNameLang ?? '—' }}</td>
                                                <td>{{ number_format($product->discount_price ?: $product->price, 2) }} ر.س</td>
                                                <td>
                                                    @if ($product->quantity <= 0)
                                                        <span class="badge bg-danger">نفدت الكمية</span>
                                                    @elseif ($product->quantity <= $stockThreshold)
                                                        <span class="badge bg-warning text-dark">{{ number_format($product->quantity) }}</span>
                                                    @else
                                                        {{ number_format($product->quantity) }}
                                                    @endif
                                                </td>
                                                <td>{{ !empty($product->last_sold_at) ? \Carbon\Carbon::parse($product->last_sold_at)->format('d/m/Y') : '—' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">لا توجد بيانات في هذه الفترة</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            {{ $lowStock->withQueryString()->links() }}
                        </div>

                        {{-- Top customers --}}
                        <div class="tab-pane {{ $activeTab === 'top_customers' ? 'active' : '' }} p-3" role="tabpanel">
                            <div class="text-end mb-3">
                                <a href="{{ $exportUrl('top_customers') }}" class="btn btn-outline-secondary btn-sm waves-effect waves-light">
                                    <i class="mdi mdi-download me-1"></i> تصدير CSV
                                </a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-nowrap align-middle table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>العميل</th>
                                            <th>الهاتف</th>
                                            <th>عدد الطلبات</th>
                                            <th>إجمالي المشتريات (ر.س)</th>
                                            <th>آخر طلب</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($topCustomers as $customer)
                                            <tr>
                                                <td>
                                                    {{ $customer->name }}
                                                    @if (!empty($customer->email))
                                                        <br><small class="text-muted">{{ $customer->email }}</small>
                                                    @endif
                                                </td>
                                                <td>{{ $customer->phone ?? '—' }}</td>
                                                <td>{{ number_format($customer->orders_count) }}</td>
                                                <td>{{ number_format($customer->total_spent, 2) }} ر.س</td>
                                                <td>{{ !empty($customer->last_order_at) ? \Carbon\Carbon::parse($customer->last_order_at)->format('d/m/Y') : '—' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">لا توجد بيانات في هذه الفترة</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            {{ $topCustomers->withQueryString()->links() }}
                        </div>

                        {{-- Returns --}}
                        <div class="tab-pane {{ $activeTab === 'returns' ? 'active' : '' }} p-3" role="tabpanel">
                            <p class="text-muted" style="font-size:13px">
                                معدل الإرجاع: {{ number_format($returnRate * 100, 1) }}% ({{ number_format($returnedOrders) }} طلب مرتجع من أصل {{ number_format($totalOrders) }} طلب)
                            </p>
                            <div class="text-end mb-3">
                                <a href="{{ $exportUrl('returns') }}" class="btn btn-outline-secondary btn-sm waves-effect waves-light">
                                    <i class="mdi mdi-download me-1"></i> تصدير CSV
                                </a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-nowrap align-middle table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>المنتج</th>
                                            <th>الكمية المرتجعة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($returns as $item)
                                            <tr>
                                                <td>{{ $item->product_name }}</td>
                                                <td>{{ number_format($item->quantity_returned) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="2" class="text-center text-muted py-4">لا توجد بيانات في هذه الفترة</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            {{ $returns->withQueryString()->links() }}
                        </div>

                        {{-- Coupons --}}
                        <div class="tab-pane {{ $activeTab === 'coupons' ? 'active' : '' }} p-3" role="tabpanel">
                            <div class="text-end mb-3">
                                <a href="{{ $exportUrl('coupons') }}" class="btn btn-outline-secondary btn-sm waves-effect waves-light">
                                    <i class="mdi mdi-download me-1"></i> تصدير CSV
                                </a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-nowrap align-middle table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>الكود</th>
                                            <th>الاسم</th>
                                            <th>مرات الاستخدام</th>
                                            <th>إجمالي الخصم (ر.س)</th>
                                            <th>إيرادات الطلبات المستخدمة (ر.س)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($coupons as $coupon)
                                            <tr>
                                                <td>{{ $coupon->code }}</td>
                                                <td>{{ $coupon->name }}</td>
                                                <td>{{ number_format($coupon->times_used) }}</td>
                                                <td>{{ number_format($coupon->discount_given, 2) }} ر.س</td>
                                                <td>{{ number_format($coupon->revenue, 2) }} ر.س</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">لا توجد بيانات في هذه الفترة</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            {{ $coupons->withQueryString()->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var canvas = document.getElementById('trendChart');
            if (!canvas || typeof Chart === 'undefined') {
                return;
            }
            var trend = @json($trend);
            if (!trend.labels || trend.labels.length === 0) {
                return;
            }
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: trend.labels,
                    datasets: [{
                        label: 'الإيرادات (ر.س)',
                        data: trend.revenue,
                        borderColor: '#76543D',
                        backgroundColor: 'rgba(118, 84, 61, 0.12)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#76543D',
                        pointRadius: 3,
                        yAxisID: 'y'
                    }, {
                        label: 'عدد الطلبات',
                        data: trend.orders,
                        type: 'bar',
                        backgroundColor: 'rgba(198, 151, 99, 0.55)',
                        borderColor: '#C69763',
                        borderWidth: 1,
                        borderRadius: 4,
                        yAxisID: 'y2'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top', labels: { font: { size: 12 }, boxWidth: 12 } },
                        tooltip: { rtl: true, textDirection: 'rtl' }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: {
                            position: 'right',
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            ticks: { font: { size: 11 } }
                        },
                        y2: {
                            position: 'left',
                            grid: { display: false },
                            ticks: { font: { size: 11 }, stepSize: 1 }
                        }
                    }
                }
            });
        });
    </script>
@endsection
