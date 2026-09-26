<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends AdminController
{
    public function index()
    {
        $this->authorizeAdmin();
        $months = collect(range(5, 0))->map(fn ($offset) => Carbon::now()->subMonths($offset));
        $revenueByMonth = Order::where('status', 'completed')
            ->where('created_at', '>=', $months->first()->copy()->startOfMonth())
            ->get(['created_at', 'total_amount'])
            ->groupBy(fn (Order $order) => $order->created_at->format('Y-m'))
            ->map(fn ($orders) => $orders->sum('total_amount'));

        return view('admin.dashboard', [
            'stats' => [
                'products' => Product::count(),
                'categories' => Category::count(),
                'customers' => User::where('is_admin', false)->count(),
                'orders' => Order::count(),
                'pending' => Order::where('status', 'pending')->count(),
                'shipping_review' => Order::where('shipping_status', 'partial_return')->count(),
                'revenue' => Order::where('status', 'completed')->sum('total_amount'),
            ],
            'orders' => Order::latest()->take(6)->get(),
            'lowStock' => ProductVariant::active()->with('product')->whereColumn('stock', '<=', 'low_stock_threshold')->orderBy('stock')->take(6)->get(),
            'chartLabels' => $months->map(fn ($date) => 'Tháng '.$date->format('m'))->values(),
            'chartData' => $months->map(fn ($date) => (int) ($revenueByMonth[$date->format('Y-m')] ?? 0))->values(),
            'categoryStats' => Category::withCount('products')->orderByDesc('products_count')->take(5)->get(),
            'bestSellers' => OrderItem::query()
                ->selectRaw('product_name, SUM(quantity) as units_sold, SUM(subtotal) as revenue')
                ->whereHas('order', fn ($query) => $query->where('status', 'completed'))
                ->groupBy('product_name')
                ->orderByDesc('units_sold')
                ->take(5)
                ->get(),
        ]);
    }
}
