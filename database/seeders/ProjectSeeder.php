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
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Report\Models\Report;
use App\Modules\Review\Enums\ReviewOutcome;
use App\Modules\Review\Models\ReviewCycle;
use App\Modules\Review\Models\ReviewNote;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds sample projects according to PRD Fitur Inti 7.3 (Data Awal Desa Contoh).
 * Idempotent: can be executed multiple times without duplicating rows.
 */
final class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $village = Village::query()->where('slug', 'desa-contoh-template')->first();

        if (! $village) {
            return;
        }

        $owner = User::query()->where('village_id', $village->id)->where('role', Role::Desa)->first();

        if (! $owner) {
            return;
        }

        app(CurrentVillage::class)->set($village->id);

        try {
            $standards = $this->seedStandards($village->regency);
            $this->seedSampleProject($village, $owner, $standards);
        } finally {
            app(CurrentVillage::class)->clear();
        }
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
                    'source_ref' => 'SSH Kabupaten Gianyar 2026',
                ],
            );
        }

        return $mapped;
    }

    /**
     * @param  array<string, PriceStandard>  $standards
     */
    private function seedSampleProject(Village $village, User $owner, array $standards): void
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
                'written_total_rp' => 105000000,
                'total_ack' => true,
            ],
        );

        $items = [
            [
                'position' => 1,
                'raw_name' => 'Semen Gresik 50kg',
                'volume' => 380.00,
                'unit' => 'sak',
                'unit_price_rp' => 85000,
                'amount_rp' => 32300000,
                'standard_code' => 'MAT-SMN-01',
                'label' => PriceLabel::AboveStandard,
                'over_pct' => 18,
                'diff_rp' => 13000,
                'explanation' => 'Medan tanjakan 18 derajat membutuhkan semen mutu lebih tinggi dan biaya angkut material lebih mahal.',
            ],
            [
                'position' => 2,
                'raw_name' => 'Pasir Pasang',
                'volume' => 45.00,
                'unit' => 'm3',
                'unit_price_rp' => 280000,
                'amount_rp' => 12600000,
                'standard_code' => 'MAT-PSR-01',
                'label' => PriceLabel::AboveStandard,
                'over_pct' => 17,
                'diff_rp' => 40000,
                'explanation' => 'Jarak angkut dari depo pasir melebihi radius standar distribusi.',
            ],
            [
                'position' => 3,
                'raw_name' => 'Batu Split 2/3',
                'volume' => 60.00,
                'unit' => 'm3',
                'unit_price_rp' => 290000,
                'amount_rp' => 17400000,
                'standard_code' => 'MAT-SPL-01',
                'label' => PriceLabel::WithinStandard,
                'over_pct' => 0,
                'diff_rp' => 0,
                'explanation' => null,
            ],
            [
                'position' => 4,
                'raw_name' => 'Upah Tukang & Pekerja',
                'volume' => 280.00,
                'unit' => 'HOK',
                'unit_price_rp' => 120000,
                'amount_rp' => 33600000,
                'standard_code' => null,
                'label' => PriceLabel::WithinStandard,
                'over_pct' => 0,
                'diff_rp' => 0,
                'explanation' => null,
            ],
            [
                'position' => 5,
                'raw_name' => 'Sewa Molen Beton',
                'volume' => 14.00,
                'unit' => 'hari',
                'unit_price_rp' => 650000,
                'amount_rp' => 9100000,
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
                'progress_pct' => 65,
                'public_status' => PublicStatus::Reviewed,
                'published_at' => Carbon::now()->subDays(14),
            ],
        );

        // Seed review cycle & note per PRD 7.3
        $cycle = ReviewCycle::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'result' => ReviewOutcome::FollowedUp,
            ],
            [
                'opened_at' => Carbon::now()->subDays(10),
                'in_review_since' => Carbon::now()->subDays(7),
                'closed_at' => Carbon::now()->subDays(3),
                'note' => 'Penjelasan biaya angkut dan medan tanjakan telah diklarifikasi bersama BPD.',
            ],
        );

        ReviewNote::query()->updateOrCreate(
            [
                'cycle_id' => $cycle->id,
                'note' => 'Medan tanjakan 18 derajat membutuhkan semen mutu lebih tinggi dan biaya angkut material lebih mahal.',
            ],
        );

        $project->update(['current_cycle_id' => $cycle->id]);

        $measurements = [
            ['name' => 'Panjang', 'value' => 350.000, 'unit' => 'meter', 'how_to' => 'Ukur dari patok awal RT 02 sampai batas RT 03.'],
            ['name' => 'Lebar', 'value' => 3.000, 'unit' => 'meter', 'how_to' => 'Ukur lebar badan jalan beton.'],
            ['name' => 'Tebal', 'value' => 0.150, 'unit' => 'meter', 'how_to' => 'Ukur ketebalan pada sisi pinggir dan tengah cor beton.'],
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
            ['path' => 'progress/sample-progress-65.webp', 'progress_pct' => 65, 'days_ago' => 2],
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

        // Seed 12 in-radius valid citizen reports per PRD 7.3
        $existingReportsCount = Report::query()->where('project_id', $project->id)->count();
        if ($existingReportsCount < 12) {
            for ($i = $existingReportsCount; $i < 12; $i++) {
                $wargaUser = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);
                Report::factory()->create([
                    'project_id' => $project->id,
                    'user_id' => $wargaUser->id,
                    'kind' => ReportKind::Match,
                    'invalid_reason' => null,
                    'created_at' => Carbon::now()->subDays(random_int(1, 10)),
                ]);
            }
        }
    }
}
