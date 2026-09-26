<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('sku', 80)->nullable()->unique()->after('product_id');
            $table->unsignedInteger('low_stock_threshold')->default(5)->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn(['sku', 'low_stock_threshold']);
        });
    }
};
