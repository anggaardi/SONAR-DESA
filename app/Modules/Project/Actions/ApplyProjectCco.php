<?php

declare(strict_types=1);

namespace App\Modules\Project\Actions;

use App\Modules\Audit\Domain\PriceLabeler;
use App\Modules\Audit\Models\AuditItem;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handles technical Contract Change Orders (CCO / addendum perubahan fisik)
 * while strictly enforcing invariants D12, D13, D14 (PRD Bagian 6 & 8).
 */
final class ApplyProjectCco
{
    public function __construct(
        private readonly PriceLabeler $priceLabeler,
        private readonly RecordProjectChange $recordProjectChange,
    ) {}

    /**
     * @param array{
     *     volume?: numeric-string|float|int|string,
     *     unit?: string,
     *     unit_price_rp?: int,
     *     standard_id?: int|null
     * } $data
     */
    public function updateItem(Project $project, AuditItem $item, array $data, string $reason): AuditItem
    {
        $this->ensureNotFrozen($project);
        abort_unless($item->audit_id === $project->audit_id, 404, 'Baris belanja tidak terkait dengan proyek ini.');

        return DB::transaction(function () use ($project, $item, $data, $reason): AuditItem {
            $oldVolume = (string) (float) $item->volume;
            $oldPrice = (int) $item->unit_price_rp;
            $oldUnit = (string) $item->unit;
            $oldStandardId = $item->standard_id;
            $oldStandard = $item->standard;

            $newVolume = isset($data['volume']) ? (string) (float) $data['volume'] : $oldVolume;
            $newPrice = isset($data['unit_price_rp']) ? (int) $data['unit_price_rp'] : $oldPrice;
            $newUnit = isset($data['unit']) ? (string) $data['unit'] : $oldUnit;
            $standardId = array_key_exists('standard_id', $data) ? $data['standard_id'] : $oldStandardId;

            $standard = $standardId !== null ? PriceStandard::find($standardId) : null;
            $newAmountRp = (int) bcmul($newVolume, (string) $newPrice, 0);

            // Record changes in project_changes
            if ($oldVolume !== $newVolume || $oldUnit !== $newUnit) {
                $this->recordProjectChange->execute(
                    project: $project,
                    field: 'Volume Item',
                    itemLabel: $item->raw_name,
                    oldValue: "{$oldVolume} {$oldUnit}",
                    newValue: "{$newVolume} {$newUnit}",
                    reason: $reason,
                );
            }

            if ($oldPrice !== $newPrice) {
                $this->recordProjectChange->execute(
                    project: $project,
                    field: 'Harga Satuan',
                    itemLabel: $item->raw_name,
                    oldValue: 'Rp'.number_format($oldPrice, 0, ',', '.'),
                    newValue: 'Rp'.number_format($newPrice, 0, ',', '.'),
                    reason: $reason,
                );
            }

            // D13: Perubahan acuan standar dicatat ke riwayat
            if ($oldStandardId !== $standard?->id) {
                $this->recordProjectChange->execute(
                    project: $project,
                    field: 'Standar Acuan Harga',
                    itemLabel: $item->raw_name,
                    oldValue: $oldStandard ? "{$oldStandard->name} ({$oldStandard->code})" : 'Tanpa Acuan',
                    newValue: $standard ? "{$standard->name} ({$standard->code})" : 'Tanpa Acuan',
                    reason: $reason,
                );
            }

            // Deterministic re-labeling via PriceLabeler (D14)
            $labelResult = $this->priceLabeler->label(
                rawName: $item->raw_name,
                volume: $newVolume,
                unit: $newUnit,
                unitPriceRp: $newPrice,
                amountRp: $newAmountRp,
                standardPriceRp: $standard?->price_rp,
                standardUnit: $standard?->unit,
                doubtFields: [],
            );

            $item->update([
                'volume' => $newVolume,
                'unit' => $newUnit,
                'unit_price_rp' => $newPrice,
                'amount_rp' => $newAmountRp,
                'standard_id' => $standard?->id,
                'standard_price_rp' => $standard?->price_rp,
                'label' => $labelResult->label,
                'over_pct' => $labelResult->overPct,
                'diff_rp' => $labelResult->diffRp,
                'doubt' => $labelResult->reason ? ['reason' => $labelResult->reason] : null,
                'corrected' => true,
            ]);

            return $item;
        });
    }

    public function updateMeasurement(
        Project $project,
        ProjectMeasurement $measurement,
        string $newValue,
        ?string $howTo,
        string $reason
    ): ProjectMeasurement {
        $this->ensureNotFrozen($project);
        abort_unless($measurement->project_id === $project->id, 404, 'Parameter ukuran tidak terkait dengan proyek ini.');

        return DB::transaction(function () use ($project, $measurement, $newValue, $howTo, $reason): ProjectMeasurement {
            $oldValue = (string) (float) $measurement->value;
            $targetValue = (string) (float) $newValue;

            if ($oldValue !== $targetValue) {
                $this->recordProjectChange->execute(
                    project: $project,
                    field: 'Target Ukuran',
                    itemLabel: $measurement->name,
                    oldValue: "{$oldValue} {$measurement->unit}",
                    newValue: "{$targetValue} {$measurement->unit}",
                    reason: $reason,
                );
            }

            if ($howTo !== null && trim($howTo) !== trim((string) $measurement->how_to)) {
                $this->recordProjectChange->execute(
                    project: $project,
                    field: 'Petunjuk Pengukuran',
                    itemLabel: $measurement->name,
                    oldValue: (string) ($measurement->how_to ?: '-'),
                    newValue: $howTo,
                    reason: $reason,
                );
            }

            $measurement->update([
                'value' => $targetValue,
                'how_to' => $howTo ?? $measurement->how_to,
            ]);

            return $measurement;
        });
    }

    public function updateGps(Project $project, string $newLat, string $newLng, string $reason): Project
    {
        $this->ensureNotFrozen($project);

        return DB::transaction(function () use ($project, $newLat, $newLng, $reason): Project {
            $oldLat = (string) $project->lat;
            $oldLng = (string) $project->lng;

            $isLatChanged = abs((float) $oldLat - (float) $newLat) > 0.0000001;
            $isLngChanged = abs((float) $oldLng - (float) $newLng) > 0.0000001;

            if ($isLatChanged || $isLngChanged) {
                $this->recordProjectChange->execute(
                    project: $project,
                    field: 'Titik Lokasi GPS',
                    itemLabel: 'Koordinat Geofence',
                    oldValue: "Lat: {$oldLat}, Lng: {$oldLng}",
                    newValue: "Lat: {$newLat}, Lng: {$newLng}",
                    reason: $reason,
                );
            }

            $project->update([
                'lat' => $newLat,
                'lng' => $newLng,
            ]);

            return $project;
        });
    }

    private function ensureNotFrozen(Project $project): void
    {
        if (! $project->isPublished()) {
            throw ValidationException::withMessages([
                'cco' => 'Perubahan CCO hanya berlaku untuk proyek yang sudah diterbitkan.',
            ]);
        }

        if ($project->public_status === PublicStatus::InReview) {
            throw ValidationException::withMessages([
                'cco' => 'Perubahan CCO (item belanja, ukuran teknis, dan titik GPS) dibekukan sementara selama proyek berstatus Dalam Tinjauan BPD (D12).',
            ]);
        }
    }
}
