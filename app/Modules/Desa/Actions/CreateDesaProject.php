<?php

declare(strict_types=1);

namespace App\Modules\Desa\Actions;

use App\Modules\Audit\Domain\PriceLabeler;
use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Models\Audit;
use App\Modules\Audit\Models\AuditItem;
use App\Modules\Project\Actions\PublishProject;
use App\Modules\Project\Enums\MeasurementOrigin;
use App\Modules\Project\Enums\ProjectType;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class CreateDesaProject
{
    public function __construct(
        private readonly PriceLabeler $priceLabeler,
        private readonly PublishProject $publishProject,
    ) {}

    /**
     * @param array{
     *     village_id: int,
     *     owner_id: int,
     *     name: string,
     *     type: ProjectType,
     *     fund_source: string,
     *     year: int,
     *     amount_rp?: int|null,
     *     lat?: float|numeric-string|string|null,
     *     lng?: float|numeric-string|string|null,
     *     is_published?: bool,
     *     file?: UploadedFile|null,
     *     file_path?: string|null,
     *     items: list<array{
     *         raw_name: string,
     *         volume: numeric-string|float|int|string,
     *         unit: string,
     *         unit_price_rp: int,
     *         standard_id?: int|null
     *     }>,
     *     measurements: list<array{
     *         name: string,
     *         value: numeric-string|float|int|string,
     *         unit: string,
     *         how_to?: string|null,
     *         origin?: MeasurementOrigin|string|null
     *     }>
     * } $data
     */
    public function execute(array $data): Project
    {
        $filePath = $data['file_path'] ?? null;
        $storedNewFile = false;

        if ($filePath === null && ! empty($data['file'])) {
            $filePath = $data['file']->store('audits', 'local');
            $storedNewFile = true;
        }

        try {
            return DB::transaction(function () use ($data, $filePath): Project {
                $isPublished = (bool) ($data['is_published'] ?? false);

                // Hitung total nilai items
                $totalAmountRp = 0;
                foreach ($data['items'] as $item) {
                    $volume = (float) $item['volume'];
                    $price = (int) $item['unit_price_rp'];
                    $totalAmountRp += (int) bcmul((string) $volume, (string) $price, 0);
                }

                $writtenTotalRp = ! empty($data['amount_rp']) ? (int) $data['amount_rp'] : $totalAmountRp;

                // 2. Buat Audit dengan status Draft (wajib berkas fisik)
                $audit = Audit::create([
                    'village_id' => $data['village_id'],
                    'owner_id' => $data['owner_id'],
                    'status' => AuditStatus::Draft,
                    'source' => 'manual',
                    'file_path' => $filePath,
                    'written_total_rp' => $writtenTotalRp,
                    'total_ack' => ($writtenTotalRp === $totalAmountRp),
                ]);

                // 3. Masukkan baris item RAB dengan label deterministik dari PriceLabeler (Opsi A)
                $standardIds = array_filter(array_column($data['items'], 'standard_id'));
                $standards = ! empty($standardIds)
                    ? PriceStandard::whereIn('id', $standardIds)->get()->keyBy('id')
                    : collect();

                $hasYellow = false;
                $position = 1;

                foreach ($data['items'] as $itemData) {
                    $standardId = ! empty($itemData['standard_id'])
                        ? (int) $itemData['standard_id']
                        : null;
                    $standard = $standardId !== null ? $standards->get($standardId) : null;

                    $volumeStr = (string) $itemData['volume'];
                    $unitPrice = (int) $itemData['unit_price_rp'];
                    $amountRp = (int) bcmul($volumeStr, (string) $unitPrice, 0);

                    $labelResult = $this->priceLabeler->label(
                        rawName: $itemData['raw_name'],
                        volume: $volumeStr,
                        unit: $itemData['unit'],
                        unitPriceRp: $unitPrice,
                        amountRp: $amountRp,
                        standardPriceRp: $standard?->price_rp,
                        standardUnit: $standard?->unit,
                        doubtFields: [],
                    );

                    if ($labelResult->label === PriceLabel::NeedsCheck) {
                        $hasYellow = true;
                    }

                    AuditItem::create([
                        'audit_id' => $audit->id,
                        'position' => $position++,
                        'raw_name' => $itemData['raw_name'],
                        'volume' => $volumeStr,
                        'unit' => $itemData['unit'],
                        'unit_price_rp' => $unitPrice,
                        'amount_rp' => $amountRp,
                        'standard_id' => $standard?->id,
                        'doubt' => $labelResult->reason ? ['reason' => $labelResult->reason] : null,
                        'label' => $labelResult->label,
                        'over_pct' => $labelResult->overPct,
                        'diff_rp' => $labelResult->diffRp,
                        'corrected' => false,
                    ]);
                }

                // Tentukan status audit
                $auditStatus = $hasYellow ? AuditStatus::NeedsReview : AuditStatus::Ready;
                $audit->update(['status' => $auditStatus]);

                // 4. Jika user ingin terbitkan langsung dan tidak ada baris kuning (D8: Satu Pintu Terbit)
                if ($isPublished && ! $hasYellow) {
                    return $this->publishProject->execute($audit, [
                        'name' => $data['name'],
                        'type' => $data['type'],
                        'fund_source' => $data['fund_source'],
                        'year' => (int) $data['year'],
                        'lat' => $data['lat'] ?? '',
                        'lng' => $data['lng'] ?? '',
                        'measurements' => $data['measurements'],
                    ]);
                }

                // 5. Simpan sebagai Draf (published_at = null)
                $baseSlug = Str::slug($data['name']);
                if ($baseSlug === '') {
                    $baseSlug = 'proyek-desa';
                }

                $slug = $baseSlug;
                $counter = 1;
                while (Project::withoutGlobalScope('village')->where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-'.Str::lower(Str::random(5));
                    $counter++;
                    if ($counter > 10) {
                        $slug = $baseSlug.'-'.time();
                        break;
                    }
                }

                $project = Project::create([
                    'village_id' => $data['village_id'],
                    'audit_id' => $audit->id,
                    'slug' => $slug,
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'fund_source' => $data['fund_source'],
                    'year' => (int) $data['year'],
                    'lat' => ! empty($data['lat']) ? (string) $data['lat'] : null,
                    'lng' => ! empty($data['lng']) ? (string) $data['lng'] : null,
                    'progress_pct' => 0,
                    'public_status' => PublicStatus::None,
                    'published_at' => null,
                ]);

                foreach ($data['measurements'] as $measurementData) {
                    $origin = $measurementData['origin'] ?? MeasurementOrigin::Template;
                    if (is_string($origin)) {
                        $origin = MeasurementOrigin::tryFrom($origin) ?? MeasurementOrigin::Template;
                    }

                    ProjectMeasurement::create([
                        'project_id' => $project->id,
                        'name' => $measurementData['name'],
                        'value' => (string) $measurementData['value'],
                        'unit' => $measurementData['unit'],
                        'how_to' => $measurementData['how_to'] ?? '',
                        'origin' => $origin,
                    ]);
                }

                return $project;
            });
        } catch (\Throwable $e) {
            if ($storedNewFile && is_string($filePath) && Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }

            throw $e;
        }
    }
}
