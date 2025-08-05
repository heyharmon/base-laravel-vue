<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('google_place_id')->unique()->nullable();
            $table->string('banner')->nullable();
            $table->decimal('score', 2, 1)->nullable();
            $table->integer('reviews')->nullable();
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('category')->nullable();
            $table->text('map_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['city', 'state']);
            $table->index('category');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
