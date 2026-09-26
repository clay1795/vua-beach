<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        [$items, $total] = $this->items($request);

        return view('shop.cart', compact('items', 'total'));
    }

    public function add(Request $request)
    {
        $data = $request->validate(['variant_id' => 'required|exists:product_variants,id', 'quantity' => 'required|integer|min:1|max:10']);
        $variant = ProductVariant::active()->with('product')->findOrFail($data['variant_id']);
        if ($variant->product->status !== 'active') {
            return back()->withErrors(['variant_id' => 'Sản phẩm này hiện không còn được bán.']);
        }
        if ($variant->stock < $data['quantity']) {
            return back()->withErrors(['variant_id' => 'Số lượng tồn kho không đủ.']);
        }
        $cart = $request->session()->get('cart', []);
        $newQuantity = ($cart[$variant->id]['quantity'] ?? 0) + $data['quantity'];
        if ($newQuantity > $variant->stock || $newQuantity > 10) {
            return back()->withErrors(['variant_id' => 'Số lượng trong giỏ vượt quá tồn kho cho phép.']);
        }
        $cart[$variant->id] = ['quantity' => $newQuantity];
        $request->session()->put('cart', $cart);

        return redirect()->route('cart.index')->with('success', 'Đã thêm sản phẩm vào giỏ hàng.');
    }

    public function update(Request $request, int $variant)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:10']);
        $cart = $request->session()->get('cart', []);
        abort_unless(isset($cart[$variant]), 404);
        $productVariant = ProductVariant::active()->with('product')->findOrFail($variant);
        if ($productVariant->product->status !== 'active') {
            return back()->withErrors(['quantity' => 'Sản phẩm này hiện không còn được bán.']);
        }
        if ($productVariant->stock < $data['quantity']) {
            return back()->withErrors(['quantity' => 'Số lượng tồn kho không đủ.']);
        }
        $cart[$variant]['quantity'] = $data['quantity'];
        $request->session()->put('cart', $cart);
        if ($request->expectsJson()) {
            $price = $productVariant->product->display_price;

            return response()->json(['quantity' => $data['quantity'], 'subtotal' => $price * $data['quantity'], 'cart_count' => collect($cart)->sum('quantity')]);
        }

        return back()->with('success', 'Đã cập nhật giỏ hàng.');
    }

    public function remove(Request $request, int $variant)
    {
        $cart = $request->session()->get('cart', []);
        unset($cart[$variant]);
        $request->session()->put('cart', $cart);

        return back()->with('success', 'Đã xóa sản phẩm khỏi giỏ.');
    }

    public function items(Request $request, ?array $selectedVariantIds = null): array
    {
        $cart = $request->session()->get('cart', []);
        if ($selectedVariantIds !== null) {
            $selected = array_flip(array_map('intval', $selectedVariantIds));
            $cart = array_intersect_key($cart, $selected);
        }
        $variants = ProductVariant::active()->with('product.category')
            ->whereHas('product', fn ($query) => $query->where('status', 'active'))
            ->whereIn('id', array_keys($cart))
            ->get();
        $items = $variants->map(function ($variant) use ($cart) {
            $quantity = $cart[$variant->id]['quantity'];
            $price = $variant->product->display_price;

            return compact('variant', 'quantity', 'price') + ['subtotal' => $price * $quantity];
        });

        return [$items, $items->sum('subtotal')];
    }
}
