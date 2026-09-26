<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name')->default('Vua Beach');
            $table->string('tagline')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone', 30)->nullable();
            $table->timestamps();
        });

        DB::table('site_settings')->insert([
            'site_name' => 'Vua Beach',
            'tagline' => 'Đồ bơi hiện đại cho mọi hành trình mùa hè.',
            'support_email' => 'sp.doitheauto5s@gmail.com',
            'support_phone' => '0900 000 000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
