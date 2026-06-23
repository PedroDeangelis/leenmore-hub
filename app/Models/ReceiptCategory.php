<?php

namespace App\Models;

use Database\Factories\ReceiptCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A usage category (사용 내역) shown in the receipt form dropdown. Admin-managed in
 * the Options tool.
 */
#[Fillable(['name', 'position'])]
class ReceiptCategory extends Model
{
    /** @use HasFactory<ReceiptCategoryFactory> */
    use HasFactory;

    /**
     * @return HasMany<Receipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * Order categories the way they appear in the form.
     *
     * @param  Builder<ReceiptCategory>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }
}
