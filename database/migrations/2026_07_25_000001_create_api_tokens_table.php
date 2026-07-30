<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Bearer tokens for the read-only REST API (routes/api.php). An admin mints one
 * on the API tab, labels it with who it is for, hands the plaintext to that
 * client once, and can revoke or delete it later. Only the SHA-256 hash is
 * stored — the plaintext is shown exactly once, at creation, and never again.
 *
 * Distinct from Passport's oauth_* tables, which back the MCP server's OAuth
 * flow. This is deliberately a plain first-party token so the management UI can
 * own the full label/revoke/delete lifecycle without OAuth machinery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();

            // Who the token is for — free text an admin types, e.g. "회계법인 김앤장".
            $table->string('name');

            // SHA-256 of the plaintext; the plaintext itself is never stored.
            $table->string('token_hash', 64)->unique();

            // Last four plaintext characters, for recognising a token in the list.
            $table->string('last_four', 4);

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Blocked (revoked) but kept for the audit trail. Distinct from a hard delete.
            $table->timestamp('revoked_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
