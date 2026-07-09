<?php

namespace Tests\Feature\EmailToWorker;

use App\Models\Project;
use App\Models\ProjectShareholder;
use App\Models\Shareholder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class RosterExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function assignmentFor(Project $project, User $worker, string $name, string $dobCode, int $no): ProjectShareholder
    {
        $assignment = ProjectShareholder::factory()
            ->for($project)
            ->for(Shareholder::factory()->create(['name' => $name, 'date_of_birth_code' => $dobCode]))
            ->create(['no' => $no]);

        $assignment->workers()->sync([$worker->id]);

        return $assignment;
    }

    private function sheetXml(string $path, string $sheet = 'xl/worksheets/sheet1.xml'): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $xml = (string) $zip->getFromName($sheet);
        $zip->close();

        return $xml;
    }

    public function test_a_workers_roster_downloads_as_xlsx_in_the_requested_order(): void
    {
        $project = Project::factory()->create(['title' => 'GST']);
        $worker = User::factory()->worker()->create(['name' => '신진욱']);
        $this->assignmentFor($project, $worker, '홍길동', '800101', 1);
        $this->assignmentFor($project, $worker, '김영희', '900202', 2);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('email-to-worker.roster', [$project, $worker, 'type' => 'xlsx', 'sort' => 'no', 'desc' => 1]))
            ->assertOk()
            ->assertHeader('content-disposition');

        $xml = $this->sheetXml($response->baseResponse->getFile()->getPathname());

        $this->assertStringContainsString('홍길동800101', $xml);
        $this->assertStringContainsString('김영희900202', $xml);
        // desc by 연번: no=2 (김영희) is written before no=1 (홍길동).
        $this->assertLessThan(mb_strpos($xml, '홍길동800101'), mb_strpos($xml, '김영희900202'));
        // Title placeholders replaced with project and worker names.
        $this->assertStringContainsString('GST', $xml);
        $this->assertStringContainsString('(신진욱)', $xml);
    }

    public function test_the_pdf_variant_downloads(): void
    {
        $project = Project::factory()->create(['title' => 'GST']);
        $worker = User::factory()->worker()->create(['name' => '신진욱']);
        $this->assignmentFor($project, $worker, '홍길동', '800101', 1);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('email-to-worker.roster', [$project, $worker, 'type' => 'pdf']))
            ->assertOk();

        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertStringEndsWith('.pdf', $path);
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($path));
    }

    public function test_the_bundle_has_one_sheet_per_worker(): void
    {
        $project = Project::factory()->create(['title' => 'GST']);
        $first = User::factory()->worker()->create(['name' => '신진욱']);
        $second = User::factory()->worker()->create(['name' => '김활동']);
        $this->assignmentFor($project, $first, '홍길동', '800101', 1);
        $this->assignmentFor($project, $second, '김영희', '900202', 2);
        // A worker with no roster rows is skipped, not an empty sheet.
        $rowless = User::factory()->worker()->create(['name' => '박활동']);
        ProjectShareholder::factory()->create()->workers()->sync([$rowless->id]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('email-to-worker.roster.all', $project))
            ->assertOk();

        $path = $response->baseResponse->getFile()->getPathname();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $workbookXml = (string) $zip->getFromName('xl/workbook.xml');
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet2.xml'));
        $this->assertFalse($zip->locateName('xl/worksheets/sheet3.xml'));
        $zip->close();

        $this->assertStringContainsString('name="신진욱"', $workbookXml);
        $this->assertStringContainsString('name="김활동"', $workbookXml);
        $this->assertStringNotContainsString('박활동', $workbookXml);
    }

    public function test_missing_rows_wrong_worker_and_bad_type_are_not_found(): void
    {
        $project = Project::factory()->create();
        $worker = User::factory()->worker()->create();
        $this->assignmentFor($project, $worker, '홍길동', '800101', 1);
        $admin = User::factory()->admin()->create();

        // A worker who is not assigned to this project.
        $outsider = User::factory()->worker()->create();
        $this->actingAs($admin)->get(route('email-to-worker.roster', [$project, $outsider]))->assertNotFound();

        // An unknown format.
        $this->actingAs($admin)->get(route('email-to-worker.roster', [$project, $worker, 'type' => 'csv']))->assertNotFound();

        // A project where no worker has roster rows.
        $empty = Project::factory()->create();
        $this->actingAs($admin)->get(route('email-to-worker.roster.all', $empty))->assertNotFound();
    }
}
