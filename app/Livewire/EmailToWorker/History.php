<?php

namespace App\Livewire\EmailToWorker;

use App\Enums\WorkerEmailRecipientStatus;
use App\Jobs\SendWorkerEmail;
use App\Models\Project;
use App\Models\WorkerEmailRecipient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The worker-email queue monitor — the hub home of the retired
 * leenmore-storage `visualize-emails-sent.php` page. Per-recipient delivery
 * rows with status counts, filters, and a re-queue action for failed or stuck
 * recipients.
 */
#[Title('Email to worker')]
class History extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url(as: 'project')]
    public string $projectId = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedProjectId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Put a failed (or never-picked-up) recipient back in the queue — the
     * legacy page's "Send now / Retry now" buttons.
     */
    public function retry(int $recipientId): void
    {
        $recipient = WorkerEmailRecipient::findOrFail($recipientId);

        if ($recipient->status === WorkerEmailRecipientStatus::Sent) {
            return;
        }

        $recipient->requeue();
        $recipient->workerEmail?->refreshStatus();

        SendWorkerEmail::dispatch($recipient);

        $this->dispatch('toast', message: __('Email queued for sending.'), variant: 'success');
    }

    /**
     * Recipient counts per status for the stat cards.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $counts = WorkerEmailRecipient::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => $counts->sum(),
            ...collect(WorkerEmailRecipientStatus::cases())
                ->mapWithKeys(fn (WorkerEmailRecipientStatus $status): array => [
                    $status->value => (int) ($counts[$status->value] ?? 0),
                ]),
        ];
    }

    /**
     * Projects that have sent batches, for the filter dropdown.
     *
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projectOptions(): Collection
    {
        return Project::query()
            ->whereHas('workerEmails')
            ->orderBy('title')
            ->get(['id', 'title']);
    }

    /**
     * @return LengthAwarePaginator<int, WorkerEmailRecipient>
     */
    private function recipients(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return WorkerEmailRecipient::query()
            ->with(['worker', 'workerEmail.project'])
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->projectId !== '', fn (Builder $query) => $query->whereHas(
                'workerEmail',
                fn (Builder $inner) => $inner->where('project_id', $this->projectId),
            ))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.$search.'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('email', 'like', $term)
                        ->orWhereHas('worker', fn (Builder $inner) => $inner->where('name', 'like', $term))
                        ->orWhereHas('workerEmail', fn (Builder $inner) => $inner->where('subject', 'like', $term));
                });
            })
            ->latest('id')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.email-to-worker.history', [
            'recipients' => $this->recipients(),
        ]);
    }
}
