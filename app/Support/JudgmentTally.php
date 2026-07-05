<?php

namespace App\Support;

use App\Enums\ResultColor;
use App\Models\Project;

/**
 * The 판단 결과 현황 tally: the sum of project shares behind each current 판단
 * (the shareholders' `result_id`), grouped into colour bands and measured
 * against the target. Only judged 판단 with shares appear. Shared by the
 * project dashboard panel and the judgment-results Excel export so both always
 * show the same numbers.
 */
final readonly class JudgmentTally
{
    private function __construct(
        /**
         * Colour bands in 판단 display order. Consecutive 판단 sharing a colour
         * merge into one band with a combined total.
         *
         * @var list<array{color: ResultColor, rows: list<array{name: string, color: ResultColor, shares: int, percent: ?float}>, totalShares: int, totalPercent: ?float}>
         */
        public array $groups,
    ) {}

    /**
     * Tally a project's judged shares against its target.
     */
    public static function for(Project $project): self
    {
        $target = (int) ($project->shares_target ?? 0);

        // Sum of project shares behind each current 판단 (judged rows only).
        // reorder() drops the relationship's default `order by no`, which MySQL's
        // only_full_group_by mode rejects alongside this GROUP BY.
        $sums = $project->shareholders()
            ->reorder()
            ->whereNotNull('result_id')
            ->groupBy('result_id')
            ->selectRaw('result_id, COALESCE(SUM(shares), 0) as shares')
            ->pluck('shares', 'result_id');

        $groups = [];

        foreach ($project->results as $result) {
            $shares = (int) ($sums[$result->id] ?? 0);

            if ($shares === 0) {
                continue; // Only populated 판단 are listed.
            }

            $row = [
                'name' => $result->name,
                'color' => $result->color,
                'shares' => $shares,
                'percent' => $target > 0 ? $shares / $target * 100 : null,
            ];

            // Consecutive results sharing a colour merge into one total group.
            $last = array_key_last($groups);

            if ($last !== null && $groups[$last]['color'] === $result->color) {
                $groups[$last]['rows'][] = $row;
                $groups[$last]['totalShares'] += $shares;
            } else {
                $groups[] = ['color' => $result->color, 'rows' => [$row], 'totalShares' => $shares];
            }
        }

        foreach ($groups as &$group) {
            $group['totalPercent'] = $target > 0 ? $group['totalShares'] / $target * 100 : null;
        }

        return new self($groups);
    }

    /**
     * Whether anything has been judged yet.
     */
    public function hasData(): bool
    {
        return $this->groups !== [];
    }
}
