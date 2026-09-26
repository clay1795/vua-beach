<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('vnpay_transaction_no')->nullable()->unique()->after('ghn_order_code');
            $table->string('vnpay_bank_code')->nullable()->after('vnpay_transaction_no');
            $table->string('vnpay_response_code', 10)->nullable()->after('vnpay_bank_code');
            $table->timestamp('vnpay_paid_at')->nullable()->after('vnpay_response_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['vnpay_transaction_no']);
            $table->dropColumn(['vnpay_transaction_no', 'vnpay_bank_code', 'vnpay_response_code', 'vnpay_paid_at']);
        });
    }
};
