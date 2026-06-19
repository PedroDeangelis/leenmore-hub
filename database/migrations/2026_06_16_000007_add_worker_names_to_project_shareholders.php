<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The import spreadsheet's 활동가 column lists assigned worker names (slash-
 * separated). Until the worker-assignment pivot lands, keep the raw names here
 * so the data isn't lost — they'll be linked to `users` later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_shareholders', function (Blueprint $table) {
            $table->json('worker_names')->nullable()->after('contact_worker');
        });
    }

    public function down(): void
    {
        Schema::table('project_shareholders', function (Blueprint $table) {
            $table->dropColumn('worker_names');
        });
    }
};
