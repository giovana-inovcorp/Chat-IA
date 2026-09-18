<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
        });

        // Backfill: tenta obter o tamanho dos ficheiros já existentes em disco
        DB::table('knowledge_documents')->select('id', 'filename')->orderBy('id')->get()->each(function ($doc) {
            if (Storage::exists($doc->filename)) {
                DB::table('knowledge_documents')
                    ->where('id', $doc->id)
                    ->update(['file_size' => Storage::size($doc->filename)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->dropColumn('file_size');
        });
    }
};
