<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bot_accessibility_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_agent_id')->constrained()->onDelete('cascade');
            $table->text('url');
            $table->string('url_hash', 64)->index();
            $table->boolean('robots_txt_allowed')->nullable();
            $table->integer('robots_txt_status_code')->nullable();
            $table->text('robots_txt_content')->nullable();
            $table->json('robots_txt_rules')->nullable();
            $table->boolean('http_accessible')->nullable();
            $table->integer('http_status_code')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->text('response_headers')->nullable();
            $table->boolean('firewall_detected')->default(false);
            $table->string('blocking_method')->nullable();
            $table->string('waf_type')->nullable();
            $table->decimal('detection_confidence', 3, 2)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index(['website_id','checked_at']);
            $table->index(['user_agent_id','robots_txt_allowed']);
            $table->index(['http_accessible','firewall_detected'], 'bar_http_firewall_idx');
            $table->unique(['website_id','url_hash','user_agent_id','checked_at'], 'unique_result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_accessibility_results');
    }
};
