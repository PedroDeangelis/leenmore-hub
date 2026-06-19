<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Project results (판단) — the outcome labels a project defines. Replaces the
 * `projects.results` JSON column from the schema proposal with a real table so
 * each result is queryable, orderable and editable. `color` is one of the
 * App\Enums\ResultColor palette values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('gray');
            $table->boolean('contact_required')->default(false);
            $table->boolean('attachment_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_results');
    }
};
