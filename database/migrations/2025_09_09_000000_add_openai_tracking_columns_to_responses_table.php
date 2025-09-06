<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            // rename columns if exist? we will keep existing columns names; but we may alter if needed
            if (!Schema::hasColumn('responses', 'parameters')) {
                $table->json('parameters')->nullable()->after('model');
            }
            if (!Schema::hasColumn('responses', 'use_flex_processing')) {
                $table->boolean('use_flex_processing')->default(false)->after('model');
            }
            if (!Schema::hasColumn('responses', 'response_metadata')) {
                $table->json('response_metadata')->nullable()->after('content');
            }
            if (!Schema::hasColumn('responses', 'error_code')) {
                $table->string('error_code')->nullable()->after('status');
            }
            if (!Schema::hasColumn('responses', 'error_message')) {
                $table->text('error_message')->nullable()->after('error_code');
            }
            if (!Schema::hasColumn('responses', 'retry_count')) {
                $table->integer('retry_count')->default(0)->after('error_message');
            }
            if (!Schema::hasColumn('responses', 'poll_count')) {
                $table->integer('poll_count')->default(0)->after('retry_count');
            }
            if (!Schema::hasColumn('responses', 'last_poll_at')) {
                $table->timestamp('last_poll_at')->nullable()->after('poll_count');
            }
            if (!Schema::hasColumn('responses', 'next_retry_at')) {
                $table->timestamp('next_retry_at')->nullable()->after('last_poll_at');
            }
            if (!Schema::hasColumn('responses', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('next_retry_at');
            }
            if (!Schema::hasColumn('responses', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }
            if (!Schema::hasColumn('responses', 'processing_time_seconds')) {
                $table->integer('processing_time_seconds')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('responses', 'tokens_used')) {
                $table->integer('tokens_used')->nullable()->after('processing_time_seconds');
            }
            if (!Schema::hasColumn('responses', 'cost')) {
                $table->decimal('cost', 10, 6)->nullable()->after('tokens_used');
            }
            // indexes
            $table->index(['status', 'next_retry_at']);
            $table->index(['prompt_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropIndex(['status', 'next_retry_at']);
            $table->dropIndex(['prompt_id', 'status']);
            $table->dropColumn([
                'parameters',
                'use_flex_processing',
                'response_metadata',
                'error_code',
                'error_message',
                'retry_count',
                'poll_count',
                'last_poll_at',
                'next_retry_at',
                'started_at',
                'completed_at',
                'processing_time_seconds',
                'tokens_used',
                'cost',
            ]);
        });
    }
};
