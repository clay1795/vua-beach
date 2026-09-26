<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('content')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'product_id', 'order_id']);
            $table->index(['product_id', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('wishlists');
    }
};
