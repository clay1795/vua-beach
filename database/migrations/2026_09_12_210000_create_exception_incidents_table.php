<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exception_incidents', function (Blueprint $table): void {
            $table->id();
            $table->char('fingerprint', 64)->unique();
            $table->string('exception_class');
            $table->string('location')->nullable();
            $table->string('route')->nullable();
            $table->string('environment', 32);
            $table->unsignedBigInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent()->index();
            $table->timestamp('last_alerted_at')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exception_incidents');
    }
};
