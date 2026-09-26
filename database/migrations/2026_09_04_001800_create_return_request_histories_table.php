<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_request_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->string('source', 30);
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['return_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_histories');
    }
};
