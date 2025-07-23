<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('team_id')->constrained()->cascadeOnDelete();
        });
        Schema::table('prompts', function (Blueprint $table) {
            $table->foreignId('campaign_id')->after('team_id')->constrained()->cascadeOnDelete();
        });
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('campaign_id')->after('team_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
        });
        Schema::table('prompts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
        });
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
        });
    }
};
