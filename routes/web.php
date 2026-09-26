<?php

use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\PurchaseReceiptController as AdminPurchaseReceiptController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReturnRequestController as AdminReturnRequestController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\SupplierController as AdminSupplierController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\Payment\MomoController;
use App\Http\Controllers\Payment\VnpayController;
use App\Http\Controllers\Shipping\GhnWebhookController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\User\CartController;
use App\Http\Controllers\User\CheckoutController;
use App\Http\Controllers\User\GHNController;
use App\Http\Controllers\User\ProductReviewController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\ReturnRequestController as UserReturnRequestController;
use App\Http\Controllers\User\WishlistController;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', function () {
    return response("User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /tai-khoan\nDisallow: /gio-hang\nDisallow: /thanh-toan\nSitemap: ".url('/sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

Route::get('/sitemap.xml', function () {
    $urls = collect([
        ['loc' => route('home'), 'lastmod' => now()],
        ['loc' => route('products.index'), 'lastmod' => now()],
    ])->merge(
        Category::query()->select(['slug', 'updated_at'])->get()->map(fn (Category $category) => [
            'loc' => route('products.index', ['category' => $category->slug]),
            'lastmod' => $category->updated_at,
        ])
    )->merge(
        Product::query()->where('status', 'active')->select(['slug', 'updated_at'])->get()->map(fn (Product $product) => [
            'loc' => route('products.show', $product),
            'lastmod' => $product->updated_at,
        ])
    );

    return response()->view('seo.sitemap', compact('urls'), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
})->name('sitemap');

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/health', HealthController::class)->middleware('throttle:6,1')->name('health');
Route::get('/san-pham', [StorefrontController::class, 'products'])->name('products.index');
Route::get('/san-pham/{product:slug}', [StorefrontController::class, 'show'])->name('products.show');

Route::get('/gio-hang', [CartController::class, 'index'])->name('cart.index');
Route::get('/thanh-toan/vnpay/ket-qua', [VnpayController::class, 'return'])->name('vnpay.return');
Route::match(['get', 'post'], '/thanh-toan/vnpay/ipn', [VnpayController::class, 'ipn'])->middleware(['webhook.payload', 'throttle:webhooks'])->name('vnpay.ipn');
Route::get('/thanh-toan/momo/ket-qua', [MomoController::class, 'return'])->name('momo.return');
Route::post('/thanh-toan/momo/ipn', [MomoController::class, 'ipn'])->middleware(['webhook.payload', 'throttle:webhooks'])->name('momo.ipn');
Route::post('/van-chuyen/ghn/webhook', GhnWebhookController::class)->middleware(['webhook.payload', 'throttle:webhooks'])->name('ghn.webhook');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/gio-hang', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/gio-hang/{variant}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/gio-hang/{variant}', [CartController::class, 'remove'])->name('cart.remove');
});

Route::middleware('guest')->group(function () {
    Route::get('/dang-nhap', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/dang-nhap', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
    Route::get('/dang-ky', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/dang-ky', [AuthController::class, 'register'])->middleware('throttle:registration')->name('register.store');
    Route::get('/quen-mat-khau', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/quen-mat-khau', [PasswordResetController::class, 'sendLink'])->middleware('throttle:password-reset-link')->name('password.email');
    Route::get('/dat-lai-mat-khau/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/dat-lai-mat-khau', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset')->name('password.update');
});
Route::post('/dang-xuat', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/xac-thuc-email', fn () => view('auth.verify-email'))->name('verification.notice');
    Route::get('/xac-thuc-email/{id}/{hash}', function (EmailVerificationRequest $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('home');
        }
        $request->fulfill();

        return redirect()->route('home')->with('success', 'Email đã được xác thực thành công.');
        // The verification signature is intentionally relative so one locally running app can be
        // reached through localhost or a temporary HTTPS tunnel without invalidating the link.
    })->middleware(['signed:relative', 'throttle:6,1'])->name('verification.verify');
    Route::post('/xac-thuc-email/gui-lai', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('home');
        }
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent')->with('verification_sent_at', now()->timestamp);
    })->middleware('throttle:verification-resend')->name('verification.send');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('bao-mat')->name('two-factor.')->group(function () {
    Route::get('/xac-thuc-hai-lop', [TwoFactorController::class, 'setup'])->name('setup');
    Route::post('/xac-thuc-hai-lop', [TwoFactorController::class, 'confirm'])->middleware('throttle:5,1')->name('confirm');
    Route::get('/xac-thuc-hai-lop/kiem-tra', [TwoFactorController::class, 'challenge'])->name('challenge');
    Route::post('/xac-thuc-hai-lop/kiem-tra', [TwoFactorController::class, 'verify'])->middleware('throttle:5,1')->name('verify');
    Route::delete('/xac-thuc-hai-lop', [TwoFactorController::class, 'disable'])->middleware('throttle:5,1')->name('disable');
});

Route::middleware('auth')->group(function () {
    Route::get('/tai-khoan', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/yeu-thich', [WishlistController::class, 'index'])->middleware('verified')->name('wishlist.index');
    Route::post('/yeu-thich/{product}', [WishlistController::class, 'toggle'])->middleware('verified')->name('wishlist.toggle');
    Route::post('/san-pham/{product}/danh-gia', [ProductReviewController::class, 'store'])->middleware('verified')->name('reviews.store');
    Route::patch('/tai-khoan', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/tai-khoan/dia-chi', [ProfileController::class, 'storeAddress'])->name('addresses.store');
    Route::patch('/tai-khoan/dia-chi/{shippingAddress}', [ProfileController::class, 'updateAddress'])->name('addresses.update');
    Route::patch('/tai-khoan/dia-chi/{shippingAddress}/mac-dinh', [ProfileController::class, 'setDefaultAddress'])->name('addresses.default');
    Route::delete('/tai-khoan/dia-chi/{shippingAddress}', [ProfileController::class, 'destroyAddress'])->name('addresses.destroy');
    Route::get('/thanh-toan', [CheckoutController::class, 'create'])->middleware('verified')->name('checkout.create');
    Route::post('/thanh-toan', [CheckoutController::class, 'store'])->middleware('verified')->block(30, 15)->name('checkout.store');
    Route::post('/thanh-toan/kiem-tra-ma', [CheckoutController::class, 'coupon'])->middleware('verified')->name('checkout.coupon');
    Route::get('/don-hang-cua-toi', [CheckoutController::class, 'orders'])->middleware('verified')->name('orders.index');
    Route::get('/don-hang/{order}', [CheckoutController::class, 'show'])->middleware('verified')->name('orders.show');
    Route::post('/don-hang/{order}/thanh-toan-lai/momo', [MomoController::class, 'payAgain'])->middleware('verified')->block(30, 15)->name('orders.momo.retry');
    Route::post('/don-hang/{order}/huy', [CheckoutController::class, 'cancel'])->middleware('verified')->name('orders.cancel');
    Route::get('/don-hang/{order}/doi-tra', [UserReturnRequestController::class, 'create'])->middleware('verified')->name('returns.create');
    Route::post('/don-hang/{order}/doi-tra', [UserReturnRequestController::class, 'store'])->middleware('verified')->name('returns.store');

    Route::prefix('dia-diem')->name('locations.')->middleware('verified')->group(function () {
        Route::get('/tinh-thanh', [GHNController::class, 'provinces'])->name('provinces');
        Route::get('/quan-huyen/{provinceId}', [GHNController::class, 'districts'])->whereNumber('provinceId')->name('districts');
        Route::get('/phuong-xa/{districtId}', [GHNController::class, 'wards'])->whereNumber('districtId')->name('wards');
        Route::post('/phi-van-chuyen', [GHNController::class, 'fee'])->name('fee');
    });
});

Route::middleware(['auth', 'verified', 'admin', 'admin.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('categories', AdminCategoryController::class)->except('show');
    Route::resource('products', AdminProductController::class)->except('show');
    Route::resource('coupons', AdminCouponController::class)->except('show');
    Route::resource('suppliers', AdminSupplierController::class)->except('show');
    Route::resource('purchase-receipts', AdminPurchaseReceiptController::class)->only(['index', 'create', 'store', 'show']);
    Route::delete('products/{product}/images/{image}', [AdminProductController::class, 'destroyImage'])->name('products.images.destroy');
    Route::get('inventory', [AdminInventoryController::class, 'index'])->name('inventory.index');
    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders-export', [AdminOrderController::class, 'export'])->name('orders.export');
    Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
    Route::post('orders/{order}/xac-nhan-hoan-tien', [AdminOrderController::class, 'confirmRefund'])->name('orders.confirm-refund');
    Route::post('orders/{order}/dong-bo-ghn', [AdminOrderController::class, 'syncGhn'])->name('orders.sync-ghn');
    Route::get('returns', [AdminReturnRequestController::class, 'index'])->name('returns.index');
    Route::get('returns/{returnRequest}', [AdminReturnRequestController::class, 'show'])->name('returns.show');
    Route::patch('returns/{returnRequest}', [AdminReturnRequestController::class, 'update'])->name('returns.update');
    Route::resource('users', AdminUserController::class);
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/charts', [ReportController::class, 'charts'])->name('reports.charts');
    Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('finance/transactions', [FinanceController::class, 'transactions'])->name('finance.transactions');
    Route::get('finance/export', [FinanceController::class, 'export'])->name('finance.export');
    Route::patch('finance/{order}/status', [FinanceController::class, 'updateStatus'])->name('finance.update-status');
    Route::patch('users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role');
    Route::get('activity-logs', [AdminActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('settings', [SiteSettingController::class, 'edit'])->name('settings.edit');
    Route::patch('settings', [SiteSettingController::class, 'update'])->name('settings.update');
});

Route::view('/chinh-sach-giao-hang', 'shop.policy', ['policy' => 'shipping'])->name('policies.shipping');
Route::view('/chinh-sach-doi-tra', 'shop.policy', ['policy' => 'returns'])->name('policies.returns');
Route::view('/dieu-khoan-mua-hang', 'shop.policy', ['policy' => 'terms'])->name('policies.terms');
