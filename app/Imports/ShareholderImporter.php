<?php

namespace App\Imports;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Shareholder;
use App\Models\User;
use App\Support\KoreanRegistration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Imports a project's shareholder roster from the firm's CSV/XLSX file.
 *
 * The 18 fixed Korean headers are legacy-mislabeled — e.g. 주소서치 carries the
 * contact number and 연락처 the worker name — so we map by the legacy field
 * meaning, not the literal header (see getFormatedShareholders.js). People are
 * matched across projects by their cleaned 실명번호 (registration); each row
 * upserts the person and their per-project assignment without disturbing a
 * worker-set result.
 */
class ShareholderImporter
{
    /**
     * Ordered column spec: field => [normalized Korean header, positional fallback].
     */
    private const COLUMNS = [
        'no' => ['연번', 0],
        'registration' => ['실명번호', 1],
        'sex' => ['성별', 2],
        'name' => ['주주명', 3],
        'shares' => ['주식수', 4],
        'shares_total' => ['총소유주식수', 5],
        'electronic_voting' => ['전자투표', 6],
        'address' => ['주소', 7],
        'contact_info' => ['주소서치', 8],
        'contact_info_2' => ['주소서치2', 9],
        'source_database' => ['구연락처', 10],
        'contact_worker' => ['연락처', 11],
        'worker_names' => ['활동가', 12],
        'prev_result' => ['구 판단', 13],
        'prev_comment' => ['구 멘트', 14],
        'prev_note' => ['비고', 15],
        'api_recipient_contact' => ['전자위임연락처', 16],
        'api_recipient_completion_date' => ['전자위임날짜', 17],
    ];

    /**
     * Lowercased, unambiguous worker name => user id. Built once per instance.
     *
     * @var array<string, int>|null
     */
    private ?array $workerMap = null;

    public function __construct(private readonly SpreadsheetReader $reader) {}

    public function import(Project $project, string $path, ?string $extension = null): ShareholderImportSummary
    {
        $rows = $this->parse($path, $extension);
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($project, $rows, &$created, &$updated, &$skipped): void {
            foreach ($rows as $row) {
                match ($this->importRow($project, $row)) {
                    'created' => $created++,
                    'updated' => $updated++,
                    default => $skipped++,
                };
            }
        });

        return new ShareholderImportSummary($created, $updated, $skipped);
    }

    /**
     * Stream the file's data rows (header resolved, no DB writes) one mapped row
     * at a time, so callers can chunk huge files without holding them in memory.
     *
     * @return iterable<int, array<string, string>>
     */
    public function eachRow(string $path, ?string $extension = null): iterable
    {
        $index = null;

        foreach ($this->reader->rows($path, $extension) as $cells) {
            if ($index === null) {
                $index = $this->resolveColumns($cells);

                continue;
            }

            yield $this->mapRow($cells, $index);
        }
    }

    /**
     * Eagerly read + map every data row (small files / tests).
     *
     * @return array<int, array<string, string>>
     */
    public function parse(string $path, ?string $extension = null): array
    {
        return iterator_to_array($this->eachRow($path, $extension), false);
    }

    /**
     * Upsert one mapped row's person + assignment.
     *
     * @param  array<string, string>  $row
     * @return 'created'|'updated'|'skipped'
     */
    public function importRow(Project $project, array $row): string
    {
        $registration = KoreanRegistration::from($row['registration']);

        if ($row['name'] === '' || $registration->digits === '') {
            return 'skipped';
        }

        $person = $this->upsertPerson($registration, $row);

        $exists = $project->shareholders()
            ->where('shareholder_id', $person->id)
            ->exists();

        $assignment = $project->shareholders()->updateOrCreate(
            ['shareholder_id' => $person->id],
            $this->assignmentAttributes($row),
        );

        // The CSV is authoritative for this row's workers; names that don't
        // resolve to exactly one worker user are silently dropped.
        $assignment->workers()->sync($this->resolveWorkerIds($row['worker_names']));

        return $exists ? 'updated' : 'created';
    }

    /**
     * Resolve the slash-separated 활동가 names to worker user ids, dropping any
     * name with no — or more than one — matching worker.
     *
     * @return array<int, int>
     */
    private function resolveWorkerIds(string $raw): array
    {
        $map = $this->workerMap();

        return collect($this->toNames($raw))
            ->map(fn (string $name): ?int => $map[mb_strtolower($name)] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Lowercased worker name => id, only for names held by exactly one worker
     * (duplicates are ambiguous and excluded). Memoized per import.
     *
     * @return array<string, int>
     */
    private function workerMap(): array
    {
        return $this->workerMap ??= User::query()
            ->where('role', UserRole::Worker->value)
            ->get(['id', 'name'])
            ->groupBy(fn (User $user): string => mb_strtolower(trim($user->name)))
            ->filter(fn ($group): bool => $group->count() === 1)
            ->map(fn ($group): int => $group->first()->id)
            ->all();
    }

    /**
     * The field keys, in column order — the shared layout for import + export.
     *
     * @return array<int, string>
     */
    public static function fields(): array
    {
        return array_keys(self::COLUMNS);
    }

    /**
     * The expected column headers, in order — for the downloadable template.
     *
     * @return array<int, string>
     */
    public static function templateHeaders(): array
    {
        return array_map(fn (array $column): string => $column[0], array_values(self::COLUMNS));
    }

    /**
     * One example row matching templateHeaders().
     *
     * @return array<int, string>
     */
    public static function templateSample(): array
    {
        return [
            '1', '900101-1234567', '남', '홍길동', '1,000', '2,000', 'Y',
            '서울특별시 강남구 …', '010-1234-5678', '', '', '', '김활동',
            '', '', '', '', '',
        ];
    }

    /**
     * Match each field to a column: by normalized header, else positional fallback.
     *
     * @param  array<int, string>  $header
     * @return array<string, int>
     */
    private function resolveColumns(array $header): array
    {
        $byName = [];
        foreach ($header as $i => $label) {
            $byName[$this->normalize($label)] = $i;
        }

        $index = [];
        foreach (self::COLUMNS as $field => [$label, $position]) {
            $index[$field] = $byName[$this->normalize($label)] ?? $position;
        }

        return $index;
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<string, int>  $index
     * @return array<string, string>
     */
    private function mapRow(array $cells, array $index): array
    {
        $row = [];
        foreach ($index as $field => $i) {
            $row[$field] = trim($cells[$i] ?? '');
        }

        return $row;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function upsertPerson(KoreanRegistration $registration, array $row): Shareholder
    {
        $person = Shareholder::firstOrNew(['registration' => $registration->digits]);

        // Seed identity + base contact/address only when first creating the
        // person; a later project must not overwrite the canonical record.
        if (! $person->exists) {
            $person->fill([
                'name' => $row['name'],
                'sex' => $row['sex'] ?: null,
                'person_type' => $registration->personType(),
                'date_of_birth' => $registration->dateOfBirth(),
                'date_of_birth_code' => $registration->dateOfBirthCode(),
                'code' => $registration->code() ?: null,
                'contact_info' => $row['contact_info'] ?: null,
                'address' => $row['address'] ?: null,
            ])->save();
        }

        return $person;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function assignmentAttributes(array $row): array
    {
        return [
            'no' => $this->toInt($row['no']),
            'shares' => $this->toInt($row['shares']),
            'shares_total' => $this->toInt($row['shares_total']),
            'contact_info' => $row['contact_info'] ?: null,
            'contact_info_2' => $row['contact_info_2'] ?: null,
            'source_database' => $row['source_database'] ?: null,
            'contact_worker' => $row['contact_worker'] ?: null,
            'electronic_voting' => $this->toBool($row['electronic_voting']),
            'prev_result' => $row['prev_result'] ?: null,
            'prev_comment' => $row['prev_comment'] ?: null,
            'prev_note' => $row['prev_note'] ?: null,
            'api_recipient_contact' => $row['api_recipient_contact'] ?: null,
            'api_recipient_completion_date' => $this->toDate($row['api_recipient_completion_date']),
            // result_id is intentionally omitted — worker submissions own it.
        ];
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/[^a-z0-9\x{3131}-\x{D7A3}]/u', '', mb_strtolower($value));

        return $value ?? '';
    }

    private function toInt(string $value): ?int
    {
        $digits = preg_replace('/\D/', '', $value);

        return ($digits === null || $digits === '') ? null : (int) $digits;
    }

    private function toBool(string $value): ?bool
    {
        $value = mb_strtolower(trim($value));

        return match (true) {
            $value === '' => null,
            in_array($value, ['y', 'yes', 'o', 'true', '1', '예', '사용'], true) => true,
            in_array($value, ['n', 'no', 'x', 'false', '0', '아니오', '미사용'], true) => false,
            default => null,
        };
    }

    /**
     * Split the slash-separated 활동가 names into a clean list.
     *
     * @return array<int, string>
     */
    private function toNames(string $value): array
    {
        return collect(explode('/', $value))
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->values()
            ->all();
    }

    private function toDate(string $value): ?string
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
