<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectResult;
use Illuminate\Database\Seeder;

/**
 * Backfill the default 판단 result set onto any project that has none.
 * Safe to run repeatedly. Run with: php artisan db:seed --class=ProjectResultSeeder
 */
class ProjectResultSeeder extends Seeder
{
    public function run(): void
    {
        Project::query()
            ->whereDoesntHave('results')
            ->each(fn (Project $project) => $project->results()->createMany(ProjectResult::defaultSet()));
    }
}
