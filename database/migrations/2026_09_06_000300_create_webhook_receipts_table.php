<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_key', 191);
            $table->string('payload_hash', 64);
            $table->string('status', 20)->default('processing');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->json('response_body')->nullable();
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_key']);
            $table->index(['provider', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_receipts');
    }
};
