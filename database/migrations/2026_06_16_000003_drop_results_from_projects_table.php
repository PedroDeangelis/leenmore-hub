<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The obsolete `projects.results` JSON column was replaced by the
 * `project_results` table. It also shadowed the Project::results() relationship,
 * so it is dropped here for any database created before that change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('projects', 'results')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('results');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('projects', 'results')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->json('results')->nullable()->after('status');
            });
        }
    }
};
