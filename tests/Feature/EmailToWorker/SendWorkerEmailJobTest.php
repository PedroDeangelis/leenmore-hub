<?php

namespace Tests\Feature\EmailToWorker;

use App\Enums\WorkerEmailRecipientStatus;
use App\Enums\WorkerEmailStatus;
use App\Jobs\SendWorkerEmail;
use App\Mail\WorkerEmailMessage;
use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use App\Models\User;
use App\Models\WorkerEmail;
use App\Models\WorkerEmailRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class SendWorkerEmailJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_send_marks_the_recipient_and_batch(): void
    {
        Mail::fake();

        $batch = WorkerEmail::factory()->create();
        $recipient = WorkerEmailRecipient::factory()->for($batch)->create(['email' => 'worker@example.com']);

        app()->call([new SendWorkerEmail($recipient), 'handle']);

        Mail::assertSent(WorkerEmailMessage::class, fn (WorkerEmailMessage $mail): bool => $mail->hasTo('worker@example.com'));

        $recipient->refresh();
        $this->assertSame(WorkerEmailRecipientStatus::Sent, $recipient->status);
        $this->assertNotNull($recipient->sent_at);
        $this->assertSame(1, $recipient->attempts);

        $this->assertSame(WorkerEmailStatus::Completed, $batch->fresh()->status);
    }

    public function test_a_roster_batch_generates_the_workers_file_at_send_time(): void
    {
        Mail::fake();
        Storage::fake('local');

        $project = Project::factory()->create(['title' => 'GST']);
        $worker = User::factory()->worker()->create(['name' => '신진욱']);

        ProjectShareholder::factory()
            ->for($project)
            ->for(Shareholder::factory()->create(['name' => '홍길동', 'date_of_birth_code' => '800101']))
            ->create(['no' => 1])
            ->workers()->sync([$worker->id]);

        $batch = WorkerEmail::factory()->for($project)->create([
            'include_roster_xlsx' => true,
            'include_roster_pdf' => false,
        ]);
        $recipient = WorkerEmailRecipient::factory()->for($batch)->for($worker, 'worker')->create();

        app()->call([new SendWorkerEmail($recipient), 'handle']);

        $recipient->refresh();
        $this->assertSame(WorkerEmailRecipientStatus::Sent, $recipient->status);
        $this->assertNotNull($recipient->roster_xlsx_path);
        Storage::disk('local')->assertExists($recipient->roster_xlsx_path);
        $this->assertNull($recipient->roster_pdf_path);
    }

    public function test_the_rendered_email_carries_body_links_and_attachments(): void
    {
        // A real (array) transport so the Mailable actually renders and
        // resolves its attachments — Mail::fake() skips both.
        config()->set('mail.default', 'array');
        Storage::fake('local');
        Storage::disk('local')->put('resources/9/guide.pdf', '%PDF-1.4 fake');

        $batch = WorkerEmail::factory()->create([
            'subject' => 'Meeting notice',
            'body' => "Line one\nLine two",
            'links' => [['title' => 'Guide', 'url' => 'https://example.com/guide']],
            'attachments' => [['resource_id' => 1, 'filename' => 'guide.pdf', 'path' => 'resources/9/guide.pdf']],
        ]);
        $recipient = WorkerEmailRecipient::factory()->for($batch)->create(['email' => 'worker@example.com']);

        app()->call([new SendWorkerEmail($recipient), 'handle']);

        $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        $email = $messages->first()->getOriginalMessage();
        $this->assertSame('Meeting notice', $email->getSubject());
        $this->assertSame('worker@example.com', $email->getTo()[0]->getAddress());

        $html = (string) $email->getHtmlBody();
        $this->assertStringContainsString('Line one<br />', $html);
        $this->assertStringContainsString('https://example.com/guide', $html);

        $this->assertSame(['guide.pdf'], array_map(
            fn ($part): ?string => $part->getFilename(),
            $email->getAttachments(),
        ));

        $this->assertSame(WorkerEmailRecipientStatus::Sent, $recipient->fresh()->status);
    }

    public function test_exhausted_retries_mark_the_recipient_and_batch_failed(): void
    {
        $batch = WorkerEmail::factory()->create();
        $failing = WorkerEmailRecipient::factory()->for($batch)->create();
        $sent = WorkerEmailRecipient::factory()->for($batch)->sent()->create();

        (new SendWorkerEmail($failing))->failed(new RuntimeException('SMTP connection refused'));

        $failing->refresh();
        $this->assertSame(WorkerEmailRecipientStatus::Failed, $failing->status);
        $this->assertSame('SMTP connection refused', $failing->error_message);

        // One sent + one failed and none unresolved → the batch is failed.
        $this->assertSame(WorkerEmailStatus::Failed, $batch->fresh()->status);
    }

    public function test_an_already_sent_recipient_is_not_resent(): void
    {
        Mail::fake();

        $recipient = WorkerEmailRecipient::factory()->sent()->create();

        app()->call([new SendWorkerEmail($recipient), 'handle']);

        Mail::assertNothingSent();
    }
}
