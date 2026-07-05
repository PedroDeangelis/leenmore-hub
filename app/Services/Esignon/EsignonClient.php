<?php

namespace App\Services\Esignon;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP client for the ESIGNON (전자위임) external API at docs.esignon.net — the
 * Laravel port of the legacy leenmore-storage `EsignonClient\Client`. Handles
 * token login/caching and downloading a project link's completed proxy
 * documents, which arrive as a delimited (CSV-ish) spreadsheet body whose
 * `Text_*` columns must be heuristically resolved into name / DOB / contact.
 */
class EsignonClient
{
    private const TOKEN_CACHE_KEY = 'esignon.access_token';

    /** Known ESIGNON download columns → our field names. */
    private const DOWNLOAD_HEADER_MAP = [
        '완료된 링크 문서 ID' => 'link_id',
        '완료일' => 'completed_date',
        '요청자 이메일' => 'email',
        'DateTime_0' => 'datetime_0',
        'Radio_1-0' => 'radio_1_0',
        'Radio_1-1' => 'radio_1_1',
        'Radio_1-2' => 'radio_1_2',
        'Radio_2-0' => 'radio_2_0',
        'Radio_2-1' => 'radio_2_1',
        'Radio_2-2' => 'radio_2_2',
        'Sign_0' => 'sign_0',
    ];

    /** Fields inferred from the free-form `Text_*` columns. */
    private const INFERRED_TEXT_FIELDS = ['name', 'dob', 'contact'];

    private string $baseUrl;

    private string $companyId;

    private string $email;

    private string $password;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.esignon.base_url'), '/');
        $this->companyId = (string) config('services.esignon.company_id');
        $this->email = (string) config('services.esignon.email');
        $this->password = (string) config('services.esignon.password');

        if ($this->baseUrl === '' || $this->companyId === '' || $this->email === '' || $this->password === '') {
            throw new RuntimeException('Missing ESIGNON config (base_url, company_id, email, password).');
        }
    }

    /**
     * Download a project link's completed documents, parsed into per-signer
     * records (`link_id`, `completed_date`, `name`, `dob`, `contact`, …). Returns
     * an empty list when the link has no completed documents yet.
     *
     * @return list<array<string, ?string>>
     */
    public function download(string $linkManageId): array
    {
        $response = $this->authedGet("/v3/link/{$linkManageId}/excel", [
            'offset' => '+09:00',
            'download_condition' => 'link_id,completed_date,field',
        ], [
            'Accept' => 'application/vnd.ms-excel',
        ]);

        // 404 here means "완료된 링크문서가 없어..." — nothing signed yet.
        if ($response->status() === 404) {
            return [];
        }

        if ($response->failed()) {
            throw new RuntimeException("ESIGNON download failed HTTP {$response->status()}: {$response->body()}");
        }

        return $this->parseDelimitedBody($response->body());
    }

    /**
     * Log in and cache the bearer token, returning it.
     */
    public function login(): string
    {
        $response = Http::acceptJson()->asJson()->post("{$this->baseUrl}/{$this->companyId}/login", [
            'header' => ['request_code' => '1001Q'],
            'body' => [
                'memb_email' => $this->email,
                'memb_pwd' => $this->password,
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException("ESIGNON login failed HTTP {$response->status()}: {$response->body()}");
        }

        $token = $response->json('body.access_token');
        $expireDate = $response->json('body.expire_date');

        if (! $token || ! $expireDate) {
            throw new RuntimeException("ESIGNON login missing fields: {$response->body()}");
        }

        $this->cacheToken((string) $token, (string) $expireDate);

        return (string) $token;
    }

    /**
     * The cached token, logging in afresh when there is none.
     */
    public function getValidToken(): string
    {
        return Cache::get(self::TOKEN_CACHE_KEY) ?? $this->login();
    }

    private function cacheToken(string $token, string $expireDate): void
    {
        $expireTs = strtotime($expireDate);
        $seconds = $expireTs !== false ? $expireTs - time() : 1800;

        // Refresh a minute early; never cache for a non-positive span.
        Cache::put(self::TOKEN_CACHE_KEY, $token, max(60, $seconds - 60));
    }

    /**
     * GET an authed endpoint, re-logging-in once if the token was rejected.
     *
     * @param  array<string, string>  $query
     * @param  array<string, string>  $headers
     */
    private function authedGet(string $path, array $query, array $headers): Response
    {
        $response = $this->sendGet($path, $query, $headers, $this->getValidToken());

        if (in_array($response->status(), [401, 403], true)) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $this->sendGet($path, $query, $headers, $this->login());
        }

        return $response;
    }

    /**
     * @param  array<string, string>  $query
     * @param  array<string, string>  $headers
     */
    private function sendGet(string $path, array $query, array $headers, string $token): Response
    {
        return Http::withHeaders($headers)
            ->withHeader('Authorization', 'esignon '.$token)
            ->get($this->baseUrl.'/'.ltrim($path, '/'), $query);
    }

    /**
     * Turn the delimited download body into per-signer records: the known mapped
     * columns plus name / DOB / contact inferred from the `Text_*` columns.
     *
     * @return list<array<string, ?string>>
     */
    private function parseDelimitedBody(string $body): array
    {
        if ($body === '' || str_contains($body, "\0")) {
            return [];
        }

        $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $rows[] = str_getcsv($line);
        }

        if ($rows === []) {
            return [];
        }

        $rawHeaders = array_map(
            static fn ($value): string => self::stripUtf8Bom((string) $value),
            array_shift($rows)
        );

        $downloadColumns = $this->selectDownloadColumns($rawHeaders);
        $selectedColumns = $downloadColumns['mapped'];
        $textColumnIndexes = $downloadColumns['text'];

        if ($selectedColumns === [] && $textColumnIndexes === []) {
            return [];
        }

        $headers = array_merge(
            array_column($selectedColumns, 'name'),
            self::INFERRED_TEXT_FIELDS
        );
        $records = [];
        $rawHeaderCount = count($rawHeaders);

        foreach ($rows as $row) {
            $normalizedRow = array_slice(array_pad($row, $rawHeaderCount, null), 0, $rawHeaderCount);
            $filteredRow = [];

            foreach ($selectedColumns as $column) {
                $filteredRow[] = $normalizedRow[$column['index']] ?? null;
            }

            $textValues = [];

            foreach ($textColumnIndexes as $textColumnIndex) {
                $textValues[] = $normalizedRow[$textColumnIndex] ?? null;
            }

            $inferredTextFields = $this->inferTextFields($textValues);

            foreach (self::INFERRED_TEXT_FIELDS as $field) {
                $filteredRow[] = $inferredTextFields[$field];
            }

            $records[] = array_combine($headers, $filteredRow);
        }

        return $records;
    }

    /**
     * Split the raw header row into mapped columns and free-form text columns.
     *
     * @param  array<int, string>  $rawHeaders
     * @return array{mapped: list<array{index: int, name: string}>, text: list<int>}
     */
    private function selectDownloadColumns(array $rawHeaders): array
    {
        $selectedColumns = [];
        $textColumnIndexes = [];

        foreach ($rawHeaders as $index => $header) {
            if ($this->isTextHeader($header)) {
                $textColumnIndexes[] = $index;

                continue;
            }

            $mappedName = self::DOWNLOAD_HEADER_MAP[$header] ?? null;
            if ($mappedName === null) {
                continue;
            }

            $selectedColumns[] = [
                'index' => $index,
                'name' => $mappedName,
            ];
        }

        return [
            'mapped' => $selectedColumns,
            'text' => $textColumnIndexes,
        ];
    }

    /**
     * Resolve name / DOB / contact out of the free-form text column values.
     *
     * @param  array<int, ?string>  $textValues
     * @return array{name: string, dob: string, contact: string}
     */
    private function inferTextFields(array $textValues): array
    {
        $candidates = [];

        foreach ($textValues as $index => $value) {
            $normalizedValue = trim((string) ($value ?? ''));

            if ($normalizedValue === '') {
                continue;
            }

            $candidates[] = [
                'index' => $index,
                'value' => $normalizedValue,
            ];
        }

        if ($candidates === []) {
            return ['name' => '', 'dob' => '', 'contact' => ''];
        }

        $dobIndex = $this->findDobIndex($candidates);
        $contactIndex = $this->findContactIndex($candidates, $dobIndex);

        $dob = '';
        $contact = '';

        foreach ($candidates as $candidate) {
            if ($candidate['index'] === $dobIndex) {
                $dob = $candidate['value'];
            }

            if ($candidate['index'] === $contactIndex) {
                $contact = $candidate['value'];
            }
        }

        $name = '';
        $nameIndex = $this->findNameIndex($candidates, $dobIndex, $contactIndex);

        foreach ($candidates as $candidate) {
            if ($candidate['index'] === $nameIndex) {
                $name = $candidate['value'];
                break;
            }
        }

        return ['name' => $name, 'dob' => $dob, 'contact' => $contact];
    }

    /**
     * @param  list<array{index: int, value: string}>  $candidates
     */
    private function findDobIndex(array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            if ($this->looksLikeDob($candidate['value'])) {
                return $candidate['index'];
            }
        }

        return null;
    }

    /**
     * @param  list<array{index: int, value: string}>  $candidates
     */
    private function findContactIndex(array $candidates, ?int $dobIndex): ?int
    {
        if ($dobIndex !== null) {
            foreach ($candidates as $candidate) {
                if ($candidate['index'] > $dobIndex && $this->looksLikeContact($candidate['value'])) {
                    return $candidate['index'];
                }
            }
        }

        foreach ($candidates as $candidate) {
            if ($this->looksLikeContact($candidate['value'])) {
                return $candidate['index'];
            }
        }

        return null;
    }

    /**
     * @param  list<array{index: int, value: string}>  $candidates
     */
    private function findNameIndex(array $candidates, ?int $dobIndex, ?int $contactIndex): ?int
    {
        $anchorIndex = $dobIndex ?? $contactIndex;
        $reservedIndexes = array_filter(
            [$dobIndex, $contactIndex],
            static fn (?int $value): bool => $value !== null
        );

        if ($anchorIndex !== null) {
            for ($i = count($candidates) - 1; $i >= 0; $i--) {
                $candidateIndex = $candidates[$i]['index'];

                if ($candidateIndex >= $anchorIndex || in_array($candidateIndex, $reservedIndexes, true)) {
                    continue;
                }

                return $candidateIndex;
            }
        }

        foreach ($candidates as $candidate) {
            if (! in_array($candidate['index'], $reservedIndexes, true)) {
                return $candidate['index'];
            }
        }

        return null;
    }

    private function isTextHeader(string $header): bool
    {
        return preg_match('/^Text(?:_\d+)?$/', $header) === 1;
    }

    private function looksLikeDob(string $value): bool
    {
        if ($this->looksLikeContact($value)) {
            return false;
        }

        $digitsOnly = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digitsOnly) < 6) {
            return false;
        }

        if (preg_match('/^\d{6}(?:$|[^\d].*)/', $value) === 1) {
            return true;
        }

        return preg_match('/^\d{7}$/', $digitsOnly) === 1;
    }

    private function looksLikeContact(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        if (preg_match('/[-\s]/', $value) === 1) {
            return preg_match(
                '/^(?:\+?82[-\s]?)?(?:0(?:10|11|1[6-9]|70|50)|0\d{1,2})[-\s]?\d{3,4}(?:(?:[-\s]\d{3,4})|[-\s])$/',
                $value
            ) === 1;
        }

        $digitsOnly = preg_replace('/\D/', '', $value) ?? '';

        if ($digitsOnly === '') {
            return false;
        }

        if (str_starts_with($digitsOnly, '82')) {
            $digitsOnly = '0'.substr($digitsOnly, 2);
        }

        $digitCount = strlen($digitsOnly);

        if ($digitCount < 9 || $digitCount > 11) {
            return false;
        }

        return preg_match('/^(?:0(?:10|11|1[6-9]|70|50)|0\d{1,2})\d+$/', $digitsOnly) === 1;
    }

    private static function stripUtf8Bom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }
}
