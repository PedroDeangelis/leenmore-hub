<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Support\MeetingCountdown;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'status', 'message', 'start_date', 'end_date', 'shares_issued', 'shares_target'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'shares_issued' => 'integer',
            'shares_target' => 'integer',
        ];
    }

    /**
     * Hide "deleted" projects from every query (lists, filters, route binding).
     * They stay in the database but are unreachable from the app — only the
     * explicit `withDeleted()` scope (or raw DB access) brings them back.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('notDeleted', function (Builder $query): void {
            $query->where('projects.status', '!=', ProjectStatus::Deleted->value);
        });
    }

    /**
     * The outcome labels (판단) this project defines, in display order.
     *
     * @return HasMany<ProjectResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(ProjectResult::class)->orderBy('sort_order');
    }

    /**
     * The shareholder roster for this project — one assignment row per person,
     * in list order. Each row's `shareholder` is the underlying person.
     *
     * @return HasMany<ProjectShareholder, $this>
     */
    public function shareholders(): HasMany
    {
        return $this->hasMany(ProjectShareholder::class)->orderBy('no');
    }

    /**
     * Every activity report (Submission) filed against this project, newest
     * first. These are what the reports archive lists and drills into.
     *
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class)->latest('date')->latest('id');
    }

    /**
     * The project's resource room (프로젝트 자료실) — links and uploaded files in
     * display order.
     *
     * @return HasMany<ProjectResource, $this>
     */
    public function resources(): HasMany
    {
        return $this->hasMany(ProjectResource::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Live countdown to the shareholder meeting (the end date), or null when
     * no end date is set.
     */
    public function meetingCountdown(): ?MeetingCountdown
    {
        return MeetingCountdown::for($this->end_date);
    }

    /**
     * Include "deleted" projects (developer/maintenance use only — no UI path).
     */
    #[Scope]
    protected function withDeleted(Builder $query): void
    {
        $query->withoutGlobalScope('notDeleted');
    }

    /**
     * The active, worker-visible statuses (draft + publish).
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', [ProjectStatus::Draft->value, ProjectStatus::Publish->value]);
    }

    /**
     * Archived projects (hidden from the active list, still restorable).
     */
    #[Scope]
    protected function archived(Builder $query): void
    {
        $query->where('status', ProjectStatus::Archived->value);
    }

    /**
     * Publish the project (draft → publish), making it visible to assigned
     * workers.
     */
    public function publish(): void
    {
        $this->update(['status' => ProjectStatus::Publish]);
    }

    /**
     * Move the project to the archive (hidden from workers, still restorable).
     */
    public function archive(): void
    {
        $this->update(['status' => ProjectStatus::Archived]);
    }

    /**
     * Bring a project back to draft — from the archive, or from publish to
     * hide it from workers again.
     */
    public function restoreToDraft(): void
    {
        $this->update(['status' => ProjectStatus::Draft]);
    }

    /**
     * Mark the project deleted: it stays in the DB but disappears from the app
     * and cannot be restored through the UI.
     */
    public function markDeleted(): void
    {
        $this->update(['status' => ProjectStatus::Deleted]);
    }
}
