<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\User;
use App\Services\FinanceOrders;
use Illuminate\Support\Facades\DB;

class ReportController extends AdminController
{
    private function data(): array
    {
        $this->authorizeAdmin();
        $paid = app(FinanceOrders::class)->paid();
        $days = (clone $paid)->selectRaw('DATE(created_at) as period, COUNT(*) as order_count, SUM(total_amount) as total_revenue')->groupByRaw('DATE(created_at)')->orderBy('period')->get();
        $group = fn (int $length) => $days->groupBy(fn ($day) => substr($day->period, 0, $length))->map(fn ($rows, $key) => (object) ['period' => (string) $key, 'order_count' => $rows->sum('order_count'), 'total_revenue' => $rows->sum('total_revenue')])->values();
        $categories = DB::table('order_items')->leftJoin('products', 'products.id', '=', 'order_items.product_id')->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('order_items.order_id', (clone $paid)->select('id'))
            ->select('categories.name as category_name')->selectRaw('SUM(order_items.quantity) as total_qty, SUM(order_items.price * order_items.quantity) as total_revenue')
            ->groupBy('categories.name')->orderByDesc('total_revenue')->get();

        return [
            'totalOrders' => Order::where('created_at', '<=', now())->count(), 'totalCustomers' => User::where('is_admin', false)->count(),
            'totalRevenue' => $days->sum('total_revenue'), 'categoryRevenue' => $categories,
            'periods' => ['Theo ngày' => $days, 'Theo tháng' => $group(7), 'Theo năm' => $group(4)],
            'methodRevenue' => (clone $paid)->select('gateway')->selectRaw('SUM(total_amount) as total_revenue')->groupBy('gateway')->get(),
        ];
    }

    public function index()
    {
        return view('admin.reports.index', $this->data());
    }

    public function charts()
    {
        $data = $this->data();
        $daily = $data['periods']['Theo ngày']->keyBy('period');
        $monthly = $data['periods']['Theo tháng']->keyBy('period');
        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
        $months = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i)->format('Y-m'));
        $data['charts'] = [
            ['title' => 'Theo danh mục', 'type' => 'bar', 'labels' => $data['categoryRevenue']->pluck('category_name')->map(fn ($name) => $name ?? 'Không còn danh mục'), 'values' => $data['categoryRevenue']->pluck('total_revenue')],
            ['title' => '30 ngày gần nhất', 'type' => 'line', 'labels' => $days, 'values' => $days->map(fn ($date) => $daily->get($date)?->total_revenue ?? 0)],
            ['title' => '12 tháng gần nhất', 'type' => 'bar', 'labels' => $months, 'values' => $months->map(fn ($month) => $monthly->get($month)?->total_revenue ?? 0)],
            ['title' => 'Theo năm', 'type' => 'bar', 'labels' => $data['periods']['Theo năm']->pluck('period'), 'values' => $data['periods']['Theo năm']->pluck('total_revenue')],
            ['title' => 'Theo phương thức', 'type' => 'doughnut', 'labels' => $data['methodRevenue']->pluck('gateway'), 'values' => $data['methodRevenue']->pluck('total_revenue')],
        ];

        return view('admin.reports.charts', $data);
    }
}
