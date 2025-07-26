<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_statuses', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('team_id')->constrained()->cascadeOnDelete();
            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::table('job_statuses', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropIndex(['campaign_id']);
            $table->dropColumn('campaign_id');
        });
    }
};
