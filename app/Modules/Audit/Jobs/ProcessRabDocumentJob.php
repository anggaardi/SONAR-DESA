<?php

declare(strict_types=1);

namespace App\Modules\Audit\Jobs;

use App\Modules\Audit\Domain\PriceLabeler;
use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Models\Audit;
use App\Modules\Audit\Models\AuditItem;
use App\Modules\Audit\Reading\RabReader;
use App\Modules\Audit\Reading\StandardRef;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\WithVillage;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProcessRabDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(
        public int $auditId,
        public ?int $villageId = null,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        if ($this->villageId !== null) {
            return [new WithVillage($this->villageId)];
        }

        $audit = Audit::withoutGlobalScopes()->find($this->auditId);

        return $audit ? [new WithVillage($audit->village_id)] : [];
    }

    public function handle(RabReader $reader, PriceLabeler $labeler): void
    {
        $audit = Audit::withoutGlobalScopes()->find($this->auditId);

        if ($audit === null || $audit->status !== AuditStatus::Reading) {
            return;
        }

        $village = app(CurrentVillage::class);
        $wasSet = $village->has();
        if (! $wasSet) {
            $village->set($audit->village_id);
        }

        try {
            $filePath = (string) $audit->file_path;
            if ($filePath === '') {
                $audit->update([
                    'status' => AuditStatus::ReadFailed,
                    'error_code' => 'MISSING_FILE',
                ]);

                return;
            }

            $realPath = file_exists($filePath)
                ? $filePath
                : (Storage::disk('local')->exists($filePath) ? Storage::disk('local')->path($filePath) : null);

            if ($realPath === null || ! file_exists($realPath)) {
                $audit->update([
                    'status' => AuditStatus::ReadFailed,
                    'error_code' => 'FILE_NOT_FOUND',
                ]);

                return;
            }

            // Sediakan katalog standar harga resmi tanpa harga ke reader AI
            $standards = PriceStandard::query()
                ->select(['id', 'code', 'name', 'unit', 'price_rp'])
                ->get();

            $standardRefs = $standards->map(static fn (PriceStandard $s): StandardRef => new StandardRef(
                code: $s->code,
                name: $s->name,
                unit: $s->unit,
            ))->all();

            $standardsByCode = $standards->keyBy('code');

            $result = $reader->read($realPath, $standardRefs);

            if (! $result->isSuccess()) {
                $audit->update([
                    'status' => AuditStatus::ReadFailed,
                    'error_code' => $result->errorCode ?? 'EXTRACTION_FAILED',
                    'read_ms' => $result->readMs,
                ]);

                return;
            }

            DB::transaction(function () use ($audit, $result, $standardsByCode, $labeler): void {
                $audit->items()->delete();

                $hasYellow = false;
                $calculatedTotalRp = 0;

                foreach ($result->rows as $row) {
                    $standard = $row->standardCode !== null ? $standardsByCode->get($row->standardCode) : null;

                    $labelResult = $labeler->label(
                        rawName: $row->rawName,
                        volume: $row->volume,
                        unit: $row->unit,
                        unitPriceRp: $row->unitPriceRp,
                        amountRp: $row->amountRp,
                        standardPriceRp: $standard?->price_rp,
                        standardUnit: $standard?->unit,
                        doubtFields: $row->doubtFields,
                        rowToleranceRp: (int) config('sonar.price.row_tolerance_rp', 1000),
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
                        'doubt' => $labelResult->reason ? [
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
                $totalAck = ($writtenTotalRp === $calculatedTotalRp);
                $status = $hasYellow ? AuditStatus::NeedsReview : AuditStatus::Ready;

                $audit->update([
                    'status' => $status,
                    'written_total_rp' => $writtenTotalRp,
                    'total_ack' => $totalAck,
                    'read_ms' => $result->readMs,
                    'error_code' => null,
                ]);
            });
        } catch (Throwable $e) {
            Log::error("Gagal memproses dokumen RAB Audit #{$audit->id}: {$e->getMessage()}", [
                'exception' => $e,
                'audit_id' => $audit->id,
            ]);

            $audit->update([
                'status' => AuditStatus::ReadFailed,
                'error_code' => 'EXCEPTION_'.class_basename($e),
            ]);
        } finally {
            if (! $wasSet) {
                $village->clear();
            }
        }
    }
}
