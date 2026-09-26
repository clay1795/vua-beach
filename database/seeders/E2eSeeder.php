<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2eSeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::create([
            'site_name' => 'Vua Beach E2E',
            'tagline' => 'Cửa hàng kiểm thử biệt lập',
            'support_email' => 'support@example.test',
            'support_phone' => '0900000000',
        ]);
        $category = Category::create(['name' => 'Đồ bơi nữ', 'slug' => 'do-boi-nu-e2e']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Bộ bơi E2E xanh biển',
            'slug' => 'bo-boi-e2e-xanh-bien',
            'price' => 399000,
            'description' => 'Sản phẩm dành riêng cho kiểm thử hành trình mua hàng.',
            'image_url' => '/images/catalog/products/bo-boi-xanh-phoi-trang.webp',
            'status' => 'active',
            'is_featured' => true,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'path' => '/images/catalog/products/bo-lien-vay-den-phoi-ghi.jpg',
            'sort_order' => 1,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'E2E-XANH-M',
            'color' => 'Xanh biển',
            'size' => 'M',
            'stock' => 50,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'E2E-XANH-L',
            'color' => 'Xanh biển',
            'size' => 'L',
            'stock' => 50,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);
        User::create([
            'name' => 'Khách E2E',
            'username' => 'e2e_customer',
            'email' => 'customer@example.test',
            'password' => Hash::make('Customer!Pass123'),
            'phone' => '0901234567',
            'address' => '123 Đường Biển, Đà Nẵng',
            'email_verified_at' => now(),
        ]);
        User::create([
            'name' => 'Quản trị E2E',
            'username' => 'e2e_admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('Admin!Pass12345'),
            'phone' => '0907654321',
            'address' => 'Văn phòng E2E',
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);
    }
}
