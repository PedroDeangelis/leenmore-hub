<?php

namespace App\Support\Supabase;

/**
 * One 판단 (judgment) option on a project — "위임(신분증)", "거부", "본인부재".
 *
 * `isProxy` is colour-derived: a green 판단 is a granted proxy (위임). That rule
 * holds across the whole production dataset — all 639 green options on all 123
 * projects are 위임 variants — which is why it stays a rule rather than a list
 * of names someone has to maintain per project.
 */
final readonly class Judgment
{
    public function __construct(
        /** Position in the project's results array — what a shareholder's `result` points at. */
        public int $index,
        public string $name,
        public string $color,
        public bool $contactRequired,
        public bool $attachmentRequired,
    ) {}

    public function isProxy(): bool
    {
        return $this->color === 'green';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->index,
            'name' => $this->name,
            'color' => $this->color,
            'is_proxy' => $this->isProxy(),
            'contact_required' => $this->contactRequired,
            'attachment_required' => $this->attachmentRequired,
        ];
    }
}
