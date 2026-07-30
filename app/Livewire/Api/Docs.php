<?php

namespace App\Livewire\Api;

use App\Support\ApiCatalog;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One page per REST endpoint: what it does, its parameters, a live "Try it"
 * tester, and a ready-to-run curl example. The endpoint definition — including
 * the handler the tester runs — comes entirely from {@see ApiCatalog}, so these
 * pages and the real API can never describe different things.
 *
 * The tester runs the endpoint's query in-process (the admin is already
 * authorised in the dashboard and sees the same masked data), so it needs no
 * token; the curl example is the real external call, with a token placeholder.
 */
#[Title('API endpoint')]
class Docs extends Component
{
    #[Locked]
    public string $endpoint;

    /** @var array<string, mixed> Form values, keyed by parameter name. */
    public array $params = [];

    /** Pretty-printed JSON of the last test run, or null before one. */
    public ?string $result = null;

    public ?int $resultStatus = null;

    public function mount(string $endpoint): void
    {
        if (! ApiCatalog::has($endpoint)) {
            abort(404);
        }

        $this->endpoint = $endpoint;
    }

    /**
     * Run the endpoint against the entered parameters and capture the JSON.
     */
    public function run(): void
    {
        $definition = ApiCatalog::find($this->endpoint);
        $input = $this->cleanedInput($definition);

        $this->validate($this->testerRules($definition));

        try {
            $data = ($definition['run'])($input);
        } catch (\Throwable $e) {
            $this->result = json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->resultStatus = 500;

            return;
        }

        if ($data === null) {
            $this->result = json_encode(['message' => 'Not found.'], JSON_PRETTY_PRINT);
            $this->resultStatus = 404;

            return;
        }

        $this->result = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->resultStatus = 200;
    }

    /**
     * Add a row to a repeater param (e.g. the activity-data days list).
     */
    public function addListItem(string $param): void
    {
        $this->params[$param] = [...(array) ($this->params[$param] ?? []), ''];
    }

    public function removeListItem(string $param, int $index): void
    {
        $values = (array) ($this->params[$param] ?? []);
        unset($values[$index]);

        $this->params[$param] = array_values($values);
    }

    /**
     * Non-empty entered params, keyed by parameter name. Repeater values are
     * joined into the comma-separated string the endpoint documents.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function cleanedInput(array $definition): array
    {
        $input = [];

        foreach ($definition['params'] as $param) {
            $value = $this->params[$param['name']] ?? null;

            if (is_array($value)) {
                $value = $this->commaJoined($value);
            }

            if ($value === null || $value === '') {
                continue;
            }

            $input[$param['name']] = $value;
        }

        return $input;
    }

    /**
     * Repeater rows as the comma-separated query value, empty rows dropped.
     *
     * @param  array<int, mixed>  $values
     */
    private function commaJoined(array $values): string
    {
        return implode(',', array_values(array_filter(array_map(
            fn ($value): string => trim((string) $value),
            $values,
        ))));
    }

    /**
     * Livewire validation rules for the tester form, matching the endpoint's own.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function testerRules(array $definition): array
    {
        $rules = [];

        foreach ($definition['params'] as $param) {
            // Drop 'sometimes' — the tester validates whatever the box holds — but
            // keep 'required' for path params so an empty id is caught here.
            $rules['params.'.$param['name']] = array_values(array_filter(
                $param['rules'],
                fn (string $rule): bool => $rule !== 'sometimes',
            ));
        }

        return $rules;
    }

    public function render(): View
    {
        $definition = ApiCatalog::find($this->endpoint);

        return view('livewire.api.docs', [
            'definition' => $definition,
            'curl' => $this->curlExample($definition),
            'endpoints' => ApiCatalog::all(),
        ]);
    }

    /**
     * A copy-pasteable curl command for the real HTTP endpoint, with the path
     * params filled from the form and a token placeholder.
     *
     * @param  array<string, mixed>  $definition
     */
    private function curlExample(array $definition): string
    {
        $path = $definition['path'];
        $query = [];

        foreach ($definition['params'] as $param) {
            $value = $this->params[$param['name']] ?? '';

            if (is_array($value)) {
                $value = $this->commaJoined($value);
            }

            if ($param['in'] === 'path') {
                $path = str_replace('{'.$param['name'].'}', $value !== '' ? (string) $value : '{'.$param['name'].'}', $path);
            } elseif ($value !== '' && $value !== []) {
                $query[$param['name']] = $value;
            }
        }

        $url = rtrim(config('app.url'), '/').$path;

        if ($query !== []) {
            $url .= '?'.Arr::query($query);
        }

        return "curl '{$url}' \\\n  -H 'Authorization: Bearer YOUR_TOKEN'";
    }
}
