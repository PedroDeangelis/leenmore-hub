<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * How long until a project's shareholder meeting (its end date). Built from
 * "today" each time, so the show page always reflects the current day — counting
 * the meeting as upcoming, happening today, or already passed.
 */
final readonly class MeetingCountdown
{
    private function __construct(
        public Carbon $meetingDate,
        /** Whole days from today to the meeting: > 0 upcoming, 0 today, < 0 passed. */
        public int $daysLeft,
    ) {}

    /**
     * Build the countdown for a meeting date, or null when none is set.
     */
    public static function for(?CarbonInterface $meetingDate): ?self
    {
        if ($meetingDate === null) {
            return null;
        }

        $meetingDate = Carbon::instance($meetingDate)->startOfDay();
        $daysLeft = (int) round(Carbon::now()->startOfDay()->diffInDays($meetingDate, false));

        return new self($meetingDate, $daysLeft);
    }

    public function isUpcoming(): bool
    {
        return $this->daysLeft > 0;
    }

    public function isToday(): bool
    {
        return $this->daysLeft === 0;
    }

    public function hasPassed(): bool
    {
        return $this->daysLeft < 0;
    }
}
