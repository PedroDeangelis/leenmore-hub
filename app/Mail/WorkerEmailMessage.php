<?php

namespace App\Mail;

use App\Models\WorkerEmail;
use App\Services\WorkerRoster\RosterFiles;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The email one worker receives from a {@see WorkerEmail} batch: the admin's
 * subject/body, a "Links:" list of the selected resource links, the selected
 * resource files as real attachments, and — when the batch asks for it — that
 * worker's own 명부양식 (roster form) XLSX/PDF. Replicates the body the retired
 * leenmore-storage PHPMailer sender composed.
 */
class WorkerEmailMessage extends Mailable
{
    public function __construct(
        public WorkerEmail $batch,
        public ?RosterFiles $roster = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->batch->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.worker-email',
            with: [
                'body' => $this->batch->body,
                'links' => $this->batch->links ?? [],
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->batch->attachments ?? [] as $attachment) {
            $path = $attachment['path'] ?? '';
            if ($path === '') {
                continue;
            }

            $attachments[] = Attachment::fromStorageDisk('local', $path)
                ->as($attachment['filename'] ?: basename($path));
        }

        if ($this->roster !== null && $this->batch->include_roster_xlsx) {
            $attachments[] = Attachment::fromStorageDisk('local', $this->roster->xlsxPath)
                ->as($this->roster->filenameStem.'.xlsx')
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }

        if ($this->roster?->pdfPath !== null && $this->batch->include_roster_pdf) {
            $attachments[] = Attachment::fromStorageDisk('local', $this->roster->pdfPath)
                ->as($this->roster->filenameStem.'.pdf')
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
