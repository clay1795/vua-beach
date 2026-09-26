<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\CouponRedemptionService;
use App\Services\GHNService;
use App\Services\InventoryService;
use App\Services\MailFailureAlert;
use App\Services\MomoService;
use App\Services\OrderShipmentService;
use App\Services\OrderStateMachine;
use App\Services\VnpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(private CartController $cart) {}

    public function create(Request $request, GHNService $ghn, VnpayService $vnpay, MomoService $momo)
    {
        $selectedVariantIds = collect($request->input('selected_variants', []))->map(fn ($id) => (int) $id)->unique()->values()->all();
        if (empty($selectedVariantIds)) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Vui lòng chọn ít nhất một sản phẩm để thanh toán.']);
        }
        [$items, $total] = $this->cart->items($request, $selectedVariantIds);
        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Các sản phẩm đã chọn không còn trong giỏ hàng.']);
        }
        $addresses = $request->user()->shippingAddresses()->orderByDesc('is_default')->latest()->get();
        $checkoutToken = (string) Str::uuid();
        $request->session()->put('checkout_token', $checkoutToken);

        return view('shop.checkout', compact('items', 'total', 'addresses', 'selectedVariantIds', 'checkoutToken') + [
            'defaultAddress' => $addresses->firstWhere('is_default', true),
            'ghnConfigured' => $ghn->configured(),
            'momoAvailable' => $momo->configured(),
            'vnpayAvailable' => $vnpay->configured(),
            'defaultShippingFee' => (int) config('services.ghn.default_fee', 30000),
            'estimatedWeight' => max(300, $items->sum(fn ($item) => 200 * $item['quantity'])),
        ]);
    }

    public function store(Request $request, GHNService $ghn, VnpayService $vnpay, MomoService $momo, OrderShipmentService $shipments)
    {
        $data = $request->validate([
            'checkout_token' => ['required', 'uuid'],
            'selected_variants' => 'required|array|min:1',
            'selected_variants.*' => 'integer|exists:product_variants,id',
            'shipping_address_id' => [
                'nullable',
                'integer',
                Rule::exists('shipping_addresses', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
            'customer_name' => 'required|max:100',
            'email' => 'required|email',
            'phone' => 'required|max:20',
            'address' => 'required|max:1000',
            'note' => 'nullable|max:1000',
            'coupon_code' => 'nullable|string|max:60',
            'payment_method' => 'required|in:cod,momo,vnpay',
            'to_district_id' => 'nullable|integer',
            'to_ward_code' => 'nullable|string|max:20',
            'accept_terms' => 'accepted',
        ]);
        $sessionToken = (string) $request->session()->get('checkout_token', '');
        if ($sessionToken === '' || ! hash_equals($sessionToken, (string) $data['checkout_token'])) {
            return redirect()->route('cart.index')->withErrors(['checkout' => 'Phiên thanh toán đã hết hạn. Vui lòng mở lại trang thanh toán.']);
        }
        $completedCheckout = $request->session()->get('completed_checkouts.'.$sessionToken);
        if (is_array($completedCheckout) && filled($completedCheckout['url'] ?? null)) {
            return ($completedCheckout['external'] ?? false)
                ? redirect()->away($completedCheckout['url'])
                : redirect($completedCheckout['url']);
        }
        $selectedVariantIds = collect($data['selected_variants'])->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($data['payment_method'] === 'momo' && ! $momo->configured()) {
            return back()->withInput()->withErrors(['payment_method' => 'Thanh toán MoMo chưa được cấu hình. Vui lòng chọn thanh toán trực tiếp.']);
        }
        if ($data['payment_method'] === 'vnpay' && ! $vnpay->configured()) {
            return back()->withInput()->withErrors(['payment_method' => 'Thanh toán VNPAY chưa được cấu hình. Vui lòng chọn thanh toán trực tiếp.']);
        }
        [$items, $total] = $this->cart->items($request, $selectedVariantIds);
        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Giỏ hàng đang trống hoặc sản phẩm đã ngừng bán.']);
        }
        if (! empty($data['shipping_address_id'])) {
            $saved = $request->user()->shippingAddresses()->findOrFail($data['shipping_address_id']);
            $data['customer_name'] = $saved->recipient_name;
            $data['phone'] = $saved->phone;
            $data['address'] = $saved->address;
            $data['to_district_id'] = $saved->district_id ?? $data['to_district_id'];
            $data['to_ward_code'] = $saved->ward_code ?? $data['to_ward_code'];
        }
        $shippingFee = (int) config('services.ghn.default_fee', 30000);
        if ($ghn->configured()) {
            if (empty($data['to_district_id']) || empty($data['to_ward_code'])) {
                return back()->withInput()->withErrors(['to_district_id' => 'Vui lòng chọn đủ Tỉnh/Thành, Quận/Huyện và Phường/Xã để tính phí giao hàng.']);
            }
            $result = $ghn->calculateFee((int) $data['to_district_id'], (string) $data['to_ward_code'], max(300, $items->sum(fn ($item) => 200 * $item['quantity'])));
            if (($result['code'] ?? 0) !== 200 || ! isset($result['data']['total'])) {
                return back()->withInput()->withErrors(['to_district_id' => $result['message'] ?? 'Không thể tính phí vận chuyển từ GHN.']);
            }
            $shippingFee = (int) $result['data']['total'];
        }
        $couponCode = $data['coupon_code'] ?? null;
        unset($data['shipping_address_id'], $data['selected_variants'], $data['coupon_code'], $data['accept_terms']);
        $order = DB::transaction(function () use ($items, $total, $shippingFee, $data, $request, $couponCode) {
            [$coupon, $discount] = $this->resolveCoupon($couponCode, $total, $items, $request->user()->id, true);
            $order = Order::create($data + [
                'user_id' => $request->user()->id,
                'order_code' => 'VB-'.strtoupper(Str::random(7)),
                'coupon_code' => $coupon?->code,
                'subtotal_amount' => $total,
                'discount_amount' => $discount,
                'shipping_fee' => $shippingFee,
                'total_amount' => max(0, $total - $discount) + $shippingFee,
                'payment_status' => $data['payment_method'] === 'cod' ? 'unpaid' : 'pending',
            ]);
            foreach ($items as $item) {
                $variant = $item['variant'];
                app(InventoryService::class)->applyOnce(
                    $variant->id,
                    -$item['quantity'],
                    "order:{$order->id}:variant:{$variant->id}:reserve",
                    'order_placed',
                    $order->id,
                    'Đặt đơn '.$order->order_code,
                );
                $order->items()->create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'product_name' => $variant->product->name, 'product_image_url' => $variant->product->image_url, 'color' => $variant->color, 'size' => $variant->size, 'price' => $item['price'], 'quantity' => $item['quantity'], 'subtotal' => $item['subtotal']]);
            }
            // Online payments consume a coupon only after the gateway confirms payment.
            if ($coupon && $data['payment_method'] === 'cod') {
                app(CouponRedemptionService::class)->consumeForOrder($order);
            }
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'pending',
                'source' => 'customer',
                'changed_by_user_id' => $request->user()->id,
                'note' => 'Khách hàng đã đặt đơn.',
            ]);
            $order->paymentTransactions()->create([
                'gateway' => $data['payment_method'],
                'amount' => $order->total_amount,
                'status' => 'pending',
                'message' => $data['payment_method'] === 'cod' ? 'Thanh toán khi nhận hàng.' : null,
            ]);

            return $order;
        });
        $paymentTransaction = $order->paymentTransactions()->latest('id')->firstOrFail();
        $momoPayUrl = null;
        $momoError = null;
        if ($order->payment_method === 'momo') {
            try {
                $payment = $momo->createPayment($order, $paymentTransaction, $request);
                $momoPayUrl = $payment['pay_url'];
            } catch (\Throwable $exception) {
                DB::transaction(function () use ($order): void {
                    $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
                    if ($locked->payment_status === 'pending') {
                        app(OrderStateMachine::class)->transitionPayment($locked, 'failed', [
                            'momo_payment_status' => 'creation_failed',
                        ]);
                    }
                });
                $momoError = $exception->getMessage();
            }
        }
        $shipment = null;
        if ($order->payment_method === 'cod') {
            $shipment = $shipments->create($order, $ghn, 'checkout');
        }
        $cart = $request->session()->get('cart', []);
        foreach ($selectedVariantIds as $variantId) {
            unset($cart[$variantId]);
        }
        $request->session()->put('cart', $cart);
        // The order is committed now. Queue this for every successful payment method,
        // including MoMo before its early redirect to the gateway.
        try {
            Mail::to($order->email)->queue(new OrderPlacedMail($order->load('items')));
        } catch (\Throwable $exception) {
            app(MailFailureAlert::class)->report('mail_enqueue_failed', OrderPlacedMail::class, $exception, ['order_id' => $order->id]);
        }
        if ($momoPayUrl) {
            $request->session()->put('completed_checkouts.'.$sessionToken, ['url' => $momoPayUrl, 'external' => true, 'order_id' => $order->id]);

            return redirect()->away($momoPayUrl);
        }
        if ($momoError) {
            $orderUrl = route('orders.show', $order);
            $request->session()->put('completed_checkouts.'.$sessionToken, ['url' => $orderUrl, 'external' => false, 'order_id' => $order->id]);

            return redirect()->route('orders.show', $order)
                ->withErrors(['payment' => $momoError.' Đơn hàng vẫn được giữ để bạn thanh toán lại.']);
        }
        if ($order->payment_method === 'vnpay') {
            $paymentUrl = $vnpay->paymentUrl($order, $request, $paymentTransaction);
            $request->session()->put('completed_checkouts.'.$sessionToken, ['url' => $paymentUrl, 'external' => true, 'order_id' => $order->id]);

            return redirect()->away($paymentUrl);
        }

        $orderUrl = route('orders.show', $order);
        $request->session()->put('completed_checkouts.'.$sessionToken, ['url' => $orderUrl, 'external' => false, 'order_id' => $order->id]);

        $message = 'Đặt hàng thành công! Mã đơn: '.$order->order_code;
        if (in_array($shipment['status'] ?? null, ['created', 'already_created'], true)) {
            $message .= ' Mã vận đơn GHN: '.$shipment['order_code'].'.';
        }

        $response = redirect()->route('orders.show', $order)->with('success', $message);
        if (($shipment['status'] ?? null) === 'failed') {
            $response->with('warning', 'Đơn đã được ghi nhận nhưng GHN chưa thể tạo vận đơn tự động. Cửa hàng sẽ thử lại.');
        }

        return $response;
    }

    public function orders(Request $request)
    {
        return view('orders.index', ['orders' => $request->user()->orders()->with('items.product')->latest()->paginate(10)]);
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->is_admin, 403);

        return view('orders.show', ['order' => $order->load('items.product', 'statusHistories.changedBy', 'returnRequests', 'paymentTransactions')]);
    }

    public function coupon(Request $request)
    {
        $data = $request->validate([
            'selected_variants' => ['required', 'array', 'min:1'],
            'selected_variants.*' => ['integer', 'exists:product_variants,id'],
            'coupon_code' => ['required', 'string', 'max:60'],
        ]);
        [$items, $subtotal] = $this->cart->items($request, collect($data['selected_variants'])->map(fn ($id) => (int) $id)->unique()->values()->all());
        if ($items->isEmpty()) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm hợp lệ trong giỏ.'], 422);
        }
        try {
            [$coupon, $discount] = $this->resolveCoupon($data['coupon_code'], $subtotal, $items, $request->user()->id);

            return response()->json([
                'code' => $coupon->code,
                'name' => $coupon->name,
                'discount' => $discount,
                'subtotal' => $subtotal,
            ]);
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->validator->errors()->first('coupon_code')], 422);
        }
    }

    public function cancel(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        DB::transaction(function () use ($order, $request) {
            $lockedOrder = Order::query()->with('items')->lockForUpdate()->findOrFail($order->id);
            if ($lockedOrder->status !== 'pending' || $lockedOrder->payment_method !== 'cod' || $lockedOrder->payment_status === 'paid' || filled($lockedOrder->ghn_order_code)) {
                throw ValidationException::withMessages(['order' => 'Đơn này đã được xử lý hoặc đã thanh toán, vui lòng liên hệ cửa hàng để được hỗ trợ.']);
            }
            foreach ($lockedOrder->items as $item) {
                if (! $item->product_variant_id) {
                    continue;
                }
                app(InventoryService::class)->applyOnce($item->product_variant_id, $item->quantity, "order:{$lockedOrder->id}:variant:{$item->product_variant_id}:release", 'order_cancelled_by_customer', $lockedOrder->id, 'Khách hủy đơn '.$lockedOrder->order_code);
            }
            app(OrderStateMachine::class)->transition($lockedOrder, 'cancelled', 'customer', $request->user()->id, 'Khách hàng đã hủy đơn khi đang chờ xác nhận.');
        });

        try {
            Mail::to($order->email)->queue(new OrderStatusUpdatedMail($order->fresh()));
        } catch (\Throwable $exception) {
            app(MailFailureAlert::class)->report('mail_enqueue_failed', OrderStatusUpdatedMail::class, $exception, ['order_id' => $order->id]);
        }

        return back()->with('success', 'Đơn hàng đã được hủy và tồn kho đã được hoàn lại.');
    }

    private function resolveCoupon(?string $code, int $subtotal, $items, int $userId, bool $lock = false): array
    {
        if (blank($code)) {
            return [null, 0];
        }

        $query = Coupon::query()->where('code', strtoupper(trim($code)));
        if ($lock) {
            $query->lockForUpdate();
        }
        $coupon = $query->first();

        if (! $coupon) {
            throw ValidationException::withMessages(['coupon_code' => 'Mã ưu đãi không hợp lệ, đã hết hạn hoặc chưa đủ điều kiện áp dụng.']);
        }

        $eligibleSubtotal = $this->eligibleCouponSubtotal($coupon, $items);
        $perUserUsage = $coupon->per_user_limit
            ? CouponUsage::where('coupon_id', $coupon->id)->where('user_id', $userId)->count()
            : 0;
        if (! $coupon->isUsableFor($eligibleSubtotal) || $eligibleSubtotal === 0 || ($coupon->per_user_limit && $perUserUsage >= $coupon->per_user_limit)) {
            throw ValidationException::withMessages(['coupon_code' => 'Mã ưu đãi không hợp lệ, đã hết hạn, không áp dụng cho sản phẩm này hoặc bạn đã dùng hết lượt.']);
        }

        return [$coupon, $coupon->discountFor($eligibleSubtotal)];
    }

    private function eligibleCouponSubtotal(Coupon $coupon, $items): int
    {
        if ($coupon->scope === 'all') {
            return (int) $items->sum('subtotal');
        }
        if ($coupon->scope === 'products') {
            $productIds = $coupon->products()->pluck('products.id')->all();

            return (int) $items->filter(fn ($item) => in_array($item['variant']->product_id, $productIds, true))->sum('subtotal');
        }
        if ($coupon->scope === 'categories') {
            $categoryIds = $coupon->categories()->pluck('categories.id')->all();

            return (int) $items->filter(fn ($item) => in_array($item['variant']->product->category_id, $categoryIds, true))->sum('subtotal');
        }

        return 0;
    }
}
