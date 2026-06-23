<?php

namespace App\Models;

use App\Enums\ResourceType;
use Database\Factories\ProjectResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One entry in a project's resource room (프로젝트 자료실) — either an external link
 * or an uploaded file. Distinct from {@see ProjectResult} (the 판단 outcome labels).
 */
#[Fillable([
    'project_id', 'type', 'title', 'url', 'file_path', 'file_name', 'sort_order',
])]
class ProjectResource extends Model
{
    /** @use HasFactory<ProjectResourceFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
            'sort_order' => 'integer',
        ];
    }

    public function isLink(): bool
    {
        return $this->type === ResourceType::Link;
    }

    public function isFile(): bool
    {
        return $this->type === ResourceType::File;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
