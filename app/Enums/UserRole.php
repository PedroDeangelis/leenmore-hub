<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Office = 'office';
    case Worker = 'worker';

    /**
     * Admin and office share the admin visual area; office has fewer permissions.
     */
    public function worksInAdminArea(): bool
    {
        return $this !== self::Worker;
    }
}
