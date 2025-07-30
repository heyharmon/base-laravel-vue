<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('protocol', 10)->default('https');
            $table->text('base_url');
            $table->enum('status', ['active','inactive','suspended'])->default('active');
            $table->json('crawl_settings')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->index(['status','last_checked_at']);
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
