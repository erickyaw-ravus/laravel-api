<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Allows lookup by token (we store hash of token in token column).
     */
    public function up(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->index('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropIndex(['token']);
        });
    }
};
