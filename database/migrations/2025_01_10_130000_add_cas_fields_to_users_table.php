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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('cas_user')->default(false)->after('email_verified_at');
            $table->string('cas_username')->nullable()->after('cas_user');
            $table->string('cas_token')->nullable()->after('cas_username');
            $table->timestamp('cas_token_expires_at')->nullable()->after('cas_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cas_user', 'cas_username', 'cas_token', 'cas_token_expires_at']);
        });
    }
};