<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Bearer tokens for the read-only REST API (routes/api.php). An admin mints one,
 * labels it with who it is for, hands the plaintext to that client, and can
 * revoke or delete it later.
 *
 * Both the hash and an encrypted copy of the plaintext are stored: the hash is
 * the lookup key for authentication, the encrypted copy only ever decrypted for
 * display on the management page so an admin can retrieve a key they already
 * issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();

            // Who the token is for — free text an admin types, e.g. "회계법인 김앤장".
            $table->string('name');

            // SHA-256 of the plaintext; the authentication lookup key.
            $table->string('token_hash', 64)->unique();

            // The plaintext, encrypted with APP_KEY, for re-display only.
            $table->text('token')->nullable();

            // Last four plaintext characters, for recognising a token in the list.
            $table->string('last_four', 4);

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Blocked (revoked) but kept for the audit trail. Distinct from a hard delete.
            $table->timestamp('revoked_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            /*
             * The portal account this key belongs to, if any. A viewer may only
             * see and test with keys assigned to them; admins see every key.
             * Null means the key belongs to an outside consumer with no login
             * here, which is the common case.
             */
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
