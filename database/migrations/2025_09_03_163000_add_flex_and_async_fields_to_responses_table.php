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
        Schema::table('responses', function (Blueprint $table) {
            $table->string('provider_id')->nullable()->after('provider');
            $table->string('status')->nullable()->after('provider_id');
            $table->boolean('flex')->default(false)->after('model');

            // Optional: indexes to speed up lookups by provider id/status
            $table->index('provider_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropIndex(['provider_id']);
            $table->dropIndex(['status']);
            $table->dropColumn(['provider_id', 'status', 'flex']);
        });
    }
};
