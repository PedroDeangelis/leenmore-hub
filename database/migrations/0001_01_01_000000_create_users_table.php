<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Login accounts for the API portal, and the session storage behind them.
 *
 * These are *not* the 활동가 the API reports on — those live in Supabase's
 * `profiles` table and never log in here. This table is only the handful of
 * people who administer the portal or consume the API through it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');

            // 'admin' or 'viewer' — see App\Enums\UserRole.
            $table->string('role', 20)->default('viewer');

            // UI language; App\Http\Middleware\SetUserLocale::SUPPORTED.
            $table->string('locale', 5)->default('ko');

            // Set = the account cannot sign in, but is kept for the audit trail.
            $table->timestamp('deactivated_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
