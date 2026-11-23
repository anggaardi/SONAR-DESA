<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Models\Audit;
use App\Modules\Audit\Models\AuditItem;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Actions\PublishProject;
use App\Modules\Project\Enums\MeasurementOrigin;
use App\Modules\Project\Enums\ProjectType;
use App\Modules\Project\Models\Project;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->village = Village::factory()->create();
    app(CurrentVillage::class)->set($this->village->id);
    $this->action = app(PublishProject::class);
});

it('rejects publishing when audit has no physical file path (D9)', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'file_path' => null,
    ]);

    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'label' => PriceLabel::WithinStandard,
    ]);

    $data = [
        'name' => 'Proyek Tanpa Berkas',
        'type' => ProjectType::RabatBeton,
        'fund_source' => 'Dana Desa',
        'year' => 2026,
        'lat' => -8.541234,
        'lng' => 115.321456,
        'measurements' => [
            ['name' => 'Ketebalan Beton Cor', 'value' => 12, 'unit' => 'cm'],
            ['name' => 'Lebar Badan Jalan', 'value' => 2.5, 'unit' => 'meter'],
            ['name' => 'Panjang Total Pekerjaan', 'value' => 450, 'unit' => 'meter'],
        ],
    ];

    expect(fn () => $this->action->execute($audit, $data))
        ->toThrow(ValidationException::class);
});

it('rejects publishing when audit has unresolved yellow rows / NeedsCheck (D1)', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'file_path' => 'audits/sample.pdf',
    ]);

    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'label' => PriceLabel::NeedsCheck,
        'corrected' => false,
    ]);

    $data = [
        'name' => 'Proyek Baris Kuning',
        'type' => ProjectType::RabatBeton,
        'fund_source' => 'Dana Desa',
        'year' => 2026,
        'lat' => -8.541234,
        'lng' => 115.321456,
        'measurements' => [
            ['name' => 'Ketebalan Beton Cor', 'value' => 12, 'unit' => 'cm'],
            ['name' => 'Lebar Badan Jalan', 'value' => 2.5, 'unit' => 'meter'],
            ['name' => 'Panjang Total Pekerjaan', 'value' => 450, 'unit' => 'meter'],
        ],
    ];

    expect(fn () => $this->action->execute($audit, $data))
        ->toThrow(ValidationException::class);
});

it('rejects publishing when audit has no items (D8, D9)', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'file_path' => 'audits/sample.pdf',
    ]);

    $data = [
        'name' => 'Proyek Tanpa Item',
        'type' => ProjectType::RabatBeton,
        'fund_source' => 'Dana Desa',
        'year' => 2026,
        'lat' => -8.541234,
        'lng' => 115.321456,
        'measurements' => [
            ['name' => 'Ketebalan Beton Cor', 'value' => 12, 'unit' => 'cm'],
            ['name' => 'Lebar Badan Jalan', 'value' => 2.5, 'unit' => 'meter'],
            ['name' => 'Panjang Total Pekerjaan', 'value' => 450, 'unit' => 'meter'],
        ],
    ];

    expect(fn () => $this->action->execute($audit, $data))
        ->toThrow(ValidationException::class);
});

it('rejects publishing when GPS coordinates are missing or invalid (D3)', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'file_path' => 'audits/sample.pdf',
    ]);

    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'label' => PriceLabel::WithinStandard,
    ]);

    $data = [
        'name' => 'Proyek Tanpa GPS',
        'type' => ProjectType::RabatBeton,
        'fund_source' => 'Dana Desa',
        'year' => 2026,
        'lat' => null,
        'lng' => null,
        'measurements' => [
            ['name' => 'Ketebalan Beton Cor', 'value' => 12, 'unit' => 'cm'],
            ['name' => 'Lebar Badan Jalan', 'value' => 2.5, 'unit' => 'meter'],
            ['name' => 'Panjang Total Pekerjaan', 'value' => 450, 'unit' => 'meter'],
        ],
    ];

    expect(fn () => $this->action->execute($audit, $data))
        ->toThrow(ValidationException::class);
});

it('rejects publishing when template key measurements are missing (D2)', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'file_path' => 'audits/sample.pdf',
    ]);

    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'label' => PriceLabel::WithinStandard,
    ]);

    // Rabat beton requires Ketebalan, Lebar, Panjang. Here we omit Panjang.
    $data = [
        'name' => 'Proyek Rabat Kurang Ukuran',
        'type' => ProjectType::RabatBeton,
        'fund_source' => 'Dana Desa',
        'year' => 2026,
        'lat' => -8.541234,
        'lng' => 115.321456,
        'measurements' => [
            ['name' => 'Ketebalan Beton Cor', 'value' => 12, 'unit' => 'cm'],
            ['name' => 'Lebar Badan Jalan', 'value' => 2.5, 'unit' => 'meter'],
        ],
    ];

    expect(fn () => $this->action->execute($audit, $data))
        ->toThrow(ValidationException::class);
});

it('rejects publishing when project is already published (D10)', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'file_path' => 'audits/sample.pdf',
        'status' => AuditStatus::Published,
    ]);

    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'label' => PriceLabel::WithinStandard,
    ]);

    Project::factory()->create([
        'village_id' => $this->village->id,
        'audit_id' => $audit->id,
        'published_at' => now(),
    ]);

    $data = [
        'name' => 'Proyek Terbit Ulang',
        'type' => ProjectType::RabatBeton,
        'fund_source' => 'Dana Desa',
        'year' => 2026,
        'lat' => -8.541234,
        'lng' => 115.321456,
        'measurements' => [
            ['name' => 'Ketebalan Beton Cor', 'value' => 12, 'unit' => 'cm'],
            ['name' => 'Lebar Badan Jalan', 'value' => 2.5, 'unit' => 'meter'],
            ['name' => 'Panjang Total Pekerjaan', 'value' => 450, 'unit' => 'meter'],
        ],
    ];

    expect(fn () => $this->action->execute($audit, $data))
        ->toThrow(ValidationException::class);
});

it('successfully publishes project when all gatekeeper and template criteria are met', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'file_path' => 'audits/rab-melati.pdf',
        'status' => AuditStatus::Ready,
        'written_total_rp' => null,
    ]);

    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'raw_name' => 'Semen Portland Komposit',
        'volume' => 100,
        'unit' => 'sak',
        'unit_price_rp' => 70000,
        'amount_rp' => 7000000,
        'label' => PriceLabel::WithinStandard,
    ]);

    $data = [
        'name' => 'Betonisasi Jalan Usaha Tani Banjar Melati',
        'type' => ProjectType::RabatBeton,
        'fund_source' => 'Dana Desa (DDS)',
        'year' => 2026,
        'lat' => -8.541234,
        'lng' => 115.321456,
        'measurements' => [
            [
                'name' => 'Ketebalan Beton Cor',
                'value' => 12.0,
                'unit' => 'cm',
                'how_to' => 'Ukur pada bekisting tepi.',
                'origin' => MeasurementOrigin::Template->value,
            ],
            [
                'name' => 'Lebar Badan Jalan',
                'value' => 2.5,
                'unit' => 'meter',
                'how_to' => 'Ukur tegak lurus.',
                'origin' => MeasurementOrigin::Template->value,
            ],
            [
                'name' => 'Panjang Total Pekerjaan',
                'value' => 450.0,
                'unit' => 'meter',
                'how_to' => 'Bentang nol sampai akhir.',
                'origin' => MeasurementOrigin::Template->value,
            ],
            [
                'name' => 'Bahu Jalan Kiri Kanan',
                'value' => 0.5,
                'unit' => 'meter',
                'how_to' => 'Lebar berem tanah.',
                'origin' => MeasurementOrigin::Desa->value,
            ],
        ],
    ];

    $project = $this->action->execute($audit, $data);

    expect($project)->not->toBeNull();
    expect($project->isPublished())->toBeTrue();
    expect($project->published_at)->not->toBeNull();
    expect($project->name)->toBe('Betonisasi Jalan Usaha Tani Banjar Melati');
    expect($project->measurements()->count())->toBe(4);

    $audit->refresh();
    expect($audit->status)->toBe(AuditStatus::Published);
    expect($audit->written_total_rp)->toBe(7000000);
});
