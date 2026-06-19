<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A shareholder's assignment to one project's meeting: the per-project half of
 * the shareholders split (audit/FINAL_DATABASE_SCHEMA_PROPOSAL.md §3). Carries
 * everything specific to *this* meeting — shares, the 판단 result, notes, the
 * imported `prev_*` history snapshot, eSignon results, and a per-project contact
 * override (null → fall back to the person's base contact).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_shareholders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shareholder_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('shares')->nullable();
            $table->unsignedBigInteger('shares_total')->nullable();

            // Per-project overrides — null means "use the person's base value".
            $table->string('contact_info')->nullable();
            $table->string('contact_info_2')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('contact_worker')->nullable();

            // Current 판단 (a real result on this project); worker submissions drive it later.
            $table->foreignId('result_id')->nullable()->constrained('project_results')->nullOnDelete();
            $table->text('last_note')->nullable();

            // History snapshot imported from the prior meeting's spreadsheet columns.
            $table->string('prev_result', 100)->nullable();
            $table->text('prev_comment')->nullable();
            $table->text('prev_note')->nullable();

            $table->boolean('electronic_voting')->nullable();

            // Written by the eSignon sync.
            $table->string('api_recipient_contact')->nullable();
            $table->date('api_recipient_completion_date')->nullable();

            // List ordering + import provenance.
            $table->unsignedInteger('no')->nullable();
            $table->unsignedInteger('row_no')->nullable();
            $table->string('source_database')->nullable();

            // Legacy mapping — one Supabase shareholder row maps to one assignment.
            $table->unsignedBigInteger('supabase_id')->nullable()->unique();
            $table->timestamps();

            $table->unique(['project_id', 'shareholder_id']);
            $table->index('shareholder_id');
            $table->index(['project_id', 'result_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_shareholders');
    }
};
