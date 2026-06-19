<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Projects ("campaigns"). Columns follow audit/FINAL_DATABASE_SCHEMA_PROPOSAL.md §2,
 * merging the Supabase `project` (content) and PHP storage `project` (folder registry)
 * tables. The legacy mapping columns keep the future import idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status', 20)->default('draft');
            // Result definitions (판단) live in their own `project_results` table.
            $table->text('message')->nullable();
            $table->string('link_manage_id')->nullable();
            $table->unsignedBigInteger('shares_issued')->nullable();
            $table->unsignedBigInteger('shares_target')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            // Legacy mapping keys — kept after cutover for traceability.
            $table->unsignedBigInteger('supabase_id')->nullable()->unique();
            $table->unsignedBigInteger('storage_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
