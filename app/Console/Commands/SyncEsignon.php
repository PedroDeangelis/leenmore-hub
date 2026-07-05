<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Esignon\EsignonSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls completed ESIGNON (전자위임) documents for every active project that has
 * an ESIGNON link and writes the results onto the matching roster rows. Runs on
 * a schedule (see routes/console.php); pass `--project` to sync just one
 * (regardless of status) for testing.
 */
class SyncEsignon extends Command
{
    protected $signature = 'esignon:sync {--project= : Sync only this project id}';

    protected $description = 'Pull completed ESIGNON proxy documents and write them onto the matching roster rows';

    public function handle(EsignonSync $sync): int
    {
        $query = Project::query()
            ->whereNotNull('link_manage_id')
            ->where('link_manage_id', '!=', '');

        if ($projectId = $this->option('project')) {
            $query->whereKey($projectId);
        } else {
            $query->active();
        }

        $projects = $query->get();

        if ($projects->isEmpty()) {
            $this->info('No projects with an ESIGNON link to sync.');

            return self::SUCCESS;
        }

        $totalMatched = 0;
        $totalMissing = 0;
        $failed = 0;

        foreach ($projects as $project) {
            try {
                $pulled = $sync->pull($project);
                ['matched' => $matched, 'missing' => $missing] = $sync->match($project);

                $totalMatched += $matched;
                $totalMissing += $missing;

                $this->line("  [{$project->id}] {$project->title}: pulled {$pulled}, matched {$matched}, missing {$missing}");
            } catch (Throwable $e) {
                $failed++;
                Log::error("ESIGNON sync failed for project {$project->id}", ['exception' => $e]);
                $this->error("  [{$project->id}] {$project->title}: {$e->getMessage()}");
            }
        }

        $this->info("ESIGNON sync done: matched {$totalMatched}, missing {$totalMissing}".($failed > 0 ? ", failed {$failed}" : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
