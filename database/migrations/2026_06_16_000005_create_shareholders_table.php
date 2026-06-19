<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Shareholders as global *people* — identity that stays stable across projects.
 * Everything meeting-specific (shares, result, per-project contact, …) lives on
 * `project_shareholders` instead (audit/FINAL_DATABASE_SCHEMA_PROPOSAL.md §3).
 *
 * A person is identified across projects by `registration` (unique when present);
 * `date_of_birth_code` is the import-time fallback matcher, so it is only indexed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shareholders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('registration', 100)->nullable()->unique();
            $table->string('sex', 10)->nullable();
            $table->string('person_type', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('date_of_birth_code', 10)->nullable();
            $table->string('code', 100)->nullable();
            // Base contact details — a project assignment may override these.
            $table->string('contact_info')->nullable();
            $table->string('address', 500)->nullable();
            $table->timestamps();

            $table->index('date_of_birth_code');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shareholders');
    }
};
