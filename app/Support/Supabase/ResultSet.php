<?php

namespace App\Support\Supabase;

/**
 * A project's 판단 options, decoded.
 *
 * Supabase stores these as `text[]` where each element is itself a JSON string,
 * so reading one takes two steps: array element, then json_decode. A shareholder
 * or submission then refers to a judgment by its *array index* as a string
 * ("0".."14"), not by name — so resolving one means indexing this collection.
 *
 * Both of those are easy to get wrong from the schema alone (the audit notes had
 * `results` as a JSON array of objects and `result` as a name), which is why the
 * decoding lives in one place with tests on it.
 */
final readonly class ResultSet
{
    /**
     * @param  list<Judgment>  $judgments
     */
    private function __construct(public array $judgments) {}

    /**
     * @param  list<string>|null  $rawResults  the project row's `results` column
     */
    public static function fromColumn(?array $rawResults): self
    {
        $judgments = [];

        foreach ($rawResults ?? [] as $index => $element) {
            $decoded = is_string($element) ? json_decode($element, true) : $element;

            if (! is_array($decoded) || ! isset($decoded['name'])) {
                continue;
            }

            // `order` is present on only some rows, so the array index — which is
            // what `result` actually points at — is the position of record.
            $judgments[] = new Judgment(
                index: $index,
                name: (string) $decoded['name'],
                color: (string) ($decoded['color'] ?? 'default'),
                contactRequired: (bool) ($decoded['contactRequired'] ?? false),
                attachmentRequired: (bool) ($decoded['attachmentRequired'] ?? false),
            );
        }

        return new self($judgments);
    }

    /**
     * Resolve the index a shareholder or submission stores. Null for an unjudged
     * row, and also for an index pointing past the end — which happens when the
     * client shortens a project's option list after rows already referenced it.
     */
    public function resolve(?string $index): ?Judgment
    {
        if ($index === null || ! is_numeric($index)) {
            return null;
        }

        return $this->judgments[(int) $index] ?? null;
    }

    /**
     * The indices of every 위임 (proxy) judgment, as strings — ready for a
     * PostgREST `result=in.(...)` filter.
     *
     * @return list<string>
     */
    public function proxyIndices(): array
    {
        return array_values(array_map(
            fn (Judgment $j): string => (string) $j->index,
            array_filter($this->judgments, fn (Judgment $j): bool => $j->isProxy()),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(fn (Judgment $j): array => $j->toArray(), $this->judgments);
    }
}
