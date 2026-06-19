<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('worker')->after('password');
            $table->string('phone')->nullable()->after('role');
            $table->boolean('email_receiver')->default(false)->after('phone');
            // Legacy key so the Supabase user import is idempotent.
            $table->uuid('supabase_uuid')->nullable()->unique()->after('email_receiver');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'email_receiver', 'supabase_uuid']);
        });
    }
};
