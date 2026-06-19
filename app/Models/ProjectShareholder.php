<?php

namespace App\Models;

use Database\Factories\ProjectShareholderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A shareholder's assignment to one project's meeting — the per-project half of
 * the shareholders split. Carries everything specific to this meeting, with the
 * per-project contact/address overriding the person's base value when set.
 */
#[Fillable([
    'project_id', 'shareholder_id', 'shares', 'shares_total',
    'contact_info', 'contact_info_2', 'address', 'contact_worker',
    'result_id', 'last_note', 'prev_result', 'prev_comment', 'prev_note',
    'electronic_voting', 'api_recipient_contact', 'api_recipient_completion_date',
    'no', 'row_no', 'source_database',
])]
class ProjectShareholder extends Model
{
    /** @use HasFactory<ProjectShareholderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shares' => 'integer',
            'shares_total' => 'integer',
            'electronic_voting' => 'boolean',
            'api_recipient_completion_date' => 'date',
            'no' => 'integer',
            'row_no' => 'integer',
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
     * The person this assignment belongs to.
     *
     * @return BelongsTo<Shareholder, $this>
     */
    public function shareholder(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class);
    }

    /**
     * The current 판단 result for this assignment, if any.
     *
     * @return BelongsTo<ProjectResult, $this>
     */
    public function result(): BelongsTo
    {
        return $this->belongsTo(ProjectResult::class, 'result_id');
    }

    /**
     * The worker users assigned to canvass this shareholder for the project.
     *
     * @return BelongsToMany<User, $this>
     */
    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_shareholder_user')->withTimestamps();
    }

    /**
     * The contact to use for this project: the per-project override, falling
     * back to the person's base contact.
     *
     * @return Attribute<?string, never>
     */
    protected function effectiveContact(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->contact_info ?? $this->shareholder?->contact_info);
    }

    /**
     * The address to use for this project: the per-project override, falling
     * back to the person's base address.
     *
     * @return Attribute<?string, never>
     */
    protected function effectiveAddress(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->address ?? $this->shareholder?->address);
    }
}
