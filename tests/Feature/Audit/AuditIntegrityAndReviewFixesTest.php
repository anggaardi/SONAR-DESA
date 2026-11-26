<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Models\Audit;
use App\Modules\Audit\Models\AuditItem;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Actions\PublishProject;
use App\Modules\Project\Enums\MeasurementOrigin;
use App\Modules\Project\Enums\ProjectType;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectChange;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Report\Http\Middleware\EnsureDeviceCookie;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');

    $this->village = Village::factory()->create(['regency' => 'Gianyar']);
    app(CurrentVillage::class)->set($this->village->id);

    $this->desaUser = User::factory()->create([
        'role' => Role::Desa,
        'village_id' => $this->village->id,
    ]);
});

// -----------------------------------------------------------------------------
// 1. Kritis 1: Data audit yang sudah terbit dilarang diedit/dihapus lewat review
// -----------------------------------------------------------------------------
it('forbids editing, confirming, and deleting audit items once published or in review (Kritis 1, D4, D12)', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::Published,
    ]);

    $project = Project::factory()->create([
        'village_id' => $this->village->id,
        'audit_id' => $audit->id,
        'public_status' => PublicStatus::InReview,
        'published_at' => now(),
    ]);

    $item = AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'raw_name' => 'Semen Portland',
        'volume' => 100,
        'unit' => 'sak',
        'unit_price_rp' => 65000,
        'amount_rp' => 6500000,
        'label' => PriceLabel::WithinStandard,
    ]);

    // Confirm must be 403
    $this->actingAs($this->desaUser)
        ->post("/audits/{$audit->id}/items/{$item->id}/confirm")
        ->assertForbidden();

    // Update must be 403
    $this->actingAs($this->desaUser)
        ->patch("/audits/{$audit->id}/items/{$item->id}", [
            'raw_name' => 'Semen Portland Ubah',
            'volume' => 120,
            'unit' => 'sak',
            'unit_price_rp' => 70000,
        ])
        ->assertForbidden();

    // Destroy must be 403
    $this->actingAs($this->desaUser)
        ->delete("/audits/{$audit->id}/items/{$item->id}")
        ->assertForbidden();

    // Verify item is unchanged in database
    $item->refresh();
    expect($item->volume)->toBe('100.000')
        ->and($item->unit_price_rp)->toBe(65000);
});

// -----------------------------------------------------------------------------
// 2. Kritis 2: Mengganti acuan standar lewat CCO tercatat transparan di riwayat
// -----------------------------------------------------------------------------
it('records standard_id changes in project_changes during CCO (Kritis 2, D13)', function () {
    $standard1 = PriceStandard::factory()->create([
        'regency' => 'Gianyar',
        'year' => 2026,
        'name' => 'Semen Standar Biasa',
        'price_rp' => 65000,
    ]);

    $standard2 = PriceStandard::factory()->create([
        'regency' => 'Gianyar',
        'year' => 2026,
        'name' => 'Semen Standar Premium',
        'price_rp' => 85000,
    ]);

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::Published,
        'file_path' => 'audits/sample.pdf',
    ]);

    $project = Project::factory()->create([
        'village_id' => $this->village->id,
        'audit_id' => $audit->id,
        'published_at' => now(),
        'public_status' => PublicStatus::None,
    ]);

    $item = AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'raw_name' => 'Semen Cor',
        'volume' => 100,
        'unit' => 'sak',
        'unit_price_rp' => 75000,
        'amount_rp' => 7500000,
        'standard_id' => $standard1->id,
        'label' => PriceLabel::AboveStandard,
    ]);

    $this->actingAs($this->desaUser)
        ->post("/desa/projects/{$project->id}/cco/items/{$item->id}", [
            'volume' => 100,
            'unit' => 'sak',
            'unit_price_rp' => 75000,
            'standard_id' => $standard2->id,
            'reason' => 'Penyesuaian acuan ke standar semen mutu tinggi sesuai adendum teknis',
        ])
        ->assertRedirect();

    $change = ProjectChange::where('project_id', $project->id)
        ->where('field', 'Standar Acuan Harga')
        ->first();

    expect($change)->not->toBeNull()
        ->and($change->old_value)->toContain('Semen Standar Biasa')
        ->and($change->new_value)->toContain('Semen Standar Premium')
        ->and($change->reason)->toBe('Penyesuaian acuan ke standar semen mutu tinggi sesuai adendum teknis');
});

// -----------------------------------------------------------------------------
// 3. Kritis 3: Alasan CCO panjang (250 karakter) disimpan ke kolom reason (text)
// -----------------------------------------------------------------------------
it('stores long CCO reason up to 255 chars in reason text column without 500 error (Kritis 3)', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::Published,
        'file_path' => 'audits/sample.pdf',
    ]);

    $project = Project::factory()->create([
        'village_id' => $this->village->id,
        'audit_id' => $audit->id,
        'published_at' => now(),
        'public_status' => PublicStatus::None,
    ]);

    $item = AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'raw_name' => 'Batu Belah',
        'volume' => 50,
        'unit' => 'm3',
        'unit_price_rp' => 200000,
        'amount_rp' => 10000000,
    ]);

    $longReason = trim(str_repeat('Alasan CCO penyesuaian lapangan karena kontur tanah berbatu ', 4)); // ~240 chars
    expect(strlen($longReason))->toBeGreaterThan(200);

    $this->actingAs($this->desaUser)
        ->post("/desa/projects/{$project->id}/cco/items/{$item->id}", [
            'volume' => 60,
            'unit' => 'm3',
            'unit_price_rp' => 200000,
            'reason' => $longReason,
        ])
        ->assertRedirect();

    $change = ProjectChange::where('project_id', $project->id)
        ->where('field', 'Volume Item')
        ->first();

    expect($change)->not->toBeNull()
        ->and($change->new_value)->toBe('60 m3')
        ->and($change->reason)->toBe($longReason);
});

// -----------------------------------------------------------------------------
// 4. Kritis 4: Nama proyek yang sama di desa lain tidak memicu UniqueConstraintViolation
// -----------------------------------------------------------------------------
it('handles duplicate project names across different villages without 500 slug conflict (Kritis 4)', function () {
    $village2 = Village::factory()->create(['name' => 'Desa Payangan', 'regency' => 'Gianyar']);
    $user2 = User::factory()->desa($village2)->create();

    // Desa 1 creates project "Jalan Melati"
    $project1 = Project::factory()->create([
        'village_id' => $this->village->id,
        'name' => 'Jalan Melati',
        'slug' => 'jalan-melati',
        'published_at' => now(),
    ]);

    // Now Desa 2 creates and publishes a project with the same name "Jalan Melati"
    app(CurrentVillage::class)->set($village2->id);

    $audit2 = Audit::factory()->create([
        'village_id' => $village2->id,
        'file_path' => 'audits/file2.pdf',
        'status' => AuditStatus::Ready,
        'written_total_rp' => 10000000,
    ]);

    AuditItem::factory()->create([
        'audit_id' => $audit2->id,
        'volume' => 10,
        'unit' => 'sak',
        'unit_price_rp' => 1000000,
        'amount_rp' => 10000000,
        'label' => PriceLabel::WithinStandard,
    ]);

    $publishAction = app(PublishProject::class);

    $project2 = $publishAction->execute($audit2, [
        'name' => 'Jalan Melati',
        'type' => ProjectType::RabatBeton,
        'fund_source' => 'Dana Desa (DDS)',
        'year' => 2026,
        'lat' => -8.541234,
        'lng' => 115.321456,
        'measurements' => [
            ['name' => 'Ketebalan Beton Cor', 'value' => 12, 'unit' => 'cm', 'origin' => MeasurementOrigin::Template],
            ['name' => 'Lebar Badan Jalan', 'value' => 2.5, 'unit' => 'meter', 'origin' => MeasurementOrigin::Template],
            ['name' => 'Panjang Total Pekerjaan', 'value' => 300, 'unit' => 'meter', 'origin' => MeasurementOrigin::Template],
        ],
    ]);

    expect($project2)->not->toBeNull();
    expect($project2->slug)->not->toBe('jalan-melati');
    expect($project2->slug)->toStartWith('jalan-melati-');
});

// -----------------------------------------------------------------------------
// 5. Tinggi 5: Pengecekan selisih total (F3) dan written_total_rp terjaga
// -----------------------------------------------------------------------------
it('preserves written_total_rp on edit/delete and enforces total_ack when mismatch exists (Tinggi 5, F3)', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'file_path' => 'audits/sample.pdf',
        'status' => AuditStatus::NeedsReview,
        'written_total_rp' => 10000000, // written on paper: 10 juta
        'total_ack' => false,
    ]);

    $item = AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'raw_name' => 'Pasir Pasang',
        'volume' => 10,
        'unit' => 'm3',
        'unit_price_rp' => 200000,
        'amount_rp' => 2000000, // sum is 2 juta -> diff 8 juta
        'label' => PriceLabel::NeedsCheck,
        'corrected' => false,
    ]);

    // Editing item must NOT overwrite written_total_rp
    $this->actingAs($this->desaUser)
        ->patch("/audits/{$audit->id}/items/{$item->id}", [
            'raw_name' => 'Pasir Pasang',
            'volume' => 15,
            'unit' => 'm3',
            'unit_price_rp' => 200000,
            'amount_rp' => 3000000,
        ])
        ->assertRedirect();

    $audit->refresh();
    expect($audit->written_total_rp)->toBe(10000000); // Must remain 10 juta!

    // Publishing without acknowledging total mismatch must be rejected
    $publishAction = app(PublishProject::class);

    expect(fn () => $publishAction->execute($audit, [
        'name' => 'Proyek Selisih Total',
        'type' => ProjectType::RabatBeton,
        'fund_source' => 'Dana Desa (DDS)',
        'year' => 2026,
        'lat' => -8.541234,
        'lng' => 115.321456,
        'measurements' => [
            ['name' => 'Ketebalan Beton Cor', 'value' => 12, 'unit' => 'cm', 'origin' => MeasurementOrigin::Template],
            ['name' => 'Lebar Badan Jalan', 'value' => 2.5, 'unit' => 'meter', 'origin' => MeasurementOrigin::Template],
            ['name' => 'Panjang Total Pekerjaan', 'value' => 300, 'unit' => 'meter', 'origin' => MeasurementOrigin::Template],
        ],
    ]))->toThrow(ValidationException::class);

    // Acknowledging total via ack-total route
    $this->actingAs($this->desaUser)
        ->post("/audits/{$audit->id}/ack-total")
        ->assertRedirect();

    $audit->refresh();
    expect($audit->total_ack)->toBeTrue();

    // Now publishing succeeds
    $project = $publishAction->execute($audit, [
        'name' => 'Proyek Selisih Total',
        'type' => ProjectType::RabatBeton,
        'fund_source' => 'Dana Desa (DDS)',
        'year' => 2026,
        'lat' => -8.541234,
        'lng' => 115.321456,
        'measurements' => [
            ['name' => 'Ketebalan Beton Cor', 'value' => 12, 'unit' => 'cm', 'origin' => MeasurementOrigin::Template],
            ['name' => 'Lebar Badan Jalan', 'value' => 2.5, 'unit' => 'meter', 'origin' => MeasurementOrigin::Template],
            ['name' => 'Panjang Total Pekerjaan', 'value' => 300, 'unit' => 'meter', 'origin' => MeasurementOrigin::Template],
        ],
    ]);

    expect($project->isPublished())->toBeTrue();
});

// -----------------------------------------------------------------------------
// 6. Tinggi 6: Pemicu Tinjauan BPD (S5 / D16) aktif saat mencapai ambang laporan
// -----------------------------------------------------------------------------
it('automatically triggers review cycle when mismatch report threshold is reached (Tinggi 6, S5, D16)', function () {
    config()->set('sonar.review.threshold', 3);

    $project = Project::factory()->create([
        'village_id' => $this->village->id,
        'slug' => 'proyek-uji-bpd',
        'lat' => -8.541234,
        'lng' => 115.321456,
        'published_at' => now(),
        'public_status' => PublicStatus::None,
        'current_cycle_id' => null,
    ]);

    $m1 = ProjectMeasurement::factory()->create([
        'project_id' => $project->id,
        'name' => 'Ketebalan Beton Cor',
        'value' => 12.0,
        'unit' => 'cm',
    ]);
    $m2 = ProjectMeasurement::factory()->create([
        'project_id' => $project->id,
        'name' => 'Lebar Badan Jalan',
        'value' => 2.5,
        'unit' => 'meter',
    ]);

    for ($i = 1; $i <= 3; $i++) {
        $citizen = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);
        $cookie = str_pad("dev-token-{$i}", 40, '0');

        $this->actingAs($citizen)
            ->withCookie(EnsureDeviceCookie::COOKIE, $cookie)
            ->post("/p/{$project->slug}/reports", [
                'answers' => [
                    ['measurement_id' => $m1->id, 'answer' => 'mismatch', 'measured_value' => '8.0'],
                    ['measurement_id' => $m2->id, 'answer' => 'match', 'measured_value' => ''],
                ],
                'photos' => [UploadedFile::fake()->image('foto.jpg', 640, 480)],
                'note' => 'Selisih tebal lapangan',
                'lat' => -8.541234,
                'lng' => 115.321456,
                'accuracy_m' => 10,
            ])
            ->assertRedirect();
    }

    $project->refresh();
    // Must now be InReview with cycle opened! (D16, S5, PD-17)
    expect($project->public_status)->toBe(PublicStatus::InReview);
    expect($project->current_cycle_id)->not->toBeNull();

    $cycleId = $project->current_cycle_id;

    // 4th reporter sends mismatch -> does NOT open a new cycle (PD-18)
    $citizen4 = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);
    $cookie4 = str_pad('dev-token-4', 40, '0');

    $this->actingAs($citizen4)
        ->withCookie(EnsureDeviceCookie::COOKIE, $cookie4)
        ->post("/p/{$project->slug}/reports", [
            'answers' => [
                ['measurement_id' => $m1->id, 'answer' => 'mismatch', 'measured_value' => '8.0'],
                ['measurement_id' => $m2->id, 'answer' => 'match', 'measured_value' => ''],
            ],
            'photos' => [UploadedFile::fake()->image('foto.jpg', 640, 480)],
            'note' => 'Pelapor keempat',
            'lat' => -8.541234,
            'lng' => 115.321456,
            'accuracy_m' => 10,
        ])
        ->assertRedirect();

    $project->refresh();
    expect($project->current_cycle_id)->toBe($cycleId);
    expect($project->public_status)->toBe(PublicStatus::InReview);
});

// -----------------------------------------------------------------------------
// 7. Sedang 8 & 9: Alasan baris kuning tersimpan di doubt, dan koreksi dievaluasi
// -----------------------------------------------------------------------------
it('stores doubt reason for yellow rows and properly re-evaluates corrected yellow rows on confirm (Sedang 8 & 9)', function () {
    $standard = PriceStandard::factory()->create([
        'regency' => 'Gianyar',
        'year' => 2026,
        'unit' => 'sak',
        'price_rp' => 70000,
    ]);

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::NeedsReview,
        'file_path' => 'audits/sample.pdf',
    ]);

    // Item with unit mismatch ('m3' vs 'sak')
    $item = AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'raw_name' => 'Semen Zak',
        'volume' => 10,
        'unit' => 'm3',
        'unit_price_rp' => 65000,
        'amount_rp' => 650000,
        'standard_id' => $standard->id,
        'label' => PriceLabel::NeedsCheck,
        'doubt' => null,
        'corrected' => false,
    ]);

    // Updating with invalid unit still leaves it NeedsCheck and corrected = false
    $this->actingAs($this->desaUser)
        ->patch("/audits/{$audit->id}/items/{$item->id}", [
            'raw_name' => 'Semen Zak',
            'volume' => 10,
            'unit' => 'm3', // still mismatch
            'unit_price_rp' => 65000,
            'standard_id' => $standard->id,
        ])
        ->assertRedirect();

    $item->refresh();
    expect($item->label)->toBe(PriceLabel::NeedsCheck);
    expect($item->corrected)->toBeFalse(); // Must NOT be true automatically!
    expect($item->doubt)->toBe(['reason' => 'satuan_berbeda']); // Stored in doubt (Sedang 9)

    // Operator confirms "Sudah Benar"
    $this->actingAs($this->desaUser)
        ->post("/audits/{$audit->id}/items/{$item->id}/confirm")
        ->assertRedirect();

    $item->refresh();
    expect($item->corrected)->toBeTrue();
    // Since 65.000 <= 70.000, label resolves to WithinStandard (Sedang 8)
    expect($item->label)->toBe(PriceLabel::WithinStandard);
});

// -----------------------------------------------------------------------------
// 8. Sedang 10: Daftar standar harga difilter kabupaten dan tahun
// -----------------------------------------------------------------------------
it('filters price standards by regency and year on review and manage (Sedang 10)', function () {
    // Standard Gianyar 2026 (target)
    $stdGianyar2026 = PriceStandard::factory()->create([
        'regency' => 'Gianyar',
        'year' => 2026,
        'name' => 'Batu Kali Gianyar 2026',
    ]);

    // Standard Badung 2026 (different regency)
    $stdBadung2026 = PriceStandard::factory()->create([
        'regency' => 'Badung',
        'year' => 2026,
        'name' => 'Batu Kali Badung 2026',
    ]);

    // Standard Gianyar 2024 (different year)
    $stdGianyar2024 = PriceStandard::factory()->create([
        'regency' => 'Gianyar',
        'year' => 2024,
        'name' => 'Batu Kali Gianyar 2024',
    ]);

    // 1. Review page for an audit
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'file_path' => 'audits/sample.pdf',
    ]);

    $this->actingAs($this->desaUser)
        ->get("/audits/{$audit->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('priceStandards', 1)
            ->where('priceStandards.0.name', 'Batu Kali Gianyar 2026')
        );

    // 2. Manage page for a project of year 2026
    $project = Project::factory()->create([
        'village_id' => $this->village->id,
        'audit_id' => $audit->id,
        'year' => 2026,
        'published_at' => now(),
    ]);

    $this->actingAs($this->desaUser)
        ->get("/desa/projects/{$project->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('standards', 1)
            ->where('standards.0.name', 'Batu Kali Gianyar 2026')
        );
});

// -----------------------------------------------------------------------------
// 9. Rendah: CCO GPS float comparison, how_to changes, draft cleanup, and CCO policy
// -----------------------------------------------------------------------------
it('verifies Rendah fixes: GPS float comparison, how_to history, draft deletion cleanup, and CCO policy on drafts', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'file_path' => 'audits/sample_draft.pdf',
        'status' => AuditStatus::Ready,
    ]);
    Storage::disk('local')->put('audits/sample_draft.pdf', 'dummy content');

    $draftProject = Project::factory()->create([
        'village_id' => $this->village->id,
        'audit_id' => $audit->id,
        'lat' => -8.5412340,
        'lng' => 115.3214560,
        'published_at' => null, // Draft!
    ]);

    $m = ProjectMeasurement::factory()->create([
        'project_id' => $draftProject->id,
        'name' => 'Ketebalan Beton Cor',
        'value' => 12.0,
        'unit' => 'cm',
        'how_to' => 'Ukur dengan mistar',
    ]);

    // 1. CCO policy on draft project: updateCco must be forbidden (Rendah 4)
    $this->actingAs($this->desaUser)
        ->post("/desa/projects/{$draftProject->id}/cco/gps", [
            'lat' => -8.549999,
            'lng' => 115.329999,
            'reason' => 'Koreksi titik geofence',
        ])
        ->assertForbidden();

    // 2. Deleting draft project cleans up audit and physical file (Rendah 3)
    $this->actingAs($this->desaUser)
        ->delete("/desa/projects/{$draftProject->id}")
        ->assertRedirect(route('desa.dashboard'));

    expect(Project::find($draftProject->id))->toBeNull();
    expect(Audit::find($audit->id))->toBeNull();
    expect(Storage::disk('local')->exists('audits/sample_draft.pdf'))->toBeFalse();

    // 3. For published project: GPS float equality does NOT trigger false change record (Rendah 1)
    $pubAudit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::Published,
        'file_path' => 'audits/pub.pdf',
    ]);

    $pubProject = Project::factory()->create([
        'village_id' => $this->village->id,
        'audit_id' => $pubAudit->id,
        'lat' => -8.5412340,
        'lng' => 115.3214560,
        'published_at' => now(),
        'public_status' => PublicStatus::None,
    ]);

    $pubM = ProjectMeasurement::factory()->create([
        'project_id' => $pubProject->id,
        'name' => 'Tebal Rabat',
        'value' => 12.0,
        'unit' => 'cm',
        'how_to' => 'Ukur pakai mistar',
    ]);

    // Sending numerically identical coordinates: -8.541234 vs -8.5412340
    $this->actingAs($this->desaUser)
        ->post("/desa/projects/{$pubProject->id}/cco/gps", [
            'lat' => -8.541234,
            'lng' => 115.321456,
            'reason' => 'Cek ulang tanpa perubahan koordinat',
        ])
        ->assertRedirect();

    expect(ProjectChange::where('project_id', $pubProject->id)->where('field', 'Titik Lokasi GPS')->count())->toBe(0);

    // 4. how_to change is recorded in project_changes (Rendah 2)
    $this->actingAs($this->desaUser)
        ->post("/desa/projects/{$pubProject->id}/cco/measurements/{$pubM->id}", [
            'value' => 12.0,
            'how_to' => 'Ukur dengan jangka sorong kalibrasi',
            'reason' => 'Pembaruan metode ukur agar lebih presisi',
        ])
        ->assertRedirect();

    $howToChange = ProjectChange::where('project_id', $pubProject->id)
        ->where('field', 'Petunjuk Pengukuran')
        ->first();

    expect($howToChange)->not->toBeNull()
        ->and($howToChange->old_value)->toBe('Ukur pakai mistar')
        ->and($howToChange->new_value)->toBe('Ukur dengan jangka sorong kalibrasi')
        ->and($howToChange->reason)->toBe('Pembaruan metode ukur agar lebih presisi');
});
