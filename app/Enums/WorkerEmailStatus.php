<?php

namespace App\Enums;

/**
 * Roll-up state of one composed 활동가 이메일 batch (a WorkerEmail): the batch
 * starts Pending, shows Processing while some recipients are still being
 * worked, and lands on Completed (every recipient sent) or Failed (at least
 * one recipient exhausted its attempts). Mirrors the legacy storage-service
 * `email_jobs.status` semantics.
 */
enum WorkerEmailStatus: string
{
    case Pending = 'pending';

    case Processing = 'processing';

    case Completed = 'completed';

    case Failed = 'failed';

    /**
     * Human-readable, translatable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Processing => __('Sending'),
            self::Completed => __('Completed'),
            self::Failed => __('Failed'),
        };
    }

    /**
     * The <x-ui.badge> variant used to render this status.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Processing => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }
}
