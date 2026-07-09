<?php

namespace App\Livewire\EmailToWorker;

use App\Enums\RosterSortColumn;
use App\Enums\WorkerEmailRecipientStatus;
use App\Enums\WorkerEmailStatus;
use App\Jobs\SendWorkerEmail;
use App\Models\Project;
use App\Models\ProjectResource;
use App\Models\User;
use App\Models\WorkerEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The compose half of 활동가에게 이메일 보내기: pick the project's workers,
 * write subject/message, attach resource links/files and the per-worker
 * 명부양식 (roster form), then queue one email per worker. Also hosts the
 * roster download buttons (명부양식 내려받기 / 전체 다운로드) whose sort options
 * mirror the email attachments. Port of the legacy SingleEmailToWorker page.
 */
#[Title('Email to worker')]
class Compose extends Component
{
    #[Locked]
    public int $projectId;

    /** @var list<int> */
    public array $selectedWorkerIds = [];

    public string $subject = '';

    public string $message = '';

    /** @var list<int> */
    public array $selectedLinkIds = [];

    /** @var list<int> */
    public array $selectedAttachmentIds = [];

    /** 이메일에 명부양식 포함 — both default on, like the legacy page. */
    public bool $includeRosterXlsx = true;

    public bool $includeRosterPdf = true;

    public string $sortColumn = 'no';

    public string $sortOrder = 'asc';

    /** Format of the 명부양식 내려받기 buttons (xlsx or pdf). */
    public string $downloadType = 'xlsx';

    public function mount(Project $project): void
    {
        $this->projectId = $project->id;
    }

    /**
     * Queue the batch: snapshot the composed email plus one recipient per
     * selected worker, then dispatch a send job for each.
     */
    public function send(): void
    {
        $this->validate([
            'selectedWorkerIds' => ['required', 'array', 'min:1'],
            'selectedWorkerIds.*' => ['integer'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'selectedLinkIds' => ['array'],
            'selectedLinkIds.*' => ['integer'],
            'selectedAttachmentIds' => ['array'],
            'selectedAttachmentIds.*' => ['integer'],
            'sortColumn' => ['required', Rule::enum(RosterSortColumn::class)],
            'sortOrder' => ['required', 'in:asc,desc'],
        ]);

        // Server-side whitelists: only this project's workers and resources count.
        $workers = $this->project->assignedWorkers()->whereKey($this->selectedWorkerIds)->get();

        if ($workers->isEmpty()) {
            $this->addError('selectedWorkerIds', __('Select at least one worker.'));

            return;
        }

        $links = $this->linkResources
            ->whereIn('id', $this->selectedLinkIds)
            ->map(fn (ProjectResource $resource): array => [
                'title' => $resource->title ?? '',
                'url' => $resource->url ?? '',
            ])
            ->filter(fn (array $link): bool => $link['url'] !== '')
            ->values()
            ->all();

        $attachments = $this->fileResources
            ->whereIn('id', $this->selectedAttachmentIds)
            ->map(fn (ProjectResource $resource): array => [
                'resource_id' => $resource->id,
                // file_name is the original upload name (keeps the extension).
                'filename' => $resource->file_name ?: basename((string) $resource->file_path),
                'path' => $resource->file_path ?? '',
            ])
            ->filter(fn (array $attachment): bool => $attachment['path'] !== '')
            ->values()
            ->all();

        $batch = DB::transaction(function () use ($workers, $links, $attachments): WorkerEmail {
            $batch = WorkerEmail::create([
                'project_id' => $this->projectId,
                'created_by' => auth()->id(),
                'subject' => trim($this->subject),
                'body' => trim($this->message),
                'links' => $links,
                'attachments' => $attachments,
                'include_roster_xlsx' => $this->includeRosterXlsx,
                'include_roster_pdf' => $this->includeRosterPdf,
                'roster_sort_column' => RosterSortColumn::from($this->sortColumn),
                'roster_sort_desc' => $this->sortOrder === 'desc',
                'status' => WorkerEmailStatus::Pending,
            ]);

            foreach ($workers as $worker) {
                $batch->recipients()->create([
                    'user_id' => $worker->id,
                    // The notification override wins over the login email.
                    'email' => $worker->email_receiver ?: $worker->email,
                    'status' => WorkerEmailRecipientStatus::Pending,
                ]);
            }

            return $batch;
        });

        foreach ($batch->recipients as $recipient) {
            SendWorkerEmail::dispatch($recipient);
        }

        $this->reset('subject', 'message');
        $this->dispatch('toast', message: __('Email queued for sending.'), variant: 'success');
    }

    public function toggleSelectAll(): void
    {
        $workerIds = $this->workers->modelKeys();

        $this->selectedWorkerIds = count($this->selectedWorkerIds) === count($workerIds) ? [] : $workerIds;
    }

    #[Computed]
    public function project(): Project
    {
        return Project::findOrFail($this->projectId);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function workers(): Collection
    {
        return $this->project->assignedWorkers()->get();
    }

    /**
     * @return Collection<int, ProjectResource>
     */
    #[Computed]
    public function linkResources(): Collection
    {
        return $this->project->resources->filter(fn (ProjectResource $resource): bool => $resource->isLink())->values();
    }

    /**
     * @return Collection<int, ProjectResource>
     */
    #[Computed]
    public function fileResources(): Collection
    {
        return $this->project->resources->filter(fn (ProjectResource $resource): bool => $resource->isFile())->values();
    }

    public function render(): View
    {
        return view('livewire.email-to-worker.compose');
    }
}
