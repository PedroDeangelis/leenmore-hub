<?php

namespace App\Enums;

/**
 * A project resource is either an external link or an uploaded file.
 */
enum ResourceType: string
{
    case Link = 'link';

    case File = 'file';

    /**
     * Human-readable, translatable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Link => __('Link'),
            self::File => __('File'),
        };
    }
}
