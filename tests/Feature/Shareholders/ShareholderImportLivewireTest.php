<?php

namespace Tests\Feature\Shareholders;

use App\Livewire\Projects\ShareholderImport;
use App\Models\Project;
use App\Models\Shareholder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ShareholderImportLivewireTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = '연번,실명번호,성별,주주명,주식수,총소유주식수,전자투표,주소,주소서치,주소서치2,구연락처,연락처,활동가,구 판단,구 멘트,비고,전자위임연락처,전자위임날짜';

    private function csv(int $rows = 1): string
    {
        $lines = [self::HEADER];
        for ($i = 1; $i <= $rows; $i++) {
            $reg = str_pad((string) $i, 6, '0', STR_PAD_LEFT).'-1234567';
            $lines[] = "{$i},{$reg},M,주주{$i},100,100,no,서울,010-0000-0000,,,,,,,,,";
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Poll step() until the import finishes (or a guard trips).
     */
    private function runToCompletion($component)
    {
        $guard = 0;
        do {
            $component->call('step');
        } while ($component->get('importing') && ++$guard < 100);

        return $component;
    }

    public function test_admins_can_import_a_roster_end_to_end(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $this->actingAs($admin);

        $file = UploadedFile::fake()->createWithContent('roster.csv', $this->csv(1));

        $component = Livewire::test(ShareholderImport::class, ['project' => $project])
            ->set('file', $file)
            ->call('start')
            ->assertHasNoErrors()
            ->assertSet('importing', true)
            ->assertSet('status', 'preparing');

        // First step parses into chunks; the next imports them.
        $component->call('step')
            ->assertSet('status', 'processing')
            ->assertSet('total', 1);

        $this->runToCompletion($component)
            ->assertSet('importing', false)
            ->assertDispatched('shareholders-imported')
            ->assertDispatched('toast');

        $this->assertSame(1, $project->shareholders()->count());
        $this->assertNotNull(Shareholder::firstWhere('registration', '0000011234567'));
    }

    public function test_a_multi_chunk_file_advances_progress_then_completes(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $this->actingAs($admin);

        // 1200 rows > the 1000-row chunk, so it spans two processing steps.
        $file = UploadedFile::fake()->createWithContent('big.csv', $this->csv(1200));

        $component = Livewire::test(ShareholderImport::class, ['project' => $project])
            ->set('file', $file)
            ->call('start');

        $component->call('step')->assertSet('total', 1200); // prepare

        $component->call('step')
            ->assertSet('importing', true)
            ->assertSet('processed', 1000); // first chunk

        $this->runToCompletion($component)->assertSet('importing', false);

        $this->assertSame(1200, $project->shareholders()->count());
    }

    public function test_office_users_can_import(): void
    {
        Storage::fake('local');
        $office = User::factory()->office()->create();
        $project = Project::factory()->create();
        $this->actingAs($office);

        $file = UploadedFile::fake()->createWithContent('roster.csv', $this->csv(1));

        $component = Livewire::test(ShareholderImport::class, ['project' => $project])
            ->set('file', $file)
            ->call('start')
            ->assertHasNoErrors();

        $this->runToCompletion($component);

        $this->assertSame(1, $project->shareholders()->count());
    }

    public function test_workers_cannot_import(): void
    {
        $worker = User::factory()->worker()->create();
        $project = Project::factory()->create();
        $this->actingAs($worker);

        Livewire::test(ShareholderImport::class, ['project' => $project])
            ->call('start')
            ->assertForbidden();
    }

    public function test_a_non_spreadsheet_file_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $this->actingAs($admin);

        $file = UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf');

        Livewire::test(ShareholderImport::class, ['project' => $project])
            ->set('file', $file)
            ->call('start')
            ->assertHasErrors(['file']);

        $this->assertSame(0, $project->shareholders()->count());
    }
}
