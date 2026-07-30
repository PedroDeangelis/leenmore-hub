<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Store the token plaintext encrypted (APP_KEY) alongside the hash, so an admin
 * can view a token again from the list instead of only once at creation. The
 * hash remains the lookup key for authentication; this column is only ever
 * decrypted for display on the management page. Nullable because tokens minted
 * before this column existed have no recoverable plaintext.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->text('token')->nullable()->after('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropColumn('token');
        });
    }
};
