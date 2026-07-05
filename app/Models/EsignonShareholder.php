<?php

namespace App\Models;

use App\Services\Esignon\EsignonSync;
use Database\Factories\EsignonShareholderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A completed ESIGNON (전자위임) proxy document for one signer on one project,
 * pulled from docs.esignon.net by {@see EsignonSync}. Once
 * matched to a roster assignment its `needs_sync` flag clears; rows that never
 * match a roster entry stay flagged and feed the "주주명부 누락" export sheet.
 */
#[Fillable([
    'project_id', 'link_manage_id', 'link_id', 'identifier', 'name',
    'contact', 'completed_date', 'data_hash', 'needs_sync', 'synced_at',
])]
class EsignonShareholder extends Model
{
    /** @use HasFactory<EsignonShareholderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_date' => 'datetime',
            'needs_sync' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
