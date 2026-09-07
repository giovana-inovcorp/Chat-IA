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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('monthly_token_limit')->nullable()->default(null)->after('is_admin');
            $table->decimal('monthly_cost_limit_usd', 10, 4)->nullable()->default(null)->after('monthly_token_limit');
            $table->unsignedBigInteger('tokens_used_this_month')->default(0)->after('monthly_cost_limit_usd');
            $table->decimal('cost_used_this_month', 10, 6)->default(0)->after('tokens_used_this_month');
            $table->timestamp('usage_reset_at')->nullable()->after('cost_used_this_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'monthly_token_limit',
                'monthly_cost_limit_usd',
                'tokens_used_this_month',
                'cost_used_this_month',
                'usage_reset_at',
            ]);
        });
    }
};
