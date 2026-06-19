<?php

namespace Tests\Feature\Reports;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_users_can_download_a_report_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('submissions/1/proof.jpg', 'binary-data');

        $admin = User::factory()->admin()->create();
        $submission = Submission::factory()->create(['files' => ['submissions/1/proof.jpg']]);

        $this->actingAs($admin)
            ->get(route('reports.file', ['submission' => $submission, 'index' => 0]))
            ->assertOk();
    }

    public function test_an_unknown_file_index_returns_404(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $submission = Submission::factory()->create(['files' => ['submissions/1/proof.jpg']]);

        $this->actingAs($admin)
            ->get(route('reports.file', ['submission' => $submission, 'index' => 9]))
            ->assertNotFound();
    }

    public function test_workers_cannot_download_report_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('submissions/1/proof.jpg', 'binary-data');

        $worker = User::factory()->worker()->create();
        $submission = Submission::factory()->create(['files' => ['submissions/1/proof.jpg']]);

        $this->actingAs($worker)
            ->get(route('reports.file', ['submission' => $submission, 'index' => 0]))
            ->assertRedirect(route('worker.dashboard'));
    }
}
