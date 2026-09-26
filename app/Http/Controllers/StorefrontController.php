<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    public function home()
    {
        $preferredCategorySlugs = ['do-boi-nu', 'do-boi-nam', 'do-boi-tre-em'];
        $categories = Category::withCount('products')
            ->whereIn('slug', $preferredCategorySlugs)
            ->get()
            ->sortBy(fn (Category $category) => array_search($category->slug, $preferredCategorySlugs, true))
            ->values();

        return view('shop.home', [
            'categories' => $categories,
            'products' => Product::with(['category', 'variants' => fn ($query) => $query->active()])
                ->where('status', 'active')
                ->where('is_featured', true)
                ->latest('id')
                ->take(7)
                ->get(),
        ]);
    }

    public function products(Request $request)
    {
        $query = Product::with(['category', 'variants' => fn ($query) => $query->active()])->where('status', 'active');
        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.$request->q.'%');
        }
        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category));
        }
        if ($request->input('sort') === 'price_asc') {
            $query->orderByRaw('COALESCE(sale_price, price) asc');
        } elseif ($request->input('sort') === 'price_desc') {
            $query->orderByRaw('COALESCE(sale_price, price) desc');
        } else {
            $query->latest();
        }

        return view('shop.products', ['products' => $query->paginate(9)->withQueryString(), 'categories' => Category::all()]);
    }

    public function show(Request $request, Product $product)
    {
        abort_unless($product->status === 'active', 404);

        $reviewableOrders = collect();
        if ($request->user()?->hasVerifiedEmail()) {
            $reviewableOrders = Order::query()
                ->where('user_id', $request->user()->id)
                ->where('status', 'completed')
                ->whereHas('items', fn ($items) => $items->where('product_id', $product->id))
                ->latest('completed_at')
                ->get(['id', 'order_code', 'completed_at']);
        }

        return view('shop.show', [
            'product' => $product->load(['category', 'variants' => fn ($query) => $query->active(), 'images', 'reviews' => fn ($query) => $query->where('is_visible', true)->with('user')->latest()]),
            'related' => Product::with(['category', 'variants' => fn ($query) => $query->active()])
                ->where('status', 'active')
                ->where('category_id', $product->category_id)
                ->whereKeyNot($product->id)
                ->take(3)
                ->get(),
            'reviewableOrders' => $reviewableOrders,
        ]);
    }
}
