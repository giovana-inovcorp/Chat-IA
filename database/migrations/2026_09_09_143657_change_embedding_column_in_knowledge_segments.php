<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE knowledge_segments DROP COLUMN embedding');
        DB::statement('ALTER TABLE knowledge_segments ADD COLUMN embedding LONGTEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE knowledge_segments DROP COLUMN embedding');
        DB::statement('ALTER TABLE knowledge_segments ADD COLUMN embedding LONGTEXT NULL');
    }
};
