<?php

namespace App\Models;

use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An expense receipt submitted by a field worker (영수증 제출). The worker and
 * category names are denormalized so the history survives a user/category delete.
 */
#[Fillable([
    'user_id', 'user_name', 'receipt_category_id', 'category_name',
    'date', 'vendor', 'amount', 'notes', 'attachment',
])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'integer',
        ];
    }

    /**
     * The worker who submitted the receipt.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The chosen usage category (may be null if it was later deleted).
     *
     * @return BelongsTo<ReceiptCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ReceiptCategory::class, 'receipt_category_id');
    }
}
