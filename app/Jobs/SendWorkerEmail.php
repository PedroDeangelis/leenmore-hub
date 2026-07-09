<?php

namespace App\Jobs;

use App\Enums\WorkerEmailRecipientStatus;
use App\Mail\WorkerEmailMessage;
use App\Models\WorkerEmail;
use App\Models\WorkerEmailRecipient;
use App\Services\WorkerRoster\RosterReportGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Delivers a {@see WorkerEmail} batch to one recipient: generates
 * that worker's 명부양식 files when the batch asks for them (at send time, so
 * retries and late roster edits see current data), sends the Mailable, and
 * walks the recipient's status. The queue's retries replace the legacy
 * storage-service cron (`EmailQueueProcessor`, batch 10 / max 3 attempts);
 * after the last retry {@see failed()} records the error for the history
 * page's retry button.
 */
class SendWorkerEmail implements ShouldQueue
{
    use Queueable;

    /** Legacy queue semantics: up to three delivery attempts per recipient. */
    public int $tries = 3;

    public function __construct(
        public WorkerEmailRecipient $recipient,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(RosterReportGenerator $rosters): void
    {
        $recipient = $this->recipient;
        $recipient->loadMissing(['workerEmail.project', 'worker']);

        // Already delivered (double dispatch, manual send while queued) — done.
        if ($recipient->status === WorkerEmailRecipientStatus::Sent) {
            return;
        }

        $batch = $recipient->workerEmail;

        $recipient->markProcessing();
        $batch->refreshStatus();

        $roster = null;
        if ($batch->includesRoster() && $recipient->worker !== null) {
            $roster = $rosters->generateForWorker(
                $batch->project,
                $recipient->worker,
                $batch->roster_sort_column,
                $batch->roster_sort_desc,
                withPdf: $batch->include_roster_pdf,
            );

            $recipient->update([
                'roster_xlsx_path' => $roster?->xlsxPath,
                'roster_pdf_path' => $roster?->pdfPath,
            ]);
        }

        Mail::to($recipient->email, $recipient->worker?->name)
            ->send(new WorkerEmailMessage($batch, $roster));

        $recipient->markSent();
        $batch->refreshStatus();
    }

    /**
     * All retries exhausted — record the error so the history page can show
     * it and offer a manual retry.
     */
    public function failed(?Throwable $exception): void
    {
        $this->recipient->markFailed($exception?->getMessage() ?? 'Unknown error');
        $this->recipient->workerEmail?->refreshStatus();
    }
}
