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
        Schema::table('chats', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('role');
            $table->string('model')->nullable()->after('provider');
            $table->json('usage')->nullable()->after('annotations');
            $table->decimal('cost', 12, 6)->nullable()->after('usage');
            $table->decimal('price', 12, 6)->nullable()->after('cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropColumn(['provider', 'model', 'usage', 'cost', 'price']);
        });
    }
};
