<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('momo_request_id')->nullable()->unique()->after('vnpay_paid_at');
            $table->string('momo_trans_id')->nullable()->unique()->after('momo_request_id');
            $table->string('momo_result_code', 10)->nullable()->after('momo_trans_id');
            $table->string('momo_payment_status', 30)->nullable()->after('momo_result_code');
            $table->timestamp('momo_paid_at')->nullable()->after('momo_payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['momo_request_id']);
            $table->dropUnique(['momo_trans_id']);
            $table->dropColumn(['momo_request_id', 'momo_trans_id', 'momo_result_code', 'momo_payment_status', 'momo_paid_at']);
        });
    }
};
