<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A field worker's activity report for one shareholder on one project
 * (audit/FINAL_DATABASE_SCHEMA_PROPOSAL.md §5). Each submission is the per-visit
 * record — the 판단 chosen, contact captured, free-text note, and uploaded files.
 * The shareholder's *current* result lives on `project_shareholders.result_id`;
 * a submission is the immutable history that drives it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();

            // Canonical link is the assignment; project_id is denormalized so the
            // admin 판단 결과 현황 tallies can group by project without a join.
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_shareholder_id')->constrained()->cascadeOnDelete();

            // The worker; name kept denormalized so history survives a user delete.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();

            $table->date('date')->nullable();
            // Snapshot of the chosen ProjectResult name (a project's results may change).
            $table->string('result', 100)->nullable();
            $table->string('contact')->nullable();
            $table->boolean('privacy_consent')->default(false);
            $table->text('note')->nullable();

            // Disk-relative paths.
            $table->json('files')->nullable();
            $table->json('privacy_consent_files')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'created_at']);
            $table->index('project_shareholder_id');
            $table->index('user_id');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
