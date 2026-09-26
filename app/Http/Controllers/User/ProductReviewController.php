<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'content' => ['nullable', 'string', 'max:1500'],
        ]);
        $order = Order::whereKey($data['order_id'])->where('user_id', $request->user()->id)->where('status', 'completed')
            ->whereHas('items', fn ($items) => $items->where('product_id', $product->id))->firstOrFail();
        ProductReview::updateOrCreate(
            ['user_id' => $request->user()->id, 'product_id' => $product->id, 'order_id' => $order->id],
            ['rating' => $data['rating'], 'content' => $data['content'] ?? null, 'is_visible' => true]
        );

        return back()->with('success', 'Cảm ơn bạn đã đánh giá sản phẩm.');
    }
}
