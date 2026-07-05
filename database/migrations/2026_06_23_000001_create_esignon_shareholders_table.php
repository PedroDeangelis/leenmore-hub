<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Completed ESIGNON (전자위임) proxy documents pulled from docs.esignon.net by the
 * `esignon:sync` command — one row per signer per project link. The sync then
 * matches each row to a roster assignment (by name + 생년월일 6자리) and writes
 * `api_recipient_*` onto `project_shareholders`. Rows that never match a roster
 * entry stay `needs_sync = true` and surface as the "주주명부 누락" export sheet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esignon_shareholders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // ESIGNON identifiers: the project's campaign link and the per-signer document.
            $table->string('link_manage_id');
            $table->string('link_id');

            // Signer data parsed out of the ESIGNON spreadsheet.
            $table->string('identifier')->nullable();   // 생년월일 6자리 (DOB code)
            $table->string('name');
            $table->string('contact')->nullable();       // normalized phone
            $table->dateTime('completed_date')->nullable();

            // Change detection + roster-match state.
            $table->char('data_hash', 64);
            $table->boolean('needs_sync')->default(true);
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            // One row per completed document; drives the upsert.
            $table->unique(['project_id', 'link_id']);
            // Drives the match pass and the "주주명부 누락" export query.
            $table->index(['project_id', 'needs_sync']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esignon_shareholders');
    }
};
