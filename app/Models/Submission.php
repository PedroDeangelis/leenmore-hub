<?php

namespace App\Models;

use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A field worker's activity report for one shareholder on one project. The
 * per-visit history record; the shareholder's *current* 판단 lives on
 * `project_shareholders.result_id`, which a submission drives.
 */
#[Fillable([
    'project_id', 'project_shareholder_id', 'user_id', 'user_name', 'created_by_user_id',
    'date', 'result', 'contact', 'privacy_consent', 'note',
    'files', 'privacy_consent_files',
])]
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'privacy_consent' => 'boolean',
            'files' => 'array',
            'privacy_consent_files' => 'array',
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
     * The shareholder assignment this report was filed against.
     *
     * @return BelongsTo<ProjectShareholder, $this>
     */
    public function projectShareholder(): BelongsTo
    {
        return $this->belongsTo(ProjectShareholder::class);
    }

    /**
     * The worker who filed the report.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The admin/office user who manually entered this report, if any (null when
     * the worker filed it themselves).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
