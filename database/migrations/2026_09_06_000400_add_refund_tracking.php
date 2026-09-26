<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('refunded_amount')->default(0)->after('discount_amount');
        });
        Schema::table('return_requests', function (Blueprint $table) {
            $table->unsignedInteger('refund_amount')->default(0)->after('admin_note');
            $table->timestamp('refund_processed_at')->nullable()->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropColumn(['refund_amount', 'refund_processed_at']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('refunded_amount');
        });
    }
};
