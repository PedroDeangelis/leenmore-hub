<?php

namespace App\Enums;

/**
 * The fixed palette a project result (판단) can use. This is the single global
 * source of truth for result colours — referenced wherever results are shown
 * (the 판단 panel, the 판단 결과 현황 bands, the shareholder list, …).
 *
 * The class-string accessors return literal Tailwind utilities. `app/Enums` is
 * registered as a Tailwind `@source` (see resources/css/app.css) so these
 * classes are emitted even though they only appear here.
 */
enum ResultColor: string
{
    case Green = 'green';
    case Lime = 'lime';
    case Blue = 'blue';
    case Gold = 'gold';
    case Orange = 'orange';
    case Red = 'red';
    case Violet = 'violet';
    case Gray = 'gray';

    /**
     * Human label for colour pickers.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Base swatch colour (for dots / native inputs).
     */
    public function hex(): string
    {
        return match ($this) {
            self::Green => '#34a36a',
            self::Lime => '#9cb52f',
            self::Blue => '#4b86e0',
            self::Gold => '#d4a72c',
            self::Orange => '#e08a45',
            self::Red => '#df5d6e',
            self::Violet => '#9a72e0',
            self::Gray => '#9aa0ab',
        };
    }

    /**
     * Pill (아이콘 색상) classes: soft background + readable text.
     */
    public function chipClasses(): string
    {
        return $this->set()['chip'];
    }

    /**
     * Row band background (판단 결과 현황 table).
     */
    public function bandClasses(): string
    {
        return $this->set()['band'];
    }

    /**
     * Left accent border colour (combine with a width utility, e.g. border-l-[3px]).
     */
    public function borderClasses(): string
    {
        return $this->set()['border'];
    }

    /**
     * Progress-bar fill.
     */
    public function barClasses(): string
    {
        return $this->set()['bar'];
    }

    /**
     * Emphasis text colour (percentages / totals).
     */
    public function accentText(): string
    {
        return $this->set()['accent'];
    }

    /**
     * Group-total cell classes (background + text).
     */
    public function totalClasses(): string
    {
        return $this->set()['total'];
    }

    /**
     * Full literal class set for this colour. One map per case so the variants
     * stay together and Tailwind can extract every utility.
     *
     * @return array<string, string>
     */
    private function set(): array
    {
        return match ($this) {
            self::Green => [
                'chip' => 'bg-[#d8efe0] text-[#157a43]',
                'band' => 'bg-[#eef8f1]', 'border' => 'border-l-[#34a36a]',
                'bar' => 'bg-[#34a36a]', 'accent' => 'text-[#15834a]',
                'total' => 'bg-[#ddefe3] text-[#157a43]',
            ],
            self::Lime => [
                'chip' => 'bg-[#e9f1d3] text-[#6b8211]',
                'band' => 'bg-[#f4f8e9]', 'border' => 'border-l-[#9cb52f]',
                'bar' => 'bg-[#9cb52f]', 'accent' => 'text-[#6b8211]',
                'total' => 'bg-[#e9f1d3] text-[#6b8211]',
            ],
            self::Blue => [
                'chip' => 'bg-[#dde9f8] text-[#2563c9]',
                'band' => 'bg-[#eef4fc]', 'border' => 'border-l-[#4b86e0]',
                'bar' => 'bg-[#4b86e0]', 'accent' => 'text-[#2563c9]',
                'total' => 'bg-[#dde9f8] text-[#2563c9]',
            ],
            self::Gold => [
                'chip' => 'bg-[#f5e9bf] text-[#8c6a05]',
                'band' => 'bg-[#fdf7e8]', 'border' => 'border-l-[#d4a72c]',
                'bar' => 'bg-[#d4a72c]', 'accent' => 'text-[#9a7406]',
                'total' => 'bg-[#f7edcc] text-[#9a7406]',
            ],
            self::Orange => [
                'chip' => 'bg-[#f9ddc7] text-[#b5611e]',
                'band' => 'bg-[#fdf1e8]', 'border' => 'border-l-[#e08a45]',
                'bar' => 'bg-[#e08a45]', 'accent' => 'text-[#bd6320]',
                'total' => 'bg-[#f9e3d2] text-[#bd6320]',
            ],
            self::Red => [
                'chip' => 'bg-[#f7d6dc] text-[#b53048]',
                'band' => 'bg-[#fcedef]', 'border' => 'border-l-[#df5d6e]',
                'bar' => 'bg-[#df5d6e]', 'accent' => 'text-[#bb3346]',
                'total' => 'bg-[#f8dadf] text-[#bb3346]',
            ],
            self::Violet => [
                'chip' => 'bg-[#e8ddf6] text-[#6f48c9]',
                'band' => 'bg-[#f4eefb]', 'border' => 'border-l-[#9a72e0]',
                'bar' => 'bg-[#9a72e0]', 'accent' => 'text-[#6f48c9]',
                'total' => 'bg-[#e8ddf6] text-[#6f48c9]',
            ],
            self::Gray => [
                'chip' => 'bg-[#e7e9ee] text-[#9aa0ab]',
                'band' => 'bg-[#f6f7f9]', 'border' => 'border-l-[#c2c7d0]',
                'bar' => 'bg-[#c2c7d0]', 'accent' => 'text-[#6b7280]',
                'total' => 'bg-[#eceef1] text-[#6b7280]',
            ],
        };
    }
}
