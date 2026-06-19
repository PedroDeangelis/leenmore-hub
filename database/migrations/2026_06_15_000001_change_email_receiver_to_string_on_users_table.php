<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Was a boolean flag; now holds an optional alternate address that
            // a user's email notifications are sent to (falls back to `email`).
            $table->string('email_receiver')->nullable()->default(null)->change();
        });

        // The old boolean stored 0/1, which is meaningless as an address.
        DB::table('users')->update(['email_receiver' => null]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_receiver')->default(false)->change();
        });
    }
};
