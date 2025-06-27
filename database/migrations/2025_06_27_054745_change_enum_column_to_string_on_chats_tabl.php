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
			// Change the role column from enum to string
			$table->string('role', 50)->default('user')->change();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('chats', function (Blueprint $table) {
			// Revert back to the original enum column
			$table->enum('role', ['user', 'assistant', 'system'])->default('user')->change();
		});
	}
};
