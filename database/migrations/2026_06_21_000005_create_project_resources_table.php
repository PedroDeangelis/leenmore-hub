<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A project's "resource room" (프로젝트 자료실): per-project links and uploaded files
 * (PDF/images/CSV). Each row is either a link (url + title) or a file (file_path +
 * file_name + title). Ordered within a project by sort_order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('link'); // ResourceType: link | file
            $table->string('title');
            $table->text('url')->nullable();          // links
            $table->string('file_path')->nullable();  // files — disk-relative path on `local`
            $table->string('file_name')->nullable();  // original filename for display/download
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_resources');
    }
};
