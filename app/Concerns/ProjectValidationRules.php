<?php

namespace App\Concerns;

use App\Enums\ProjectStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProjectValidationRules
{
    /**
     * Validation rules for the editable project fields, shared by the create
     * and edit (single) screens.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function projectRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // Only draft/publish are user-assignable; archived/deleted are reached
            // through the archive/delete actions, never a form payload.
            'status' => ['required', Rule::enum(ProjectStatus::class)->only(ProjectStatus::assignable())],
            'message' => ['nullable', 'string', 'max:65535'],
            'start_date' => ['nullable', 'date'],
            // Only compare against the start date when one was actually provided,
            // otherwise an empty start date would break the date comparison.
            'end_date' => ['nullable', 'date', $this->start_date ? 'after_or_equal:start_date' : ''],
            'shares_issued' => ['nullable', 'integer', 'min:0'],
            'shares_target' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
