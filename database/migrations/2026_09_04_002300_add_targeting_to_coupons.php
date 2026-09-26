<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('scope', 20)->default('all')->after('name');
            $table->unsignedInteger('per_user_limit')->nullable()->after('usage_limit');
        });
        Schema::create('coupon_product', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'product_id']);
        });
        Schema::create('category_coupon', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'category_id']);
        });
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['coupon_id', 'user_id']);
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('category_coupon');
        Schema::dropIfExists('coupon_product');
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['scope', 'per_user_limit']);
        });
    }
};
