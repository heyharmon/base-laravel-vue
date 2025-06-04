<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            // Drop the old foreign keys
            $table->dropForeign('keywords_organization_id_foreign');
            $table->dropForeign('keywords_team_id_foreign');
            
            // Add new foreign keys with correct names
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            // Drop the new foreign keys
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['team_id']);
            
            // Add back the old foreign keys
            $table->foreign('organization_id', 'keywords_organization_id_foreign')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('team_id', 'keywords_team_id_foreign')->references('id')->on('teams')->onDelete('cascade');
        });
    }
};
