<?php

declare(strict_types=1);

namespace App\Modules\Audit\Actions;

use App\Modules\Audit\Domain\PriceLabeler;
use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Models\Audit;
use App\Modules\Audit\Models\AuditItem;
use App\Modules\Audit\Reading\FixtureRabReader;
use App\Modules\Audit\Reading\StandardRef;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use App\Modules\Standard\Models\PriceStandard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Populates an Audit immediately from a pre-cached fixture JSON using deterministic PriceLabeler rules.
 * Used for offline fixtures and instant sample RAB previews without calling external AI services.
 */
final class CreateAuditFromFixture
{
    public function __construct(
        private readonly FixtureRabReader $reader,
        private readonly PriceLabeler $labeler,
    ) {}

    public function execute(
        Village $village,
        User $owner,
        string $storedFilePath,
        string $source = 'fixture',
        ?int $year = null,
    ): Audit {
        $targetYear = $year ?? (int) CarbonImmutable::now((string) config('sonar.display_timezone', 'Asia/Makassar'))->year;
        $standards = PriceStandard::catalogForRegencyAndYear($village->regency, $targetYear);

        $standardRefs = $standards->map(static fn (PriceStandard $s): StandardRef => new StandardRef(
            code: $s->code,
            name: $s->name,
            unit: $s->unit,
        ))->all();

        $standardsByCode = $standards->keyBy('code');
        $realPath = file_exists($storedFilePath)
            ? $storedFilePath
            : (Storage::disk('local')->exists($storedFilePath) ? Storage::disk('local')->path($storedFilePath) : $storedFilePath);

        $result = $this->reader->read($realPath, $standardRefs);
        $toleranceRp = (int) config('sonar.price.row_tolerance_rp', 1000);

        return DB::transaction(function () use ($village, $owner, $storedFilePath, $source, $result, $standardsByCode, $toleranceRp): Audit {
            $audit = Audit::withoutGlobalScopes()->create([
                'village_id' => $village->id,
                'owner_id' => $owner->id,
                'status' => AuditStatus::Reading,
                'source' => $source,
                'file_path' => $storedFilePath,
                'written_total_rp' => null,
                'total_ack' => false,
            ]);

            $hasYellow = false;
            $calculatedTotalRp = 0;

            foreach ($result->rows as $row) {
                $standard = $row->standardCode !== null ? $standardsByCode->get($row->standardCode) : null;

                $labelResult = $this->labeler->label(
                    rawName: $row->rawName,
                    volume: $row->volume,
                    unit: $row->unit,
                    unitPriceRp: $row->unitPriceRp,
                    amountRp: $row->amountRp,
                    standardPriceRp: $standard?->price_rp,
                    standardUnit: $standard?->unit,
                    doubtFields: $row->doubtFields,
                    rowToleranceRp: $toleranceRp,
                );

                if ($labelResult->label === PriceLabel::NeedsCheck) {
                    $hasYellow = true;
                }

                $amountRp = $row->amountRp ?? 0;
                $calculatedTotalRp += $amountRp;

                AuditItem::create([
                    'audit_id' => $audit->id,
                    'position' => $row->position,
                    'raw_name' => $row->rawName,
                    'volume' => $row->volume ?? '0',
                    'unit' => $row->unit ?? '',
                    'unit_price_rp' => $row->unitPriceRp ?? 0,
                    'amount_rp' => $amountRp,
                    'standard_id' => $standard?->id,
                    'doubt' => $labelResult->reason !== null ? [
                        'reason' => $labelResult->reason,
                        'fields' => $labelResult->doubtFields,
                    ] : null,
                    'label' => $labelResult->label,
                    'over_pct' => $labelResult->overPct,
                    'diff_rp' => $labelResult->diffRp,
                    'corrected' => false,
                ]);
            }

            $writtenTotalRp = $result->writtenTotalRp ?? $calculatedTotalRp;
            $totalAck = $writtenTotalRp === $calculatedTotalRp;

            $audit->update([
                'status' => $hasYellow ? AuditStatus::NeedsReview : AuditStatus::Ready,
                'written_total_rp' => $writtenTotalRp,
                'total_ack' => $totalAck,
                'read_ms' => $result->readMs,
                'error_code' => null,
            ]);

            return $audit;
        });
    }
}
