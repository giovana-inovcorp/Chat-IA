<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_collections', function (Blueprint $table) {
            $table->enum('status', ['empty', 'processing', 'ready', 'error'])
                ->default('empty')
                ->after('description');
        });

        // Backfill: calcula o estado das coleções já existentes
        DB::table('knowledge_collections')->pluck('id')->each(function ($id) {
            $statuses = DB::table('knowledge_documents')
                ->where('collection_id', $id)
                ->pluck('status');

            $status = match (true) {
                $statuses->isEmpty() => 'empty',
                $statuses->intersect(['pending', 'processing'])->isNotEmpty() => 'processing',
                $statuses->contains('error') => 'error',
                default => 'ready',
            };

            DB::table('knowledge_collections')->where('id', $id)->update(['status' => $status]);
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_collections', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
