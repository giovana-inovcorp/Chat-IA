<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assistants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('icon', 10)->nullable();
            $table->text('instructions')->nullable();
            $table->string('model')->default('openai/gpt-4o-mini');
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->integer('max_tokens')->default(1024);
            $table->boolean('is_memory_active')->default(true);
            $table->boolean('is_favorite')->default(false);
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistants');
    }
};
