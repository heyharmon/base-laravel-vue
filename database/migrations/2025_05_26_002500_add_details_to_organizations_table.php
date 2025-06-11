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
        Schema::table('organizations', function (Blueprint $table) {
			$table->string('logo')->nullable()->after('website');
			$table->string('color')->nullable()->after('logo');
			$table->string('description')->nullable()->after('color');
			$table->longText('long_description')->nullable()->after('description');
			$table->string('industry')->nullable()->after('long_description');
			$table->string('city')->nullable()->after('industry');
			$table->string('state')->nullable()->after('city');
			$table->string('country')->nullable()->after('state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'logo',
                'color',
                'description',
                'long_description',
                'industry',
                'city',
                'state',
                'country',
            ]);
        });
    }
};
