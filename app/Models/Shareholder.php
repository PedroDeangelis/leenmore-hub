<?php

namespace App\Models;

use App\Enums\PersonType;
use Database\Factories\ShareholderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shareholder as a global *person* — identity that stays stable across
 * projects. Per-project participation lives on {@see ProjectShareholder}.
 */
#[Fillable(['name', 'registration', 'sex', 'person_type', 'date_of_birth', 'date_of_birth_code', 'code', 'contact_info', 'address'])]
class Shareholder extends Model
{
    /** @use HasFactory<ShareholderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'person_type' => PersonType::class,
            'date_of_birth' => 'date',
        ];
    }

    /**
     * This person's per-project assignments.
     *
     * @return HasMany<ProjectShareholder, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectShareholder::class);
    }

    /**
     * The projects this person is assigned to (convenience over the assignments).
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_shareholders')->withTimestamps();
    }
}
