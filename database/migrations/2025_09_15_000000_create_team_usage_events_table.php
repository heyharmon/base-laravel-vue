<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('team_usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('resource_type', 32);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->index(['team_id', 'resource_type', 'created_at']);
        });

        // Backfill existing responses
        DB::table('responses')
            ->join('prompts', 'responses.prompt_id', '=', 'prompts.id')
            ->select('prompts.team_id', 'responses.id as resource_id', 'responses.created_at')
            ->whereNotNull('prompts.team_id')
            ->orderBy('responses.id')
            ->chunk(500, function ($rows) {
                $now = now();
                $payload = [];

                foreach ($rows as $row) {
                    $payload[] = [
                        'team_id' => $row->team_id,
                        'resource_type' => 'response',
                        'resource_id' => $row->resource_id,
                        'quantity' => 1,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->created_at ?? $now,
                    ];
                }

                if (! empty($payload)) {
                    DB::table('team_usage_events')->insert($payload);
                }
            });

        // Backfill existing articles
        DB::table('articles')
            ->select('team_id', 'id as resource_id', 'created_at')
            ->whereNotNull('team_id')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                $now = now();
                $payload = [];

                foreach ($rows as $row) {
                    $payload[] = [
                        'team_id' => $row->team_id,
                        'resource_type' => 'article',
                        'resource_id' => $row->resource_id,
                        'quantity' => 1,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->created_at ?? $now,
                    ];
                }

                if (! empty($payload)) {
                    DB::table('team_usage_events')->insert($payload);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_usage_events');
    }
};
