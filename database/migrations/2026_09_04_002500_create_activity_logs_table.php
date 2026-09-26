<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->nullableMorphs('subject');
            $table->string('description', 500);
            $table->json('metadata')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
