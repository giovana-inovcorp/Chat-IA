<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('content', 500);
            $table->string('category', 50)->nullable();
            $table->enum('source', ['manual', 'suggested'])->default('manual');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('memory_message', function (Blueprint $table) {
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('memory_id')->constrained()->cascadeOnDelete();
            $table->primary(['message_id', 'memory_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_message');
        Schema::dropIfExists('memories');
    }
};
