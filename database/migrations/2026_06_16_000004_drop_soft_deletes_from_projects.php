<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The project lifecycle is now a single static `status` (App\Enums\ProjectStatus:
 * draft|publish|archived|deleted). "Archived" replaces the old soft-delete, and
 * "deleted" rows are hidden by a model global scope instead of `deleted_at`.
 *
 * Existing soft-deleted rows carried the old "archived" meaning, so promote them
 * to status = 'archived' before the column goes away.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('projects', 'deleted_at')) {
            DB::table('projects')
                ->whereNotNull('deleted_at')
                ->update(['status' => 'archived']);

            Schema::table('projects', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('projects', 'deleted_at')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->softDeletes();
            });

            // Best-effort reversal: re-trash today's archived rows. The original
            // deletion timestamp is unrecoverable, so fall back to updated_at.
            DB::table('projects')
                ->where('status', 'archived')
                ->update([
                    'deleted_at' => DB::raw('updated_at'),
                    'status' => 'draft',
                ]);
        }
    }
};
