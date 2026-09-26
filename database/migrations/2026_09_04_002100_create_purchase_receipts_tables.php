<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_code', 40)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at');
            $table->unsignedBigInteger('total_cost')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'received_at']);
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_cost')->default(0);
            $table->unsignedBigInteger('line_total')->default(0);
            $table->timestamps();

            $table->unique(['purchase_receipt_id', 'product_variant_id'], 'receipt_variant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
    }
};
