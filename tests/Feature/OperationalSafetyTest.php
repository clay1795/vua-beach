<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperationalSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_concurrency_drill_is_forbidden_on_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('inventory:concurrency-test')
            ->expectsOutput('Từ chối chạy bài kiểm tra tranh chấp tồn kho trên Production. Hãy dùng database Staging riêng.')
            ->assertFailed();
    }

    public function test_preflight_identifies_each_active_product_without_a_variant(): void
    {
        $category = Category::create(['name' => 'Đồ bơi nữ', 'slug' => 'do-boi-nu']);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Bộ bơi thiếu phân loại',
            'slug' => 'bo-boi-thieu-phan-loai',
            'price' => 450000,
            'description' => 'Sản phẩm thật nhưng chưa cấu hình size và màu.',
            'image_url' => '/images/vua-beach-hero-v2.webp',
            'status' => 'active',
        ]);

        $this->artisan('vua-beach:preflight --strict')
            ->expectsOutputToContain('Sản phẩm đang bán thiếu biến thể size/màu: Bộ bơi thiếu phân loại.')
            ->assertFailed();
    }

    public function test_preflight_rejects_a_common_default_admin_password(): void
    {
        User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);

        $this->artisan('vua-beach:preflight --strict')
            ->expectsOutputToContain('Có tài khoản quản trị vẫn dùng mật khẩu mặc định hoặc quá phổ biến')
            ->assertFailed();
    }

    public function test_public_preflight_requires_verified_email_and_two_factor_for_every_admin(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');
        User::factory()->create([
            'is_admin' => true,
            'password' => Hash::make('A-unique-strong-admin-passphrase!2026'),
            'email_verified_at' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->artisan('vua-beach:preflight --strict')
            ->expectsOutputToContain('Có 1 quản trị viên chưa xác thực email.')
            ->expectsOutputToContain('Có 1 quản trị viên chưa hoàn tất 2FA.')
            ->assertFailed();
    }

    public function test_preflight_rejects_an_order_without_a_payment_ledger_entry(): void
    {
        Order::create([
            'order_code' => 'VB-NOLEDGER',
            'customer_name' => 'Khách',
            'email' => 'khach@example.test',
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'total_amount' => 300000,
            'payment_method' => 'cod',
        ]);

        $this->artisan('vua-beach:preflight --strict')
            ->expectsOutputToContain('Có 1 đơn chưa có dòng payment_transactions.')
            ->assertFailed();
    }
}
