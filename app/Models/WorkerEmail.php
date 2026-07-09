<?php

namespace App\Models;

use App\Enums\RosterSortColumn;
use App\Enums\WorkerEmailRecipientStatus;
use App\Enums\WorkerEmailStatus;
use Database\Factories\WorkerEmailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One composed "email to worker" (활동가에게 이메일 보내기) batch: subject/body plus
 * a write-once snapshot of the selected resource links, file attachments, and
 * 명부양식 (roster form) options. Each selected worker becomes a
 * {@see WorkerEmailRecipient}; a queued SendWorkerEmail job delivers to each
 * one and {@see refreshStatus()} rolls their outcomes up onto this row. Port
 * of the retired leenmore-storage `email_jobs`.
 */
#[Fillable([
    'project_id', 'created_by', 'subject', 'body', 'links', 'attachments',
    'include_roster_xlsx', 'include_roster_pdf', 'roster_sort_column',
    'roster_sort_desc', 'status',
])]
class WorkerEmail extends Model
{
    /** @use HasFactory<WorkerEmailFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'links' => 'array',
            'attachments' => 'array',
            'include_roster_xlsx' => 'boolean',
            'include_roster_pdf' => 'boolean',
            'roster_sort_column' => RosterSortColumn::class,
            'roster_sort_desc' => 'boolean',
            'status' => WorkerEmailStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The admin who composed and queued the batch.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<WorkerEmailRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(WorkerEmailRecipient::class);
    }

    /**
     * Whether any 명부양식 (roster form) is attached per recipient.
     */
    public function includesRoster(): bool
    {
        return $this->include_roster_xlsx || $this->include_roster_pdf;
    }

    /**
     * Re-derive the batch status from its recipients (legacy
     * `updateJobStatuses`): still Pending until a first attempt, Processing
     * while any recipient is unresolved, then Failed if anyone failed,
     * Completed otherwise. Called after every recipient status transition.
     */
    public function refreshStatus(): void
    {
        $statuses = $this->recipients()->pluck('status');

        $unresolved = $statuses->contains(fn (WorkerEmailRecipientStatus $status): bool => in_array(
            $status,
            [WorkerEmailRecipientStatus::Pending, WorkerEmailRecipientStatus::Processing],
            true,
        ));

        $status = match (true) {
            $unresolved => $statuses->contains(WorkerEmailRecipientStatus::Sent) || $statuses->contains(WorkerEmailRecipientStatus::Failed)
                ? WorkerEmailStatus::Processing
                : WorkerEmailStatus::Pending,
            $statuses->contains(WorkerEmailRecipientStatus::Failed) => WorkerEmailStatus::Failed,
            default => WorkerEmailStatus::Completed,
        };

        if ($this->status !== $status) {
            $this->update(['status' => $status]);
        }
    }
}
