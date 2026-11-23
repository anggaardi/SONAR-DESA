<?php

declare(strict_types=1);

namespace App\Modules\Project\Actions;

use App\Modules\Audit\Domain\TotalChecker;
use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Project\Enums\MeasurementOrigin;
use App\Modules\Project\Enums\ProjectType;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PublishProject
{
    /**
     * @param array{
     *     name: string,
     *     type: ProjectType|string,
     *     fund_source: string,
     *     year: int,
     *     lat?: float|numeric-string|string|null,
     *     lng?: float|numeric-string|string|null,
     *     total_ack?: bool|null,
     *     measurements?: list<array{
     *         name: string,
     *         value: numeric-string|float|int|string,
     *         unit: string,
     *         how_to?: string|null,
     *         origin?: MeasurementOrigin|string|null
     *     }>
     * } $data
     */
    public function execute(Audit $audit, array $data): Project
    {
        // 1. Audit tenant ownership check
        $villageId = app(CurrentVillage::class)->id();
        if ($audit->village_id !== $villageId) {
            throw ValidationException::withMessages([
                'audit' => 'Dokumen audit tidak ditemukan di desa aktif.',
            ]);
        }

        // 2. Berkas asli wajib (D9)
        if ($audit->file_path === null || trim((string) $audit->file_path) === '') {
            throw ValidationException::withMessages([
                'file' => 'Berkas asli RAB wajib diunggah sebelum proyek dapat diterbitkan (D9).',
            ]);
        }

        // 3. Immutabilitas pasca-terbit (D10): jika proyek sudah terbit, tolak
        $existingProject = $audit->project;
        if ($existingProject !== null && $existingProject->isPublished()) {
            throw ValidationException::withMessages([
                'project' => 'Proyek yang sudah diterbitkan tidak dapat diterbitkan ulang (D10).',
            ]);
        }

        // 4. Minimal harus ada 1 baris item (D8, D9)
        $itemsCount = $audit->items()->count();
        if ($itemsCount === 0) {
            throw ValidationException::withMessages([
                'items' => 'Audit tidak memiliki rincian item anggaran (D8, D9).',
            ]);
        }

        // 5. Gatekeeper 0 baris kuning (D1)
        // Baris kuning: label == needs_check dan corrected == false
        $yellowCount = $audit->items()
            ->where('label', PriceLabel::NeedsCheck)
            ->where('corrected', false)
            ->count();

        if ($yellowCount > 0) {
            throw ValidationException::withMessages([
                'gatekeeper' => "Terdapat {$yellowCount} baris kuning (NeedsCheck) yang belum diselesaikan (D1).",
            ]);
        }

        // 5b. Pengecekan konsistensi total tertulis vs baris belanja (F3)
        if ($audit->written_total_rp !== null) {
            $totalChecker = app(TotalChecker::class);
            $totalCheck = $totalChecker->check(
                $audit->items,
                $audit->written_total_rp,
                (int) config('sonar.price.row_tolerance_rp', 1000)
            );

            $isTotalAck = (bool) $audit->total_ack || ! empty($data['total_ack']);
            if (! $totalCheck->isMatch && ! $isTotalAck) {
                throw ValidationException::withMessages([
                    'total_ack' => 'Terdapat ketidakcocokan antara total tertulis RAB dengan penjumlahan baris belanja. Konfirmasi ketidakcocokan total wajib dicentang sebelum menerbitkan (F3).',
                ]);
            }
        }

        // 6. Titik koordinat GPS wajib ada (D3)
        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;
        if ($lat === null || $lat === '' || $lng === null || $lng === '') {
            throw ValidationException::withMessages([
                'gps' => 'Titik koordinat GPS wajib diisi untuk mengaktifkan geofence warga (D3).',
            ]);
        }

        $latNum = (float) $lat;
        $lngNum = (float) $lng;
        if ($latNum < -90.0 || $latNum > 90.0 || $lngNum < -180.0 || $lngNum > 180.0) {
            throw ValidationException::withMessages([
                'gps' => 'Koordinat GPS berada di luar rentang valid bumi.',
            ]);
        }

        // 7. Template spek fisik wajib lengkap (D2)
        $type = $data['type'] instanceof ProjectType ? $data['type'] : ProjectType::from((string) $data['type']);
        /** @var list<array{name: string, unit: string, default_value: string, how_to: string}> $requiredTemplates */
        $requiredTemplates = config("sonar.measurement_templates.{$type->value}", []);

        $providedMeasurements = $data['measurements'] ?? [];
        $providedMap = [];
        $customCount = 0;

        foreach ($providedMeasurements as $m) {
            $name = trim((string) $m['name']);
            $val = (float) $m['value'];
            $origin = $m['origin'] ?? MeasurementOrigin::Template;
            if (is_string($origin)) {
                $origin = MeasurementOrigin::tryFrom($origin) ?? MeasurementOrigin::Template;
            }

            if ($origin === MeasurementOrigin::Desa) {
                $customCount++;
            }

            if ($val <= 0.0) {
                throw ValidationException::withMessages([
                    'measurements' => "Nilai target untuk ukuran '{$name}' harus lebih besar dari 0.",
                ]);
            }

            $providedMap[mb_strtolower($name)] = $m;
        }

        // Pastikan semua ukuran template ada
        foreach ($requiredTemplates as $tpl) {
            $tplKey = mb_strtolower(trim($tpl['name']));
            if (! isset($providedMap[$tplKey])) {
                throw ValidationException::withMessages([
                    'measurements' => "Ukuran kunci template '{$tpl['name']}' wajib ada dan tidak boleh dihapus (D2).",
                ]);
            }
        }

        if ($customCount > 5) {
            throw ValidationException::withMessages([
                'measurements' => 'Maksimal ukuran kustom tambahan adalah 5 parameter (D2).',
            ]);
        }

        // 8. Eksekusi penerbitan dalam DB::transaction (D8: Satu Pintu Terbit)
        return DB::transaction(function () use ($audit, $data, $type, $lat, $lng, $providedMeasurements): Project {
            $now = Carbon::now();

            $baseSlug = Str::slug($data['name']);
            if ($baseSlug === '') {
                $baseSlug = 'proyek-desa';
            }

            $slug = $baseSlug;
            $counter = 1;
            while (Project::withoutGlobalScope('village')->where('slug', $slug)->where('audit_id', '!=', $audit->id)->exists()) {
                $slug = $baseSlug.'-'.Str::lower(Str::random(5));
                $counter++;
                if ($counter > 10) {
                    $slug = $baseSlug.'-'.time();
                    break;
                }
            }

            $project = Project::updateOrCreate(
                ['audit_id' => $audit->id],
                [
                    'village_id' => $audit->village_id,
                    'slug' => $slug,
                    'name' => $data['name'],
                    'type' => $type,
                    'fund_source' => $data['fund_source'],
                    'year' => (int) $data['year'],
                    'lat' => (string) $lat,
                    'lng' => (string) $lng,
                    'progress_pct' => 0,
                    'public_status' => PublicStatus::None,
                    'published_at' => $now,
                ]
            );

            // Sync measurements
            $project->measurements()->delete();
            foreach ($providedMeasurements as $m) {
                $origin = $m['origin'] ?? MeasurementOrigin::Template;
                if (is_string($origin)) {
                    $origin = MeasurementOrigin::tryFrom($origin) ?? MeasurementOrigin::Template;
                }

                ProjectMeasurement::create([
                    'project_id' => $project->id,
                    'name' => $m['name'],
                    'value' => (string) $m['value'],
                    'unit' => $m['unit'],
                    'how_to' => $m['how_to'] ?? '',
                    'origin' => $origin,
                ]);
            }

            // Resolve confirmed yellow rows before publishing so citizen page has pure labels
            foreach ($audit->items as $item) {
                if ($item->label === PriceLabel::NeedsCheck) {
                    $standard = $item->standard;
                    if ($standard && $standard->price_rp > 0) {
                        if ($item->unit_price_rp > $standard->price_rp) {
                            $diff = $item->unit_price_rp - $standard->price_rp;
                            $pct = (int) ceil(($diff * 100) / $standard->price_rp);
                            $item->update([
                                'label' => PriceLabel::AboveStandard,
                                'over_pct' => $pct,
                                'diff_rp' => (int) bcmul((string) (float) $item->volume, (string) $diff, 0),
                                'corrected' => true,
                            ]);
                        } else {
                            $item->update([
                                'label' => PriceLabel::WithinStandard,
                                'over_pct' => null,
                                'diff_rp' => 0,
                                'corrected' => true,
                            ]);
                        }
                    } else {
                        $item->update([
                            'label' => PriceLabel::NoReference,
                            'over_pct' => null,
                            'diff_rp' => null,
                            'corrected' => true,
                        ]);
                    }
                }
            }

            $isTotalAck = (bool) $audit->total_ack || ! empty($data['total_ack']);
            $audit->update([
                'status' => AuditStatus::Published,
                'total_ack' => $audit->written_total_rp === null ? true : $isTotalAck,
                'written_total_rp' => $audit->written_total_rp ?? (int) $audit->items()->sum('amount_rp'),
            ]);

            return $project;
        });
    }
}
