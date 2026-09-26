<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponController extends AdminController
{
    public function index()
    {
        $this->authorizeAdmin();

        return view('admin.coupons.index', ['coupons' => Coupon::latest()->paginate(15)]);
    }

    public function create()
    {
        $this->authorizeAdmin();

        return view('admin.coupons.form', $this->formData(new Coupon));
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        [$data, $productIds, $categoryIds] = $this->data($request);
        $coupon = Coupon::create($data);
        $this->syncScope($coupon, $productIds, $categoryIds);
        $this->audit('coupon.created', $coupon, 'Tạo mã ưu đãi '.$coupon->code.'.');

        return redirect()->route('admin.coupons.index')->with('success', 'Đã tạo mã ưu đãi.');
    }

    public function edit(Coupon $coupon)
    {
        $this->authorizeAdmin();

        return view('admin.coupons.form', $this->formData($coupon));
    }

    public function update(Request $request, Coupon $coupon)
    {
        $this->authorizeAdmin();
        [$data, $productIds, $categoryIds] = $this->data($request, $coupon);
        $coupon->update($data);
        $this->syncScope($coupon, $productIds, $categoryIds);
        $this->audit('coupon.updated', $coupon, 'Cập nhật mã ưu đãi '.$coupon->code.'.');

        return redirect()->route('admin.coupons.index')->with('success', 'Đã cập nhật mã ưu đãi.');
    }

    public function destroy(Coupon $coupon)
    {
        $this->authorizeAdmin();
        $coupon->update(['is_active' => false]);
        $this->audit('coupon.disabled', $coupon, 'Ngừng sử dụng mã ưu đãi '.$coupon->code.'.');

        return back()->with('success', 'Mã ưu đãi đã được tắt.');
    }

    private function data(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:coupons,code'.($coupon ? ','.$coupon->id : '')],
            'name' => ['required', 'string', 'max:150'],
            'scope' => ['required', 'in:all,products,categories'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1', 'max:100000000'],
            'min_order_amount' => ['nullable', 'integer', 'min:0'],
            'max_discount_amount' => ['nullable', 'integer', 'min:1'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id', 'distinct'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id', 'distinct'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        if ($data['type'] === 'percent' && $data['value'] > 100) {
            return throw ValidationException::withMessages(['value' => 'Phần trăm giảm giá không được vượt quá 100%.']);
        }
        if ($data['scope'] === 'products' && empty($data['product_ids'])) {
            return throw ValidationException::withMessages(['product_ids' => 'Chọn ít nhất một sản phẩm áp dụng mã.']);
        }
        if ($data['scope'] === 'categories' && empty($data['category_ids'])) {
            return throw ValidationException::withMessages(['category_ids' => 'Chọn ít nhất một danh mục áp dụng mã.']);
        }
        $data['code'] = Str::upper($data['code']);
        $data['is_active'] = $request->boolean('is_active');
        $productIds = $data['product_ids'] ?? [];
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['product_ids'], $data['category_ids']);

        return [$data, $productIds, $categoryIds];
    }

    private function syncScope(Coupon $coupon, array $productIds, array $categoryIds): void
    {
        $coupon->products()->sync($coupon->scope === 'products' ? $productIds : []);
        $coupon->categories()->sync($coupon->scope === 'categories' ? $categoryIds : []);
    }

    private function formData(Coupon $coupon): array
    {
        $coupon->load('products:id', 'categories:id');

        return [
            'coupon' => $coupon,
            'products' => Product::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ];
    }
}
