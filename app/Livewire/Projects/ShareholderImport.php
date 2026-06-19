<?php

namespace App\Livewire\Projects;

use App\Imports\ShareholderImporter;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Uploads a CSV/XLSX roster and imports it into a project. The work runs in the
 * normal request cycle, chunked across a poll — no queue worker needed — so the
 * progress bar tracks the import even for very large files:
 *
 *  start()   stores the upload
 *  step()    first call parses the file into cache chunks ("Reading the file…"),
 *            each later call imports one chunk and advances the bar.
 */
class ShareholderImport extends Component
{
    use WithFileUploads;

    /** Rows per cache chunk / imported per polling step. */
    private const CHUNK = 1000;

    #[Locked]
    public int $projectId;

    public bool $showModal = false;

    public ?TemporaryUploadedFile $file = null;

    // Progress state.
    public bool $importing = false;

    public string $status = ''; // '' | 'preparing' | 'processing'

    public int $total = 0;

    public int $processed = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    #[Locked]
    public ?string $token = null;

    #[Locked]
    public ?string $path = null;

    #[Locked]
    public string $extension = '';

    #[Locked]
    public int $chunkIndex = 0;

    #[Locked]
    public int $chunkCount = 0;

    public function mount(Project $project): void
    {
        $this->projectId = $project->id;
    }

    public function updatedShowModal(bool $value): void
    {
        if (! $value) {
            $this->resetImport();
        }
    }

    /**
     * Store the upload and kick off the chunked import; the view polls step().
     */
    public function start(): void
    {
        Gate::authorize('manage-shareholders');

        $this->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:102400'],
        ]);

        $this->reset('total', 'processed', 'created', 'updated', 'skipped', 'chunkIndex', 'chunkCount');
        $this->token = Str::random(40);
        $this->path = $this->file->store('imports', 'local');
        $this->extension = $this->file->getClientOriginalExtension();
        $this->reset('file');

        $this->status = 'preparing';
        $this->importing = true;
    }

    /**
     * One unit of work per poll: parse on the first call, then import a chunk
     * on each subsequent call.
     */
    public function step(ShareholderImporter $importer): void
    {
        if (! $this->importing) {
            return;
        }

        Gate::authorize('manage-shareholders');

        // Reading/inserting a large file can be slow — don't let PHP time out.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $this->status === 'preparing'
            ? $this->prepare($importer)
            : $this->processChunk($importer);
    }

    public function progress(): int
    {
        return $this->total > 0 ? (int) floor($this->processed / $this->total * 100) : 0;
    }

    /**
     * Stream the upload into cache chunks (one pass, flat memory), then switch
     * to processing.
     */
    private function prepare(ShareholderImporter $importer): void
    {
        $fullPath = Storage::disk('local')->path($this->path);

        $chunk = [];
        $this->total = 0;
        $this->chunkCount = 0;

        foreach ($importer->eachRow($fullPath, $this->extension) as $row) {
            $chunk[] = $row;
            $this->total++;

            if (count($chunk) >= self::CHUNK) {
                Cache::put($this->chunkKey($this->chunkCount), $chunk, now()->addHours(2));
                $this->chunkCount++;
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            Cache::put($this->chunkKey($this->chunkCount), $chunk, now()->addHours(2));
            $this->chunkCount++;
        }

        Storage::disk('local')->delete($this->path);
        $this->path = null;

        if ($this->total === 0) {
            $this->finishDone();

            return;
        }

        $this->status = 'processing';
    }

    private function processChunk(ShareholderImporter $importer): void
    {
        $rows = Cache::get($this->chunkKey($this->chunkIndex));

        if ($rows === null) {
            $this->failImport();

            return;
        }

        $project = Project::findOrFail($this->projectId);

        DB::transaction(function () use ($importer, $project, $rows): void {
            foreach ($rows as $row) {
                match ($importer->importRow($project, $row)) {
                    'created' => $this->created++,
                    'updated' => $this->updated++,
                    default => $this->skipped++,
                };
            }
        });

        $this->processed += count($rows);
        Cache::forget($this->chunkKey($this->chunkIndex));
        $this->chunkIndex++;

        if ($this->chunkIndex >= $this->chunkCount) {
            $this->finishDone();
        }
    }

    private function finishDone(): void
    {
        $this->forgetChunks();

        $this->dispatch('toast', message: __('Imported :created, updated :updated, skipped :skipped.', [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
        ]), variant: 'success');

        $this->dispatch('shareholders-imported');

        $this->reset('showModal', 'importing', 'status', 'token', 'path', 'extension', 'chunkIndex', 'chunkCount');
    }

    private function failImport(): void
    {
        $this->cleanup();
        $this->reset('importing', 'status', 'token', 'path', 'extension', 'total', 'processed', 'chunkIndex', 'chunkCount');
        $this->dispatch('toast', message: __('The import failed. Please try again.'), variant: 'danger');
    }

    private function resetImport(): void
    {
        $this->cleanup();
        $this->reset('file', 'importing', 'status', 'token', 'path', 'extension', 'total', 'processed', 'created', 'updated', 'skipped', 'chunkIndex', 'chunkCount');
    }

    private function cleanup(): void
    {
        $this->forgetChunks();

        if ($this->path !== null) {
            Storage::disk('local')->delete($this->path);
        }
    }

    private function forgetChunks(): void
    {
        for ($i = $this->chunkIndex; $i < $this->chunkCount; $i++) {
            Cache::forget($this->chunkKey($i));
        }
    }

    private function chunkKey(int $i): string
    {
        return "shareholder-import:{$this->token}:chunk:{$i}";
    }

    public function render(): View
    {
        return view('livewire.projects.shareholder-import');
    }
}
