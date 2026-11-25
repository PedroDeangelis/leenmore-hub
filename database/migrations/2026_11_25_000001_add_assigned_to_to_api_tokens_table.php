<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The migration squash folded `assigned_to` into the api_tokens create
 * migration, but databases created before the squash already had the table
 * without it — so minting a token there failed on insert. Add the column where
 * it is missing; on a fresh database the create migration already made it and
 * this is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('api_tokens', 'assigned_to')) {
            return;
        }

        Schema::table('api_tokens', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Intentionally empty: on a fresh database the column belongs to the
        // create migration, so dropping it here would leave the schema wrong.
    }
};
