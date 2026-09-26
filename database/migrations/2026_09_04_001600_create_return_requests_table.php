<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['refund', 'exchange']);
            $table->enum('status', ['requested', 'approved', 'rejected', 'received', 'completed', 'cancelled'])->default('requested');
            $table->string('reason', 255);
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
