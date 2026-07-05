<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Worksheet names Excel will accept: no `\ / : * ? [ ]`, at most 31 chars,
 * with a fallback for empty titles.
 */
final class WorksheetName
{
    public static function for(?string $title, string $fallback = 'Project'): string
    {
        $name = trim(preg_replace('#[\\\\/:*?\[\]]#u', '', (string) $title) ?? '');

        return Str::limit($name !== '' ? $name : $fallback, 31, '');
    }
}
