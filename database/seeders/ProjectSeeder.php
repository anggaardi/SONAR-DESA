<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Models\Audit;
use App\Modules\Audit\Models\AuditItem;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Enums\MeasurementOrigin;
use App\Modules\Project\Enums\ProjectType;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\ProgressPhoto;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Report\Enums\MeasurementAnswer;
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Report\Models\Report;
use App\Modules\Report\Models\ReportAnswer;
use App\Modules\Report\Models\ReportPhoto;
use App\Modules\Review\Enums\ReviewOutcome;
use App\Modules\Review\Models\ReviewCycle;
use App\Modules\Review\Models\ReviewNote;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds sample projects according to PRD Fitur Inti 7.3 (Data Awal Desa Contoh).
 * Idempotent: can be executed multiple times without duplicating rows.
 */
final class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $village = Village::query()->where('slug', config('sonar.demo.template_slug'))->first();

        if (! $village) {
            return;
        }

        $owner = User::query()->where('village_id', $village->id)->where('role', Role::Desa)->first();

        if (! $owner) {
            return;
        }

        $this->copySampleFiles();

        app(CurrentVillage::class)->set($village->id);

        try {
            $standards = $this->seedStandards($village->regency);
            $this->seedSampleProjects($village, $owner, $standards);
        } finally {
            app(CurrentVillage::class)->clear();
        }
    }

    /**
     * Sample RAB PDFs and progress photos live in the repo (database/seeders/assets, same paths as on the
     * local disk) so production gets them too. Demo clones point at these files (VillageCloner).
     */
    private function copySampleFiles(): void
    {
        $disk = Storage::disk('local');
        $assets = database_path('seeders/assets');

        foreach (File::allFiles($assets) as $file) {
            $path = str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());

            if (! $disk->exists($path)) {
                $disk->put($path, $file->getContents());
            }
        }
    }

    /**
     * Fixed warga accounts and reports: no Faker (dev-only) and the same rows on every run.
     * Accounts have no password and no village, like Google-login warga.
     *
     * @param  array<string, float|string>  $mismatchMeasurements  measurement name => measured value for Mismatch answers
     */
    private function seedCitizenReport(
        Project $project,
        int $citizenIndex,
        ReportKind $kind,
        ?string $note = null,
        int $daysAgo = 2,
        int $distanceM = 15,
        ?ReviewCycle $cycle = null,
        array $mismatchMeasurements = [],
        string $samplePhotoPath = 'progress/sample-progress-35.webp',
    ): void {
        $sampleCitizens = [
            1 => ['name' => 'Ni Made Ayu', 'google_id' => 'google-warga-01'],
            2 => ['name' => 'I Wayan Sudarma', 'google_id' => 'google-warga-02'],
            3 => ['name' => 'I Ketut Wirawan', 'google_id' => 'google-warga-03'],
            4 => ['name' => 'Ni Luh Sari', 'google_id' => 'google-warga-04'],
            5 => ['name' => 'I Nyoman Gede', 'google_id' => 'google-warga-05'],
            6 => ['name' => 'Ni Putu Lestari', 'google_id' => 'google-warga-06'],
            7 => ['name' => 'I Made Dharma', 'google_id' => 'google-warga-07'],
            8 => ['name' => 'Ni Kadek Pratiwi', 'google_id' => 'google-warga-08'],
        ];
        $citizenProfile = $sampleCitizens[$citizenIndex] ?? [
            'name' => sprintf('Warga Contoh %02d', $citizenIndex),
            'google_id' => sprintf('google-warga-%02d', $citizenIndex),
        ];

        $citizen = User::query()->updateOrCreate(
            ['email' => sprintf('warga-%02d@contoh.sonar-desa.test', $citizenIndex)],
            [
                'name' => $citizenProfile['name'],
                'google_id' => $citizenProfile['google_id'],
                'role' => Role::Warga,
                'village_id' => null,
                'password' => null,
            ],
        );

        $reportedAt = Carbon::now()->subDays($daysAgo);

        $report = Report::query()->updateOrCreate(
            ['project_id' => $project->id, 'user_id' => $citizen->id],
            [
                'cycle_id' => $cycle?->id,
                'kind' => $kind,
                'lat' => $project->lat,
                'lng' => $project->lng,
                'accuracy_m' => 12,
                'distance_m' => $distanceM,
                'note' => $note,
                'invalid_reason' => null,
                'opened_at' => $reportedAt,
            ],
        );
        $report->forceFill(['created_at' => $reportedAt])->save();

        $measurements = ProjectMeasurement::query()
            ->where('project_id', $project->id)
            ->orderBy('id')
            ->get();

        foreach ($measurements as $index => $measurement) {
            $hasSpecificMismatch = array_key_exists($measurement->name, $mismatchMeasurements);
            $isFallbackMismatch = $kind === ReportKind::Mismatch
                && $mismatchMeasurements === []
                && $index === $measurements->count() - 1;

            if ($hasSpecificMismatch || $isFallbackMismatch) {
                $measuredVal = $hasSpecificMismatch
                    ? (string) $mismatchMeasurements[$measurement->name]
                    : (string) round(((float) $measurement->value) * 0.75, 3);

                ReportAnswer::query()->updateOrCreate(
                    ['report_id' => $report->id, 'measurement_id' => $measurement->id],
                    [
                        'answer' => MeasurementAnswer::Mismatch,
                        'measured_value' => $measuredVal,
                        'measured_unit' => $measurement->unit,
                        'target_value' => $measurement->value,
                        'target_unit' => $measurement->unit,
                    ],
                );
            } else {
                ReportAnswer::query()->updateOrCreate(
                    ['report_id' => $report->id, 'measurement_id' => $measurement->id],
                    [
                        'answer' => MeasurementAnswer::Match,
                        'measured_value' => null,
                        'measured_unit' => null,
                        'target_value' => $measurement->value,
                        'target_unit' => $measurement->unit,
                    ],
                );
            }
        }

        ReportPhoto::query()->updateOrCreate(
            ['report_id' => $report->id, 'position' => 1],
            ['path' => $samplePhotoPath],
        );
    }

    /**
     * @return array<string, PriceStandard>
     */
    private function seedStandards(string $regency): array
    {
        $standards = [
            ['code' => 'MAT-SMN-01', 'name' => 'Semen Portland Komposit 50kg', 'unit' => 'sak', 'price_rp' => 72000],
            ['code' => 'MAT-PSR-01', 'name' => 'Pasir Pasang / Pasir Cor', 'unit' => 'm3', 'price_rp' => 240000],
            ['code' => 'MAT-SPL-01', 'name' => 'Batu Split / Kerikil 2/3', 'unit' => 'm3', 'price_rp' => 290000],
            ['code' => 'MAT-BSI-01', 'name' => 'Besi Beton Polos dia 10mm', 'unit' => 'btg', 'price_rp' => 78000],
            ['code' => 'MAT-BTK-01', 'name' => 'Batu Kali Bulat', 'unit' => 'm3', 'price_rp' => 337000],
            ['code' => 'MAT-BPC-01', 'name' => 'Batu Pecah 2/3', 'unit' => 'm3', 'price_rp' => 350000],
            ['code' => 'PJU-SLR-01', 'name' => 'Lampu PJU All-in-One Solar LED 60 Watt', 'unit' => 'unit', 'price_rp' => 2600000],
            ['code' => 'PJU-BAT-01', 'name' => 'Baterai Lithium LiFePO4 cadangan 12V', 'unit' => 'unit', 'price_rp' => 1450000],
        ];

        $mapped = [];
        foreach ($standards as $item) {
            $mapped[$item['code']] = PriceStandard::query()->updateOrCreate(
                [
                    'regency' => $regency,
                    'code' => $item['code'],
                    'year' => 2026,
                ],
                [
                    'name' => $item['name'],
                    'unit' => $item['unit'],
                    'price_rp' => $item['price_rp'],
                    'source_ref' => 'Pergub Bali No. 31/2025 (SSH TA 2026)',
                ],
            );
        }

        return $mapped;
    }

    /**
     * @param  array<string, PriceStandard>  $standards
     */
    private function seedSampleProjects(Village $village, User $owner, array $standards): void
    {
        $this->seedProject1($village, $owner, $standards);
        $this->seedProject2($village, $owner, $standards);
        $this->seedProject3($village, $owner, $standards);
        $this->seedProject4($village, $owner, $standards);
    }

    /**
     * Project 1: Rabat Beton Jl. Melati RT 02/03.
     * Matches sample-rab-melati.pdf and PRD 7.3:
     * - 3 items AboveStandard (Semen, Pasir pasang, Besi beton dia 10mm)
     * - 1 item WithinStandard (Batu pecah 2/3)
     * - 10 items NoReference
     * - Total: Rp 114.980.000, Dibulatkan Rp 115.000.000
     * - Measurements: 200 m × 3 m × 0,12 m
     * - Reports: 2 Mismatch valid (tebal 8 cm dan 9 cm) + 1 Match
     * - Status: PublicStatus::None (threshold of 3 mismatch reports not reached; demo judge triggers review)
     * - Progress: 60%
     *
     * @param  array<string, PriceStandard>  $standards
     */
    private function seedProject1(Village $village, User $owner, array $standards): void
    {
        $audit = Audit::query()->withoutGlobalScope('village')->updateOrCreate(
            [
                'village_id' => $village->id,
                'file_path' => 'audits/sample-rab-melati.pdf',
            ],
            [
                'owner_id' => $owner->id,
                'status' => AuditStatus::Published,
                'source' => 'upload',
                'read_ms' => 1250,
                'written_total_rp' => 115000000,
                'total_ack' => true,
            ],
        );

        $items = [
            // I. PERSIAPAN & TANAH
            [
                'position' => 1,
                'raw_name' => 'Pembersihan dan perataan badan jalan',
                'volume' => 1.00,
                'unit' => 'ls',
                'unit_price_rp' => 7200000,
                'amount_rp' => 7200000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
            [
                'position' => 2,
                'raw_name' => 'Pengukuran dan pemasangan bowplank',
                'volume' => 50.00,
                'unit' => 'm',
                'unit_price_rp' => 3000,
                'amount_rp' => 150000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
            [
                'position' => 3,
                'raw_name' => 'Pemadatan tanah dasar',
                'volume' => 100.00,
                'unit' => 'm2',
                'unit_price_rp' => 2500,
                'amount_rp' => 250000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
            // II. BAHAN
            [
                'position' => 4,
                'raw_name' => 'Semen Portland 50kg',
                'volume' => 450.00,
                'unit' => 'sak',
                'unit_price_rp' => 85000,
                'amount_rp' => 38250000,
                'standard_code' => 'MAT-SMN-01',
                'label' => PriceLabel::AboveStandard,
                'over_pct' => 18,
                'diff_rp' => 13000,
                'explanation' => 'Medan tanjakan 18 derajat membutuhkan semen mutu lebih tinggi dan biaya angkut material lebih mahal.',
            ],
            [
                'position' => 5,
                'raw_name' => 'Pasir Pasang',
                'volume' => 38.00,
                'unit' => 'm3',
                'unit_price_rp' => 280000,
                'amount_rp' => 10640000,
                'standard_code' => 'MAT-PSR-01',
                'label' => PriceLabel::AboveStandard,
                'over_pct' => 17,
                'diff_rp' => 40000,
                'explanation' => 'Jarak angkut dari depo pasir melebihi radius standar distribusi desa.',
            ],
            [
                'position' => 6,
                'raw_name' => 'Batu Pecah / Split 2/3',
                'volume' => 56.00,
                'unit' => 'm3',
                'unit_price_rp' => 350000,
                'amount_rp' => 19600000,
                'standard_code' => 'MAT-BPC-01',
                'label' => PriceLabel::WithinStandard,
                'over_pct' => 0,
                'diff_rp' => 0,
                'explanation' => null,
            ],
            [
                'position' => 7,
                'raw_name' => 'Besi Beton Polos dia 10mm',
                'volume' => 40.00,
                'unit' => 'btg',
                'unit_price_rp' => 95000,
                'amount_rp' => 3800000,
                'standard_code' => 'MAT-BSI-01',
                'label' => PriceLabel::AboveStandard,
                'over_pct' => 22,
                'diff_rp' => 17000,
                'explanation' => 'Besi tulangan dowel sambungan ekspansi mutu SNI polos toleransi penuh.',
            ],
            [
                'position' => 8,
                'raw_name' => 'Plastik cor alas beton',
                'volume' => 12.00,
                'unit' => 'rol',
                'unit_price_rp' => 45000,
                'amount_rp' => 540000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
            [
                'position' => 9,
                'raw_name' => 'Papan bekisting kayu terentang',
                'volume' => 1.50,
                'unit' => 'm3',
                'unit_price_rp' => 3200000,
                'amount_rp' => 4800000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
            [
                'position' => 10,
                'raw_name' => 'Paku usuk / reng 5-7 cm',
                'volume' => 10.00,
                'unit' => 'kg',
                'unit_price_rp' => 25000,
                'amount_rp' => 250000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
            [
                'position' => 11,
                'raw_name' => 'Air kerja',
                'volume' => 15.00,
                'unit' => 'm3',
                'unit_price_rp' => 50000,
                'amount_rp' => 750000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
            // III. UPAH & ALAT
            [
                'position' => 12,
                'raw_name' => 'Tukang batu',
                'volume' => 45.00,
                'unit' => 'OH',
                'unit_price_rp' => 150000,
                'amount_rp' => 6750000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
            [
                'position' => 13,
                'raw_name' => 'Pekerja',
                'volume' => 150.00,
                'unit' => 'OH',
                'unit_price_rp' => 120000,
                'amount_rp' => 18000000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
            [
                'position' => 14,
                'raw_name' => 'Sewa molen beton 0.35 m3',
                'volume' => 10.00,
                'unit' => 'hari',
                'unit_price_rp' => 400000,
                'amount_rp' => 4000000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
        ];

        foreach ($items as $itemData) {
            $standard = isset($itemData['standard_code'], $standards[$itemData['standard_code']])
                ? $standards[$itemData['standard_code']]
                : null;

            AuditItem::query()->updateOrCreate(
                [
                    'audit_id' => $audit->id,
                    'position' => $itemData['position'],
                ],
                [
                    'raw_name' => $itemData['raw_name'],
                    'volume' => $itemData['volume'],
                    'unit' => $itemData['unit'],
                    'unit_price_rp' => $itemData['unit_price_rp'],
                    'amount_rp' => $itemData['amount_rp'],
                    'standard_id' => $standard?->id,
                    'label' => $itemData['label'],
                    'over_pct' => $itemData['over_pct'],
                    'diff_rp' => $itemData['diff_rp'],
                    'doubt' => $itemData['explanation'] ? ['neutral_explanation' => $itemData['explanation']] : null,
                ],
            );
        }

        $project = Project::query()->withoutGlobalScope('village')->updateOrCreate(
            [
                'village_id' => $village->id,
                'slug' => 'rabat-beton-jl-melati-rt-02-03',
            ],
            [
                'audit_id' => $audit->id,
                'name' => 'Rabat Beton Jl. Melati RT 02/03',
                'type' => ProjectType::RabatBeton,
                'fund_source' => 'Dana Desa 2026',
                'year' => 2026,
                'lat' => -8.5412340,
                'lng' => 115.3214560,
                'progress_pct' => 60,
                'public_status' => PublicStatus::None,
                'current_cycle_id' => null,
                'published_at' => Carbon::now()->subDays(14),
            ],
        );

        $measurements = [
            ['name' => 'Panjang', 'value' => 200.000, 'unit' => 'meter', 'how_to' => 'Ukur dari patok awal RT 02 sampai batas RT 03.'],
            ['name' => 'Lebar', 'value' => 3.000, 'unit' => 'meter', 'how_to' => 'Ukur lebar perkerasan badan jalan beton.'],
            ['name' => 'Tebal', 'value' => 12.000, 'unit' => 'cm', 'how_to' => 'Ukur ketebalan pada sisi pinggir dan tengah cor beton.'],
        ];

        foreach ($measurements as $m) {
            ProjectMeasurement::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'name' => $m['name'],
                ],
                [
                    'value' => $m['value'],
                    'unit' => $m['unit'],
                    'how_to' => $m['how_to'],
                    'origin' => MeasurementOrigin::Template,
                ],
            );
        }

        $photos = [
            ['path' => 'progress/sample-progress-0.webp', 'progress_pct' => 0, 'days_ago' => 14],
            ['path' => 'progress/sample-progress-35.webp', 'progress_pct' => 35, 'days_ago' => 8],
            ['path' => 'progress/sample-progress-65.webp', 'progress_pct' => 60, 'days_ago' => 2],
        ];

        foreach ($photos as $p) {
            ProgressPhoto::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'progress_pct' => $p['progress_pct'],
                ],
                [
                    'path' => $p['path'],
                    'taken_at' => Carbon::now()->subDays($p['days_ago']),
                ],
            );
        }

        // PRD 7.3: 2 laporan Selisih valid (tebal 8 cm dan 9 cm) + 1 Sesuai
        $this->seedCitizenReport(
            $project,
            citizenIndex: 1,
            kind: ReportKind::Mismatch,
            note: 'Tebal perkerasan cor di pinggir jalan hanya 8 cm, tidak mencapai spesifikasi 12 cm di RAB.',
            daysAgo: 5,
            distanceM: 18,
            mismatchMeasurements: ['Tebal' => '8'],
            samplePhotoPath: 'progress/sample-progress-35.webp',
        );

        $this->seedCitizenReport(
            $project,
            citizenIndex: 2,
            kind: ReportKind::Mismatch,
            note: 'Hasil pengukuran ketebalan beton di beberapa titik rata-rata 9 cm.',
            daysAgo: 3,
            distanceM: 24,
            mismatchMeasurements: ['Tebal' => '9'],
            samplePhotoPath: 'progress/sample-progress-65.webp',
        );

        $this->seedCitizenReport(
            $project,
            citizenIndex: 3,
            kind: ReportKind::Match,
            note: 'Panjang 200 m dan lebar badan jalan 3 m sudah sesuai rencana.',
            daysAgo: 1,
            distanceM: 14,
            samplePhotoPath: 'progress/sample-progress-65.webp',
        );
    }

    /**
     * Project 2: Rehabilitasi Saluran Irigasi Subak Uma Dalem.
     *
     * Data matches RAB-3-sintetis.pdf (database/seeders/assets/audits/sample-rab-subak-uma.pdf).
     * 15 items across 3 kelompok (Persiapan & Tanah, Bahan, Upah & Alat).
     * Written total Rp 64.495.000, dibulatkan Rp 64.500.000 di dokumen.
     *
     * PRD 7.3:
     * - 1 di atas standar (Semen Portland 50 kg)
     * - Embedded calculation discrepancy (F7 calc check): Sewa molen 3 × 350.000 = 1.050.000,
     *   tetapi tertulis 1.500.000 (selisih Rp 450.000)
     * - 1 laporan Selisih (di bawah ambang batas, tidak menampilkan status alert publik)
     *
     * @param  array<string, PriceStandard>  $standards
     */
    private function seedProject2(Village $village, User $owner, array $standards): void
    {
        $audit = Audit::query()->withoutGlobalScope('village')->updateOrCreate(
            [
                'village_id' => $village->id,
                'file_path' => 'audits/sample-rab-subak-uma.pdf',
            ],
            [
                'owner_id' => $owner->id,
                'status' => AuditStatus::Published,
                'source' => 'upload',
                'read_ms' => 1100,
                'written_total_rp' => 64500000,
                'total_ack' => true,
            ],
        );

        $items = [
            // I. PERSIAPAN & TANAH
            [
                'position' => 1,
                'raw_name' => 'Pembersihan lokasi',
                'volume' => 1.00,
                'unit' => 'ls',
                'unit_price_rp' => 500000,
                'amount_rp' => 500000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 2,
                'raw_name' => 'Galian tanah biasa s.d. 1 m',
                'volume' => 45.00,
                'unit' => 'm3',
                'unit_price_rp' => 95000,
                'amount_rp' => 4275000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 3,
                'raw_name' => 'Urugan tanah kembali',
                'volume' => 12.00,
                'unit' => 'm3',
                'unit_price_rp' => 110000,
                'amount_rp' => 1320000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            // II. BAHAN (1 AboveStandard per PRD 7.3)
            [
                'position' => 4,
                'raw_name' => 'Semen Portland 50 kg',
                'volume' => 210.00,
                'unit' => 'sak',
                'unit_price_rp' => 98000,
                'amount_rp' => 20580000,
                'standard_code' => 'MAT-SMN-01',
                'label' => PriceLabel::AboveStandard,
                'over_pct' => 36,
                'diff_rp' => 26000,
                'explanation' => 'Biaya langsir semen ke tengah petak sawah Subak Uma Dalem tanpa akses truk.',
            ],
            [
                'position' => 5,
                'raw_name' => 'Pasir pasang',
                'volume' => 14.00,
                'unit' => 'm3',
                'unit_price_rp' => 285000,
                'amount_rp' => 3990000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 6,
                'raw_name' => 'Batu kali',
                'volume' => 38.00,
                'unit' => 'm3',
                'unit_price_rp' => 380000,
                'amount_rp' => 14440000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 7,
                'raw_name' => 'Batu pecah 2/3',
                'volume' => 6.00,
                'unit' => 'm3',
                'unit_price_rp' => 420000,
                'amount_rp' => 2520000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 8,
                'raw_name' => 'Besi beton Ø 10 polos',
                'volume' => 24.00,
                'unit' => 'btg',
                'unit_price_rp' => 95000,
                'amount_rp' => 2280000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 9,
                'raw_name' => 'Kawat beton',
                'volume' => 3.00,
                'unit' => 'kg',
                'unit_price_rp' => 35000,
                'amount_rp' => 105000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 10,
                'raw_name' => 'Papan bekisting',
                'volume' => 0.80,
                'unit' => 'm3',
                'unit_price_rp' => 3200000,
                'amount_rp' => 2560000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 11,
                'raw_name' => 'Paku',
                'volume' => 5.00,
                'unit' => 'kg',
                'unit_price_rp' => 25000,
                'amount_rp' => 125000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 12,
                'raw_name' => 'Pasir beton',
                'volume' => 2.00,
                'unit' => 'truk',
                'unit_price_rp' => 1400000,
                'amount_rp' => 2800000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            // III. UPAH & ALAT
            [
                'position' => 13,
                'raw_name' => 'Tukang batu',
                'volume' => 18.00,
                'unit' => 'OH',
                'unit_price_rp' => 150000,
                'amount_rp' => 2700000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 14,
                'raw_name' => 'Pekerja',
                'volume' => 40.00,
                'unit' => 'OH',
                'unit_price_rp' => 120000,
                'amount_rp' => 4800000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 15,
                'raw_name' => 'Sewa molen',
                'volume' => 3.00,
                'unit' => 'hari',
                'unit_price_rp' => 350000,
                'amount_rp' => 1500000,
                'standard_code' => null,
                'label' => PriceLabel::NeedsCheck,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => 'Jumlah Rp 1.500.000 tidak cocok dengan perhitungan volume 3 × harga Rp 350.000 = Rp 1.050.000. Terdapat selisih Rp 450.000.',
            ],
        ];

        foreach ($items as $itemData) {
            $standard = isset($itemData['standard_code'], $standards[$itemData['standard_code']])
                ? $standards[$itemData['standard_code']]
                : null;

            $doubt = isset($itemData['explanation'])
                ? ['neutral_explanation' => $itemData['explanation']]
                : null;

            AuditItem::query()->updateOrCreate(
                [
                    'audit_id' => $audit->id,
                    'position' => $itemData['position'],
                ],
                [
                    'raw_name' => $itemData['raw_name'],
                    'volume' => $itemData['volume'],
                    'unit' => $itemData['unit'],
                    'unit_price_rp' => $itemData['unit_price_rp'],
                    'amount_rp' => $itemData['amount_rp'],
                    'standard_id' => $standard?->id,
                    'label' => $itemData['label'],
                    'over_pct' => $itemData['over_pct'],
                    'diff_rp' => $itemData['diff_rp'],
                    'doubt' => $doubt,
                ],
            );
        }

        $project = Project::query()->withoutGlobalScope('village')->updateOrCreate(
            ['village_id' => $village->id, 'slug' => 'saluran-irigasi-subak-uma-dalem'],
            [
                'audit_id' => $audit->id,
                'name' => 'Rehabilitasi Saluran Irigasi Subak Uma Dalem',
                'type' => ProjectType::SaluranIrigasi,
                'fund_source' => 'Dana Desa 2026',
                'year' => 2026,
                'lat' => -8.5420000,
                'lng' => 115.3220000,
                'progress_pct' => 30,
                'public_status' => PublicStatus::None,
                'current_cycle_id' => null,
                'published_at' => Carbon::now()->subDays(10),
            ],
        );

        $measurements = [
            ['name' => 'Panjang saluran', 'value' => 120.000, 'unit' => 'meter', 'how_to' => 'Ukur panjang saluran dari titik intake ke titik akhir.'],
            ['name' => 'Lebar dasar', 'value' => 0.600, 'unit' => 'meter', 'how_to' => 'Ukur lebar bagian dasar saluran pasangan batu.'],
            ['name' => 'Tinggi dinding', 'value' => 0.800, 'unit' => 'meter', 'how_to' => 'Ukur tinggi dinding pasangan batu dari dasar saluran.'],
        ];

        foreach ($measurements as $m) {
            ProjectMeasurement::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'name' => $m['name'],
                ],
                [
                    'value' => $m['value'],
                    'unit' => $m['unit'],
                    'how_to' => $m['how_to'],
                    'origin' => MeasurementOrigin::Template,
                ],
            );
        }

        // PRD 7.3: 1 laporan Selisih (di bawah ambang batas, tidak menampilkan status alert publik)
        $this->seedCitizenReport(
            $project,
            citizenIndex: 4,
            kind: ReportKind::Mismatch,
            note: 'Pasangan batu dinding saluran pada titik 80 meter belum diplester rapi.',
            daysAgo: 2,
            distanceM: 25,
        );
    }

    /**
     * Project 3: Renovasi Gedung Posyandu Br. Kaja.
     * Matches sample-rab-posyandu.pdf and PRD 7.3:
     * - 0 di atas standar (seluruh item Dalam Standar / Tanpa Acuan)
     * - Total: Rp 69.000.000
     * - Measurements: Luas bangunan 48 m2 (6m x 8m), Tinggi dinding 3.5 m, Jendela 4 unit
     * - Siklus selesai: Ditinjau BPD: Tidak ada selisih + catatan BPD publik
     * - Status: PublicStatus::Reviewed
     * - Reports: 4 laporan Sesuai
     *
     * @param  array<string, PriceStandard>  $standards
     */
    private function seedProject3(Village $village, User $owner, array $standards): void
    {
        $audit = Audit::query()->withoutGlobalScope('village')->updateOrCreate(
            [
                'village_id' => $village->id,
                'file_path' => 'audits/sample-rab-posyandu.pdf',
            ],
            [
                'owner_id' => $owner->id,
                'status' => AuditStatus::Published,
                'source' => 'upload',
                'read_ms' => 980,
                'written_total_rp' => 69000000,
                'total_ack' => true,
            ],
        );

        $items = [
            // I. PEKERJAAN TANAH & PONDASI
            [
                'position' => 1,
                'raw_name' => 'Galian tanah pondasi bangunan',
                'volume' => 18.00,
                'unit' => 'm3',
                'unit_price_rp' => 85000,
                'amount_rp' => 1530000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 2,
                'raw_name' => 'Pasangan batu kali pondasi 1:4',
                'volume' => 16.00,
                'unit' => 'm3',
                'unit_price_rp' => 330000,
                'amount_rp' => 5280000,
                'standard_code' => 'MAT-BTK-01',
                'label' => PriceLabel::WithinStandard,
                'over_pct' => 0,
                'diff_rp' => 0,
            ],
            [
                'position' => 3,
                'raw_name' => 'Urugan pasir bawah pondasi t=5cm',
                'volume' => 4.00,
                'unit' => 'm3',
                'unit_price_rp' => 220000,
                'amount_rp' => 880000,
                'standard_code' => 'MAT-PSR-01',
                'label' => PriceLabel::WithinStandard,
                'over_pct' => 0,
                'diff_rp' => 0,
            ],
            // II. PEKERJAAN STRUKTUR & DINDING
            [
                'position' => 4,
                'raw_name' => 'Semen Portland 50kg',
                'volume' => 110.00,
                'unit' => 'sak',
                'unit_price_rp' => 71000,
                'amount_rp' => 7810000,
                'standard_code' => 'MAT-SMN-01',
                'label' => PriceLabel::WithinStandard,
                'over_pct' => 0,
                'diff_rp' => 0,
            ],
            [
                'position' => 5,
                'raw_name' => 'Pasir pasang / cor',
                'volume' => 18.00,
                'unit' => 'm3',
                'unit_price_rp' => 235000,
                'amount_rp' => 4230000,
                'standard_code' => 'MAT-PSR-01',
                'label' => PriceLabel::WithinStandard,
                'over_pct' => 0,
                'diff_rp' => 0,
            ],
            [
                'position' => 6,
                'raw_name' => 'Batu split kerikil beton',
                'volume' => 12.00,
                'unit' => 'm3',
                'unit_price_rp' => 285000,
                'amount_rp' => 3420000,
                'standard_code' => 'MAT-SPL-01',
                'label' => PriceLabel::WithinStandard,
                'over_pct' => 0,
                'diff_rp' => 0,
            ],
            [
                'position' => 7,
                'raw_name' => 'Besi beton Ø 10 polos SNI',
                'volume' => 30.00,
                'unit' => 'btg',
                'unit_price_rp' => 76000,
                'amount_rp' => 2280000,
                'standard_code' => 'MAT-BSI-01',
                'label' => PriceLabel::WithinStandard,
                'over_pct' => 0,
                'diff_rp' => 0,
            ],
            [
                'position' => 8,
                'raw_name' => 'Bata merah bakar lokal',
                'volume' => 4200.00,
                'unit' => 'bh',
                'unit_price_rp' => 1800,
                'amount_rp' => 7560000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            // III. PEKERJAAN FINISHING & ATAP
            [
                'position' => 9,
                'raw_name' => 'Keramik lantai 40x40 polos',
                'volume' => 48.00,
                'unit' => 'm2',
                'unit_price_rp' => 75000,
                'amount_rp' => 3600000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 10,
                'raw_name' => 'Pengecatan tembok interior & eksterior',
                'volume' => 180.00,
                'unit' => 'm2',
                'unit_price_rp' => 22000,
                'amount_rp' => 3960000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 11,
                'raw_name' => 'Kusen dan daun pintu panel kayu',
                'volume' => 2.00,
                'unit' => 'unit',
                'unit_price_rp' => 1200000,
                'amount_rp' => 2400000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 12,
                'raw_name' => 'Jendela kaca bingkai kayu',
                'volume' => 4.00,
                'unit' => 'unit',
                'unit_price_rp' => 45000,
                'amount_rp' => 180000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 13,
                'raw_name' => 'Rangka atap baja ringan & genteng',
                'volume' => 60.00,
                'unit' => 'm2',
                'unit_price_rp' => 175000,
                'amount_rp' => 10500000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            // IV. UPAH TENAGA KERJA
            [
                'position' => 14,
                'raw_name' => 'Tukang batu & kayu',
                'volume' => 35.00,
                'unit' => 'OH',
                'unit_price_rp' => 150000,
                'amount_rp' => 5250000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
            [
                'position' => 15,
                'raw_name' => 'Pekerja terampil',
                'volume' => 70.00,
                'unit' => 'OH',
                'unit_price_rp' => 120000,
                'amount_rp' => 8400000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
            ],
        ];

        foreach ($items as $itemData) {
            $standard = isset($itemData['standard_code'], $standards[$itemData['standard_code']])
                ? $standards[$itemData['standard_code']]
                : null;

            AuditItem::query()->updateOrCreate(
                [
                    'audit_id' => $audit->id,
                    'position' => $itemData['position'],
                ],
                [
                    'raw_name' => $itemData['raw_name'],
                    'volume' => $itemData['volume'],
                    'unit' => $itemData['unit'],
                    'unit_price_rp' => $itemData['unit_price_rp'],
                    'amount_rp' => $itemData['amount_rp'],
                    'standard_id' => $standard?->id,
                    'label' => $itemData['label'],
                    'over_pct' => $itemData['over_pct'],
                    'diff_rp' => $itemData['diff_rp'],
                    'doubt' => null,
                ],
            );
        }

        $project = Project::query()->withoutGlobalScope('village')->updateOrCreate(
            ['village_id' => $village->id, 'slug' => 'renovasi-posyandu-banjar-tengah'],
            [
                'audit_id' => $audit->id,
                'name' => 'Renovasi Gedung Posyandu Br. Kaja',
                'type' => ProjectType::Gedung,
                'fund_source' => 'Dana Desa 2026',
                'year' => 2026,
                'lat' => -8.5430000,
                'lng' => 115.3230000,
                'progress_pct' => 90,
                'public_status' => PublicStatus::Reviewed,
                'published_at' => Carbon::now()->subDays(20),
            ],
        );

        // PRD 7.3: siklus selesai: "Ditinjau BPD: Tidak ada selisih" + catatan BPD publik
        $cycle = ReviewCycle::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'result' => ReviewOutcome::NoMismatch,
            ],
            [
                'opened_at' => Carbon::now()->subDays(15),
                'in_review_since' => Carbon::now()->subDays(10),
                'closed_at' => Carbon::now()->subDays(3),
                'note' => 'Pemeriksaan fisik posyandu sesuai spesifikasi RAB. Tidak ditemukan selisih volume atau bahan.',
            ],
        );

        ReviewNote::query()->updateOrCreate(
            [
                'cycle_id' => $cycle->id,
                'note' => 'BPD bersama perwakilan warga Banjar Kaja telah memverifikasi dimensi ruang gedung 6x8m, kusen, keramik, dan pekerjaan atap. Seluruh realisasi sesuai RAB.',
            ],
        );

        $project->update(['current_cycle_id' => $cycle->id]);

        $measurements = [
            ['name' => 'Luas bangunan', 'value' => 48.000, 'unit' => 'm2', 'how_to' => 'Ukur panjang kali lebar lantai bangunan posyandu (6 m × 8 m).'],
            ['name' => 'Tinggi dinding', 'value' => 3.500, 'unit' => 'meter', 'how_to' => 'Ukur tinggi dinding dari lantai keramik sampai ringbalk.'],
            ['name' => 'Jendela ventilasi', 'value' => 4.000, 'unit' => 'unit', 'how_to' => 'Hitung jumlah unit bukaan jendela kaca bingkai kayu.'],
        ];

        foreach ($measurements as $m) {
            ProjectMeasurement::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'name' => $m['name'],
                ],
                [
                    'value' => $m['value'],
                    'unit' => $m['unit'],
                    'how_to' => $m['how_to'],
                    'origin' => MeasurementOrigin::Template,
                ],
            );
        }

        // PRD 7.3: 4 laporan Sesuai
        $this->seedCitizenReport(
            $project,
            citizenIndex: 5,
            kind: ReportKind::Match,
            note: 'Dimensi gedung 6x8 meter dan lantai keramik sudah rapi sesuai rencana.',
            daysAgo: 14,
            distanceM: 10,
            cycle: $cycle,
        );

        $this->seedCitizenReport(
            $project,
            citizenIndex: 6,
            kind: ReportKind::Match,
            note: 'Kusen pintu kayu dan jendela kaca lengkap terpasang.',
            daysAgo: 12,
            distanceM: 15,
            cycle: $cycle,
        );

        $this->seedCitizenReport(
            $project,
            citizenIndex: 7,
            kind: ReportKind::Match,
            note: 'Rangka atap baja ringan dan genteng kokoh.',
            daysAgo: 8,
            distanceM: 12,
            cycle: $cycle,
        );

        $this->seedCitizenReport(
            $project,
            citizenIndex: 8,
            kind: ReportKind::Match,
            note: 'Pengecatan interior dan eksterior selesai rapi.',
            daysAgo: 4,
            distanceM: 18,
            cycle: $cycle,
        );
    }

    /**
     * Project 4: Penerangan Jalan Tenaga Surya 20 Titik.
     * Matches sample-rab-lampu-jalan.pdf and PRD 7.3:
     * - 2 di atas standar (Lampu PJU All-in-One Solar LED 60W, Baterai LiFePO4 cadangan 12V)
     * - Total: Rp 146.000.000
     * - Measurements: Jumlah titik lampu 20 titik, Tinggi tiang 7 meter
     * - Reports: 0 laporan (proyek tanpa laporan tidak hijau)
     * - Status: PublicStatus::None
     *
     * @param  array<string, PriceStandard>  $standards
     */
    private function seedProject4(Village $village, User $owner, array $standards): void
    {
        $audit = Audit::query()->withoutGlobalScope('village')->updateOrCreate(
            [
                'village_id' => $village->id,
                'file_path' => 'audits/sample-rab-lampu-jalan.pdf',
            ],
            [
                'owner_id' => $owner->id,
                'status' => AuditStatus::Published,
                'source' => 'upload',
                'read_ms' => 850,
                'written_total_rp' => 146000000,
                'total_ack' => true,
            ],
        );

        $items = [
            // I. PENGADAAN UNIT & TIANG PJU
            [
                'position' => 1,
                'raw_name' => 'Tiang PJU oktagonal galvanis 7 meter',
                'volume' => 20.00,
                'unit' => 'btg',
                'unit_price_rp' => 2400000,
                'amount_rp' => 48000000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
            [
                'position' => 2,
                'raw_name' => 'Lampu PJU All-in-One Solar LED 60 Watt',
                'volume' => 20.00,
                'unit' => 'unit',
                'unit_price_rp' => 3200000,
                'amount_rp' => 64000000,
                'standard_code' => 'PJU-SLR-01',
                'label' => PriceLabel::AboveStandard,
                'over_pct' => 23,
                'diff_rp' => 600000,
                'explanation' => 'Spesifikasi luminous efficacy tinggi 180 lm/W dan sertifikasi tahan cuaca pesisir tropis.',
            ],
            [
                'position' => 3,
                'raw_name' => 'Baterai Lithium LiFePO4 cadangan 12V',
                'volume' => 5.00,
                'unit' => 'unit',
                'unit_price_rp' => 1850000,
                'amount_rp' => 9250000,
                'standard_code' => 'PJU-BAT-01',
                'label' => PriceLabel::AboveStandard,
                'over_pct' => 28,
                'diff_rp' => 400000,
                'explanation' => 'Baterai cadangan deep cycle grade A dengan ketahanan lebih dari 3.000 siklus pengisian.',
            ],
            // II. PONDASI & INSTALASI
            [
                'position' => 4,
                'raw_name' => 'Pondasi beton umpak cor & angkur baut',
                'volume' => 20.00,
                'unit' => 'titik',
                'unit_price_rp' => 450000,
                'amount_rp' => 9000000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
            [
                'position' => 5,
                'raw_name' => 'Semen Portland 50kg untuk pondasi',
                'volume' => 30.00,
                'unit' => 'sak',
                'unit_price_rp' => 71000,
                'amount_rp' => 2130000,
                'standard_code' => 'MAT-SMN-01',
                'label' => PriceLabel::WithinStandard,
                'over_pct' => 0,
                'diff_rp' => 0,
                'explanation' => null,
            ],
            [
                'position' => 6,
                'raw_name' => 'Pasir cor & kerikil split',
                'volume' => 6.00,
                'unit' => 'm3',
                'unit_price_rp' => 260000,
                'amount_rp' => 1560000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
            [
                'position' => 7,
                'raw_name' => 'Kabel NYY dan kelengkapan grounding arde',
                'volume' => 20.00,
                'unit' => 'set',
                'unit_price_rp' => 180000,
                'amount_rp' => 3600000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
            // III. JASA PEMASANGAN & PENGUJIAN
            [
                'position' => 8,
                'raw_name' => 'Jasa ereksi tiang dan instalasi panel surya',
                'volume' => 20.00,
                'unit' => 'titik',
                'unit_price_rp' => 350000,
                'amount_rp' => 7000000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
            [
                'position' => 9,
                'raw_name' => 'Uji fungsi dan komisioning sistem',
                'volume' => 1.00,
                'unit' => 'ls',
                'unit_price_rp' => 1500000,
                'amount_rp' => 1500000,
                'standard_code' => null,
                'label' => PriceLabel::NoReference,
                'over_pct' => null,
                'diff_rp' => null,
                'explanation' => null,
            ],
        ];

        foreach ($items as $itemData) {
            $standard = isset($itemData['standard_code'], $standards[$itemData['standard_code']])
                ? $standards[$itemData['standard_code']]
                : null;

            $doubt = isset($itemData['explanation'])
                ? ['neutral_explanation' => $itemData['explanation']]
                : null;

            AuditItem::query()->updateOrCreate(
                [
                    'audit_id' => $audit->id,
                    'position' => $itemData['position'],
                ],
                [
                    'raw_name' => $itemData['raw_name'],
                    'volume' => $itemData['volume'],
                    'unit' => $itemData['unit'],
                    'unit_price_rp' => $itemData['unit_price_rp'],
                    'amount_rp' => $itemData['amount_rp'],
                    'standard_id' => $standard?->id,
                    'label' => $itemData['label'],
                    'over_pct' => $itemData['over_pct'],
                    'diff_rp' => $itemData['diff_rp'],
                    'doubt' => $doubt,
                ],
            );
        }

        $project = Project::query()->withoutGlobalScope('village')->updateOrCreate(
            ['village_id' => $village->id, 'slug' => 'lampu-jalan-tenaga-surya'],
            [
                'audit_id' => $audit->id,
                'name' => 'Penerangan Jalan Tenaga Surya 20 Titik',
                'type' => ProjectType::LampuJalan,
                'fund_source' => 'PAD 2026',
                'year' => 2026,
                'lat' => -8.5440000,
                'lng' => 115.3240000,
                'progress_pct' => 10,
                'public_status' => PublicStatus::None,
                'current_cycle_id' => null,
                'published_at' => Carbon::now()->subDays(5),
            ],
        );

        $measurements = [
            ['name' => 'Jumlah titik lampu', 'value' => 20.000, 'unit' => 'titik', 'how_to' => 'Hitung jumlah tiang penerangan jalan solar cell yang terpasang di sepanjang ruas jalan.'],
            ['name' => 'Tinggi tiang', 'value' => 7.000, 'unit' => 'meter', 'how_to' => 'Ukur tinggi tiang lampu dari atas pondasi sampai pangkal solar panel.'],
        ];

        foreach ($measurements as $m) {
            ProjectMeasurement::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'name' => $m['name'],
                ],
                [
                    'value' => $m['value'],
                    'unit' => $m['unit'],
                    'how_to' => $m['how_to'],
                    'origin' => MeasurementOrigin::Template,
                ],
            );
        }

        // PRD 7.3: belum ada laporan (proyek tanpa laporan tidak hijau)
    }
}
