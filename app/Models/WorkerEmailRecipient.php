<?php

namespace App\Models;

use App\Enums\WorkerEmailRecipientStatus;
use Database\Factories\WorkerEmailRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recipient of a {@see WorkerEmail} batch: the worker and the address the
 * email actually goes to (their `email_receiver` override, else their login
 * email — snapshotted at queue time so history shows what was really used).
 * The queued SendWorkerEmail job walks this row through
 * pending → processing → sent | failed, recording attempts, errors, and the
 * per-worker 명부양식 files it generated. Port of the retired leenmore-storage
 * `email_recipients`.
 */
#[Fillable([
    'worker_email_id', 'user_id', 'email', 'status', 'attempts',
    'last_attempt_at', 'sent_at', 'error_message',
    'roster_xlsx_path', 'roster_pdf_path',
])]
class WorkerEmailRecipient extends Model
{
    /** @use HasFactory<WorkerEmailRecipientFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkerEmailRecipientStatus::class,
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkerEmail, $this>
     */
    public function workerEmail(): BelongsTo
    {
        return $this->belongsTo(WorkerEmail::class);
    }

    /**
     * The worker this was addressed to (null if the account was deleted).
     *
     * @return BelongsTo<User, $this>
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Record the start of a delivery attempt.
     */
    public function markProcessing(): void
    {
        $this->update([
            'status' => WorkerEmailRecipientStatus::Processing,
            'attempts' => $this->attempts + 1,
            'last_attempt_at' => now(),
        ]);
    }

    public function markSent(): void
    {
        $this->update([
            'status' => WorkerEmailRecipientStatus::Sent,
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => WorkerEmailRecipientStatus::Failed,
            'error_message' => $error,
        ]);
    }

    /**
     * Put a failed/stuck recipient back in line for another send (the history
     * page's retry action).
     */
    public function requeue(): void
    {
        $this->update([
            'status' => WorkerEmailRecipientStatus::Pending,
            'error_message' => null,
        ]);
    }
}
