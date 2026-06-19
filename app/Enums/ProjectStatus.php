<?php

namespace App\Enums;

/**
 * The lifecycle state of a project — the single static source of truth.
 *
 * - Draft / Publish are the active, user-assignable states.
 * - Archived hides a project from the active list but keeps it visible to
 *   admin/office (via the "Archived" filter) and restorable.
 * - Deleted keeps the row in the database forever but never lists or shows it
 *   anywhere, and it cannot be restored from the UI (a global scope on the
 *   Project model hides it). Only a developer editing the DB directly revives it.
 */
enum ProjectStatus: string
{
    case Draft = 'draft';

    case Publish = 'publish';

    case Archived = 'archived';

    case Deleted = 'deleted';

    /**
     * Human-readable, translatable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Preparing'),
            self::Publish => __('In progress'),
            self::Archived => __('Archived'),
            self::Deleted => __('Deleted'),
        };
    }

    /**
     * The <x-ui.badge> variant used to render this status.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Publish => 'success',
            self::Archived => 'danger',
            self::Deleted => 'danger',
        };
    }

    /**
     * The statuses a user may assign directly (create form, validation, list
     * filter). Archived and Deleted are only reached via the archive/delete
     * actions, never picked from a dropdown.
     *
     * @return array<int, self>
     */
    public static function assignable(): array
    {
        return [self::Draft, self::Publish];
    }
}
