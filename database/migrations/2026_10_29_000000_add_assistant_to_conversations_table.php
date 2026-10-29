<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('assistant_id')->nullable()->after('user_id')->constrained('assistants')->nullOnDelete();
            $table->json('assistant_snapshot')->nullable()->after('assistant_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['assistant_id']);
            $table->dropColumn(['assistant_id', 'assistant_snapshot']);
        });
    }
};
