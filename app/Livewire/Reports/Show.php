<?php

namespace App\Livewire\Reports;

use App\Enums\ResultColor;
use App\Models\Project;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One project's reports (보고 내역) — every activity report filed against it,
 * with filter / sort controls and expandable per-report detail. Read-only,
 * gated by `view-submissions`.
 */
#[Title('Reports')]
class Show extends Component
{
    #[Locked]
    public int $projectId;

    /** Free-text search over shareholder name / registration / worker. */
    public string $search = '';

    /** Filter by 판단 (a result name); '' = all. */
    public string $result = '';

    /** Filter by 활동가 (a worker name); '' = all. */
    public string $worker = '';

    /** Sort column: 'shares' (총소유주식수) or 'date' (제출 날짜). */
    public string $sort = 'date';

    /** Sort direction: 'asc' | 'desc'. */
    public string $direction = 'desc';

    /** Show only each shareholder's most recent report. */
    public bool $latestOnly = false;

    /** The expanded report row, or null. */
    public ?int $expanded = null;

    public function mount(Project $project): void
    {
        $this->projectId = $project->id;
    }

    /**
     * Expand / collapse one report's detail panel.
     */
    public function toggle(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    /**
     * Sort by a column, flipping direction when it is already active.
     */
    public function sortBy(string $column): void
    {
        if (! in_array($column, ['shares', 'date'], true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sort = $column;
        $this->direction = 'desc';
    }

    public function render(): View
    {
        $project = $this->project();

        // A report stores its 판단 as a name snapshot; map back to the project's
        // result colour so the chip keeps its colour even if results later change.
        $colours = $project->results->mapWithKeys(
            fn ($result): array => [$result->name => $result->color],
        );

        $reports = $this->reports($project);

        return view('livewire.reports.show', [
            'project' => $project,
            'rows' => $reports->map(fn (Submission $s): array => $this->presentRow($s, $colours))->all(),
            'summary' => [
                'count' => $reports->count(),
                'shares' => $reports->sum(fn (Submission $s): int => (int) ($s->projectShareholder?->shares ?? 0)),
                'total' => $reports->sum(fn (Submission $s): int => (int) ($s->projectShareholder?->shares_total ?? 0)),
            ],
            'results' => $project->results,
            'workers' => $this->workerNames($project),
        ]);
    }

    /**
     * The filtered, sorted reports for this project (no pagination — the page
     * mirrors the design's single scrolling table).
     *
     * @return Collection<int, Submission>
     */
    private function reports(Project $project): Collection
    {
        $search = trim($this->search);

        $reports = Submission::query()
            ->where('project_id', $project->id)
            ->with(['projectShareholder.shareholder', 'projectShareholder.result'])
            ->when($this->result !== '', fn (Builder $q) => $q->where('result', $this->result))
            ->when($this->worker !== '', fn (Builder $q) => $q->where('user_name', $this->worker))
            ->when($this->latestOnly, fn (Builder $q) => $q->whereIn('id', $this->latestReportIds($project)))
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $sub) use ($search): void {
                $sub->where('user_name', 'like', '%'.$search.'%')
                    ->orWhereHas('projectShareholder.shareholder', fn (Builder $person) => $person
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('registration', 'like', '%'.$search.'%'));
            }))
            ->get();

        $descending = $this->direction === 'desc';

        return $reports
            ->sortBy(
                $this->sort === 'shares'
                    ? fn (Submission $s): int => (int) ($s->projectShareholder?->shares_total ?? 0)
                    : fn (Submission $s): array => [$s->date?->timestamp ?? 0, $s->id],
                SORT_REGULAR,
                $descending,
            )
            ->values();
    }

    /**
     * The id of the most recent report for each shareholder in this project.
     *
     * @return Builder<Submission>
     */
    private function latestReportIds(Project $project): Builder
    {
        return Submission::query()
            ->where('project_id', $project->id)
            ->groupBy('project_shareholder_id')
            ->selectRaw('MAX(id)');
    }

    /**
     * Distinct worker names that have filed a report on this project.
     *
     * @return Collection<int, string>
     */
    private function workerNames(Project $project): Collection
    {
        return Submission::query()
            ->where('project_id', $project->id)
            ->whereNotNull('user_name')
            ->orderBy('user_name')
            ->distinct()
            ->pluck('user_name');
    }

    /**
     * Flatten one report into the display fields the table + detail panel use.
     *
     * @param  Collection<string, ResultColor>  $colours
     * @return array<string, mixed>
     */
    private function presentRow(Submission $submission, Collection $colours): array
    {
        $assignment = $submission->projectShareholder;
        $person = $assignment?->shareholder;
        $colour = $colours->get((string) $submission->result, ResultColor::Gray);

        return [
            'id' => $submission->id,
            'name' => $person?->name ?? '—',
            'idNum' => $person?->date_of_birth_code ?: $person?->registration ?: '',
            'gender' => $this->genderLabel($person?->sex),
            'shares' => $assignment?->shares,
            'total' => $assignment?->shares_total,
            'worker' => $submission->user_name ?: '—',
            'submitDate' => $submission->created_at,
            'judgeDate' => $submission->date,
            'judgment' => $submission->result ?: '—',
            'chip' => $colour->chipClasses(),
            'isOpen' => $this->expanded === $submission->id,
            'detail' => [
                'created' => $submission->created_at,
                'actDate' => $submission->date,
                'address' => $assignment?->effective_address ?: '-',
                'contact' => $submission->contact ?: ($assignment?->effective_contact ?: '-'),
                'note' => $submission->note ?: '—',
                'files' => collect($submission->files ?? [])
                    ->map(fn (string $path, int $index): array => [
                        'index' => $index,
                        'name' => basename($path),
                    ])
                    ->all(),
            ],
        ];
    }

    /**
     * Map a stored sex value to its Korean label, or null when unknown.
     */
    private function genderLabel(?string $sex): ?string
    {
        if ($sex === null || $sex === '') {
            return null;
        }

        return match (mb_strtoupper($sex)) {
            'M' => '남',
            'F' => '여',
            default => $sex,
        };
    }

    private function project(): Project
    {
        return Project::with('results')->findOrFail($this->projectId);
    }
}
