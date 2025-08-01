<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('job_statuses', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_id')->nullable()->after('team_id');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->onDelete('cascade');
            $table->index(['team_id', 'campaign_id', 'status']);
        });
    }

    public function down()
    {
        Schema::table('job_statuses', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropColumn('campaign_id');
        });
    }
};
