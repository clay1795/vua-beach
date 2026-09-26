<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        return view('profile.wishlist', ['wishlists' => $request->user()->wishlists()->with(['product.variants'])->latest()->paginate(12)]);
    }

    public function toggle(Request $request, Product $product)
    {
        abort_unless($product->status === 'active', 404);
        $existing = $request->user()->wishlists()->where('product_id', $product->id)->first();
        if ($existing) {
            $existing->delete();

            return back()->with('success', 'Đã bỏ sản phẩm khỏi yêu thích.');
        }
        Wishlist::create(['user_id' => $request->user()->id, 'product_id' => $product->id]);

        return back()->with('success', 'Đã thêm sản phẩm vào yêu thích.');
    }
}
