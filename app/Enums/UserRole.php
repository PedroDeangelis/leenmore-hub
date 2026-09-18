<?php

namespace App\Enums;

/**
 * Who a login account is. There are only two kinds of person here: the admins who
 * run the portal, and the viewers who consume the API through it.
 *
 * Note this is *not* the same vocabulary as Supabase's `profiles.role`
 * (worker/admin) — those are the 활동가 the API reports on, not people who log in
 * to this tool.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Viewer = 'viewer';

    /**
     * Human label for the role picker.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Admin'),
            self::Viewer => __('Viewer'),
        };
    }
}
