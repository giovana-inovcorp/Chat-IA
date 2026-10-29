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
        Schema::create('assistant_knowledge_collection', function (Blueprint $table) {
            $table->foreignId('assistant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('knowledge_collections')->cascadeOnDelete();
            $table->primary(['assistant_id', 'collection_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_knowledge_collection');
    }
};
