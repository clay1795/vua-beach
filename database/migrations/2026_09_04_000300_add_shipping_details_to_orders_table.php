<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('shipping_fee')->default(0)->after('total_amount');
            $table->unsignedInteger('subtotal_amount')->default(0)->after('shipping_fee');
            $table->unsignedInteger('to_district_id')->nullable()->after('address');
            $table->string('to_ward_code', 20)->nullable()->after('to_district_id');
            $table->string('shipping_status')->default('pending')->after('status');
            $table->string('ghn_order_code')->nullable()->after('shipping_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_fee', 'subtotal_amount', 'to_district_id', 'to_ward_code', 'shipping_status', 'ghn_order_code']);
        });
    }
};
