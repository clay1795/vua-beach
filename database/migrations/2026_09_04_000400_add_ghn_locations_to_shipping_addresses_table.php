<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_addresses', function (Blueprint $table) {
            $table->unsignedInteger('province_id')->nullable()->after('address');
            $table->string('province_name', 100)->nullable()->after('province_id');
            $table->unsignedInteger('district_id')->nullable()->after('province_name');
            $table->string('district_name', 100)->nullable()->after('district_id');
            $table->string('ward_code', 20)->nullable()->after('district_name');
            $table->string('ward_name', 100)->nullable()->after('ward_code');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_addresses', function (Blueprint $table) {
            $table->dropColumn(['province_id', 'province_name', 'district_id', 'district_name', 'ward_code', 'ward_name']);
        });
    }
};
