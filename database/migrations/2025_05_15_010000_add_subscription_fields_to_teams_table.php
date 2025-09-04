<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->date('subscription_started_at')->nullable()->after('articles_limit');
            $table->string('billing_interval')->default('monthly')->after('subscription_started_at');
        });

        DB::table('teams')->update([
            'subscription_started_at' => now()->toDateString(),
            'billing_interval' => 'monthly',
        ]);
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['subscription_started_at', 'billing_interval']);
        });
    }
};
