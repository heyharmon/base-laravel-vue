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
        Schema::table('teams', function (Blueprint $table) {
            $table->decimal('token_limit_cost', 12, 6)->nullable()->after('owner_id');
            $table->decimal('token_limit_price', 12, 6)->nullable()->after('token_limit_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['token_limit_cost', 'token_limit_price']);
        });
    }
};
