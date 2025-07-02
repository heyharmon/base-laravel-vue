<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add the new boolean column
            $table->boolean('is_super_admin')->default(false)->after('id');
        });

        // Update existing super admin users
        DB::table('users')
            ->where('role', 'super_admin')
            ->update(['is_super_admin' => true]);

        Schema::table('users', function (Blueprint $table) {
            // Drop the old role column
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add back the role column
            $table->string('role')->nullable()->after('id');
        });

        // Update existing super admin users back to role
        DB::table('users')
            ->where('is_super_admin', true)
            ->update(['role' => 'super_admin']);

        Schema::table('users', function (Blueprint $table) {
            // Drop the is_super_admin column
            $table->dropColumn('is_super_admin');
        });
    }
};