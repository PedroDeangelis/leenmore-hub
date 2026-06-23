<?php

namespace App\Concerns;

/**
 * Validation rules for an expense receipt (영수증). Shared so the worker submit form
 * and any future edit screen validate identically.
 */
trait ReceiptValidationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function receiptRules(): array
    {
        return [
            'date' => ['required', 'date'],
            'receipt_category_id' => ['required', 'integer', 'exists:receipt_categories,id'],
            'vendor' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // A single optional receipt attachment (photo / audio / PDF).
            'attachment' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,heic,webp,pdf,mp3,m4a,wav'],
        ];
    }
}
