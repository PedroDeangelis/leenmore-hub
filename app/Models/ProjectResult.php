<?php

namespace App\Models;

use App\Enums\ResultColor;
use Database\Factories\ProjectResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'color', 'contact_required', 'attachment_required', 'sort_order'])]
class ProjectResult extends Model
{
    /** @use HasFactory<ProjectResultFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => ResultColor::class,
            'contact_required' => 'boolean',
            'attachment_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The standard 판단 set applied to a new project. Drives both the create
     * flow and the backfill seeder.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaultSet(): array
    {
        $rows = [
            ['위임(대면_서명)', ResultColor::Green, true, true],
            ['위임(비대면_녹취)', ResultColor::Green, true, true],
            ['위임(비대면_문자)', ResultColor::Green, true, true],
            ['위임(대면_문자)', ResultColor::Green, true, true],
            ['위임(발급번호)', ResultColor::Green, true, true],
            ['위임(대면_신분증)', ResultColor::Green, true, true],
            ['위임(비대면_신분증)', ResultColor::Green, false, true],
            ['위임대기', ResultColor::Green, false, false],
            ['위임대기(전자위임)', ResultColor::Lime, false, false],
            ['유보', ResultColor::Blue, false, false],
            ['단순부재', ResultColor::Gold, false, false],
            ['본인부재', ResultColor::Gold, false, false],
            ['부재(콜)', ResultColor::Gold, true, false],
            ['소재불명/주소불명', ResultColor::Orange, false, false],
            ['소재불명(콜)', ResultColor::Orange, true, false],
            ['출입불가', ResultColor::Orange, false, false],
            ['거부', ResultColor::Red, false, false],
            ['주총직참', ResultColor::Violet, false, false],
            ['본부처리요망', ResultColor::Violet, false, false],
        ];

        return collect($rows)
            ->map(fn (array $row, int $i): array => [
                'name' => $row[0],
                'color' => $row[1],
                'contact_required' => $row[2],
                'attachment_required' => $row[3],
                'sort_order' => $i,
            ])
            ->all();
    }
}
