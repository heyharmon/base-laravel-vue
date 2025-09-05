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
            $table->unsignedInteger('initial_attempts')->default(0)->after('usage');
            $table->unsignedInteger('poll_attempts')->default(0)->after('initial_attempts');
            $table->timestamp('last_polled_at')->nullable()->after('poll_attempts');
            $table->timestamp('next_poll_at')->nullable()->after('last_polled_at');
            $table->string('error_code')->nullable()->after('next_poll_at');
            $table->string('processing_error_code')->nullable()->after('error_code');
            $table->text('processing_error_message')->nullable()->after('processing_error_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropColumn([
                'initial_attempts',
                'poll_attempts',
                'last_polled_at',
                'next_poll_at',
                'error_code',
                'processing_error_code',
                'processing_error_message',
            ]);
        });
    }
};

