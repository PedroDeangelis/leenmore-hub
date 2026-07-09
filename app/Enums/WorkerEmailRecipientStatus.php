<?php

namespace App\Enums;

/**
 * Delivery state of one recipient within a WorkerEmail batch. A recipient is
 * queued Pending, marked Processing while its send job runs, and ends Sent or
 * Failed (after the job's retries are exhausted). Failed recipients can be
 * re-queued from the history page. Mirrors the legacy storage-service
 * `email_recipients.status` lifecycle.
 */
enum WorkerEmailRecipientStatus: string
{
    case Pending = 'pending';

    case Processing = 'processing';

    case Sent = 'sent';

    case Failed = 'failed';

    /**
     * Human-readable, translatable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Processing => __('Sending'),
            self::Sent => __('Sent'),
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
            self::Sent => 'success',
            self::Failed => 'danger',
        };
    }
}
