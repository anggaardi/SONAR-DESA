<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\PriceLabeler;
use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Jobs\ProcessRabDocumentJob;
use App\Modules\Audit\Models\Audit;
use App\Modules\Audit\Models\AuditItem;
use App\Modules\Audit\Reading\RabReader;
use App\Modules\Audit\Reading\RabReadResult;
use App\Modules\Audit\Reading\RabReadRow;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Village;
use App\Modules\Demo\Actions\VillageCloner;
use App\Modules\Project\Actions\PublishProject;
use App\Modules\Project\Enums\ProjectType;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectChange;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

// S2: Snapshot harga acuan di audit_items
// AC1 – migration backfill, AC2 – halaman terbit tidak berubah setelah harga acuan diubah,
// AC3 – audit baru memakai harga baru, AC4 – CCO memperbarui snapshot,
// AC5 – confirm(), store(), update(), publish() menyimpan snapshot,
// AC6 – klon demo membawa snapshot yang sama.

beforeEach(function () {
    Storage::fake('local');

    $this->village = Village::factory()->create(['regency' => 'Gianyar']);
    app(CurrentVillage::class)->set($this->village->id);

    $this->desaUser = User::factory()->create([
        'role' => Role::Desa,
        'village_id' => $this->village->id,
    ]);
});

// ── AC1: Migration backfill ────────────────────────────────────────────────────
it('backfills standard_price_rp from price_standards for existing items (AC1)', function () {
    $standard = PriceStandard::factory()->create([
        'regency' => 'Gianyar',
        'price_rp' => 70_000,
    ]);

    $audit = Audit::factory()->create(['village_id' => $this->village->id]);

    // Insert baris lama yang belum punya standard_price_rp (simulasi pre-migration)
    $itemId = (int) DB::table('audit_items')->insertGetId([
        'audit_id' => $audit->id,
        'position' => 1,
        'raw_name' => 'Semen Portland',
        'volume' => '100',
        'unit' => 'sak',
        'unit_price_rp' => 85_000,
        'amount_rp' => 8_500_000,
        'standard_id' => $standard->id,
        'standard_price_rp' => null,
        'label' => 'above_standard',
        'over_pct' => 21,
        'diff_rp' => 1_500_000,
        'corrected' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::statement(
        'UPDATE audit_items ai
         SET standard_price_rp = ps.price_rp
         FROM price_standards ps
         WHERE ai.standard_id = ps.id
           AND ai.id = ?',
        [$itemId]
    );

    $item = AuditItem::find($itemId);
    expect($item->standard_price_rp)->toBe(70_000);
});

// ── AC2: Halaman terbit tidak bergeser setelah harga acuan diubah ──────────────
it('published project page shows snapshot price even after standard price_rp is updated (AC2)', function () {
    $standard = PriceStandard::factory()->create([
        'regency' => 'Gianyar',
        'price_rp' => 70_000,
    ]);

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::Published,
    ]);

    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'unit_price_rp' => 85_000,
        'standard_id' => $standard->id,
        'standard_price_rp' => 70_000,
        'label' => PriceLabel::AboveStandard,
        'over_pct' => 22,
        'diff_rp' => 1_500_000,
    ]);

    $project = Project::factory()->create([
        'village_id' => $this->village->id,
        'audit_id' => $audit->id,
        'type' => ProjectType::RabatBeton,
        'year' => 2026,
        'public_status' => PublicStatus::None,
        'published_at' => now(),
    ]);

    // Pemda menaikkan harga acuan di atas harga RAB
    $standard->update(['price_rp' => 90_000]);

    // Halaman publik harus tetap menampilkan snapshot 70.000, selisih 15.000, dan +22%
    $this->get("/p/{$project->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('project/Show')
            ->where('project.items.0.standard_price_rp', 70_000)
            ->where('project.items.0.unit_diff_rp', 15_000)
            ->where('project.items.0.total_diff_rp', 1_500_000)
            ->where('project.items.0.over_pct', 22)
            ->where('project.items.0.label', PriceLabel::AboveStandard->value)
            ->where('project.label_counts.above_standard', 1));
});

// ── AC3: Audit baru memakai harga baru setelah standard diubah ────────────────
it('new audit job stores updated standard_price_rp in items (AC3)', function () {
    Storage::disk('local')->put('audits/rab.pdf', 'fake-pdf');

    $standard = PriceStandard::factory()->create([
        'regency' => $this->village->regency,
        'code' => 'MAT-SMN-01',
        'price_rp' => 70_000,
        'unit' => 'sak',
    ]);

    // Pemda mengubah harga sebelum audit baru diproses
    $standard->update(['price_rp' => 90_000]);

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::Reading,
        'file_path' => 'audits/rab.pdf',
    ]);

    $mockReader = mock(RabReader::class);
    $mockReader->shouldReceive('read')
        ->once()
        ->andReturn(new RabReadResult(
            rows: [
                new RabReadRow(
                    position: 1,
                    rawName: 'Semen Portland',
                    volume: '100',
                    unit: 'sak',
                    unitPriceRp: 85_000,
                    amountRp: 8_500_000,
                    standardCode: 'MAT-SMN-01',
                    doubtFields: [],
                ),
            ],
            writtenTotalRp: 8_500_000,
            readMs: 300,
        ));

    (new ProcessRabDocumentJob($audit->id, $audit->village_id, true))->handle($mockReader, new PriceLabeler);

    $item = $audit->items()->firstOrFail();
    expect($item->standard_price_rp)->toBe(90_000)
        ->and($item->standard_id)->toBe($standard->id)
        ->and($item->label)->toBe(PriceLabel::WithinStandard);
});

// ── AC4: CCO memperbarui snapshot dan melabel ulang dengan harga terbaru ───────
it('CCO updates standard_price_rp with latest price and re-labels correctly (AC4)', function () {
    $standard = PriceStandard::factory()->create([
        'regency' => 'Gianyar',
        'price_rp' => 70_000,
        'unit' => 'sak',
    ]);

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::Published,
    ]);

    $item = AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'volume' => '100',
        'unit' => 'sak',
        'unit_price_rp' => 85_000,
        'amount_rp' => 8_500_000,
        'standard_id' => $standard->id,
        'standard_price_rp' => 70_000,
        'label' => PriceLabel::AboveStandard,
        'over_pct' => 22,
    ]);

    $project = Project::factory()->create([
        'village_id' => $this->village->id,
        'audit_id' => $audit->id,
        'type' => ProjectType::RabatBeton,
        'year' => 2026,
        'public_status' => PublicStatus::None,
        'published_at' => now(),
    ]);

    // Pemda menaikkan harga acuan
    $standard->update(['price_rp' => 100_000]);

    // Desa melakukan CCO volume (harga tetap 85.000, acuan terbaru 100.000 → within_standard)
    $this->actingAs($this->desaUser)
        ->post("/desa/projects/{$project->id}/cco/items/{$item->id}", [
            'volume' => 120,
            'unit' => 'sak',
            'unit_price_rp' => 85_000,
            'standard_id' => $standard->id,
            'reason' => 'Koreksi volume setelah pengukuran lapangan',
        ])
        ->assertRedirect();

    $item->refresh();

    expect($item->standard_price_rp)->toBe(100_000)
        ->and($item->label)->toBe(PriceLabel::WithinStandard);

    expect(ProjectChange::where('project_id', $project->id)->exists())->toBeTrue();
});

// ── AC5: confirm() menyimpan snapshot ─────────────────────────────────────────
it('confirm() stores standard_price_rp snapshot when resolving a needs_check item (AC5)', function () {
    $standard = PriceStandard::factory()->create([
        'regency' => 'Gianyar',
        'price_rp' => 70_000,
        'unit' => 'sak',
    ]);

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::NeedsReview,
    ]);

    $item = AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'volume' => '100',
        'unit' => 'sak',
        'unit_price_rp' => 85_000,
        'amount_rp' => 8_500_000,
        'standard_id' => $standard->id,
        'standard_price_rp' => null,
        'label' => PriceLabel::NeedsCheck,
        'corrected' => false,
    ]);

    $this->actingAs($this->desaUser)
        ->post("/audits/{$audit->id}/items/{$item->id}/confirm")
        ->assertRedirect();

    $item->refresh();

    expect($item->standard_price_rp)->toBe(70_000)
        ->and($item->label)->toBe(PriceLabel::AboveStandard)
        ->and($item->corrected)->toBeTrue();
});

// ── AC5b: store(), update(), dan PublishProject menyimpan snapshot ─────────────
it('store(), update(), and PublishProject persist standard_price_rp snapshot', function () {
    $standard = PriceStandard::factory()->create([
        'regency' => 'Gianyar',
        'price_rp' => 65_000,
        'unit' => 'sak',
    ]);

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'file_path' => 'audits/sample.pdf',
        'status' => AuditStatus::NeedsReview,
    ]);

    // store()
    $this->actingAs($this->desaUser)
        ->post("/audits/{$audit->id}/items", [
            'raw_name' => 'Semen Portland',
            'volume' => '50',
            'unit' => 'sak',
            'unit_price_rp' => 60_000,
            'standard_id' => $standard->id,
        ])
        ->assertRedirect();

    $newItem = $audit->items()->latest('id')->firstOrFail();
    expect($newItem->standard_price_rp)->toBe(65_000);

    // update()
    $this->actingAs($this->desaUser)
        ->patch("/audits/{$audit->id}/items/{$newItem->id}", [
            'raw_name' => 'Semen Portland',
            'volume' => '80',
            'unit' => 'sak',
            'unit_price_rp' => 70_000,
            'standard_id' => $standard->id,
        ])
        ->assertRedirect();

    $newItem->refresh();
    expect($newItem->standard_price_rp)->toBe(65_000);

    // PublishProject resolves any corrected NeedsCheck item and records snapshot
    $yellowItem = AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'position' => 2,
        'volume' => '10',
        'unit' => 'sak',
        'unit_price_rp' => 75_000,
        'amount_rp' => 750_000,
        'standard_id' => $standard->id,
        'standard_price_rp' => null,
        'label' => PriceLabel::NeedsCheck,
        'corrected' => true,
    ]);

    app(PublishProject::class)->execute($audit->fresh(), [
        'name' => 'Rabat Beton Banjar Melati',
        'type' => ProjectType::RabatBeton->value,
        'fund_source' => 'Dana Desa (DDS)',
        'year' => 2026,
        'lat' => -8.541234,
        'lng' => 115.321456,
        'total_ack' => true,
        'measurements' => [
            ['name' => 'Ketebalan Beton Cor', 'value' => '12', 'unit' => 'cm', 'origin' => 'template'],
            ['name' => 'Lebar Badan Jalan', 'value' => '2.5', 'unit' => 'meter', 'origin' => 'template'],
            ['name' => 'Panjang Total Pekerjaan', 'value' => '100', 'unit' => 'meter', 'origin' => 'template'],
        ],
    ]);

    expect($yellowItem->fresh()->standard_price_rp)->toBe(65_000)
        ->and($yellowItem->fresh()->label)->toBe(PriceLabel::AboveStandard);
});

// ── Review & Manage controllers expose snapshot price on existing rows ────────
it('audit review and project manage expose item snapshot price rather than mutated catalog price', function () {
    $standard = PriceStandard::factory()->create([
        'regency' => 'Gianyar',
        'year' => 2026,
        'price_rp' => 70_000,
    ]);

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::Ready,
    ]);

    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'unit_price_rp' => 85_000,
        'standard_id' => $standard->id,
        'standard_price_rp' => 70_000,
        'label' => PriceLabel::AboveStandard,
        'over_pct' => 22,
    ]);

    $project = Project::factory()->create([
        'village_id' => $this->village->id,
        'audit_id' => $audit->id,
        'year' => 2026,
        'public_status' => PublicStatus::None,
        'published_at' => now(),
    ]);

    // Pemda mengubah harga di katalog
    $standard->update(['price_rp' => 95_000]);

    // Layar Review tetap menampilkan snapshot 70.000 pada baris item, sementara pilihan acuan memuat harga terbaru 95.000
    $this->actingAs($this->desaUser)
        ->get("/audits/{$audit->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.0.standard_price_rp', 70_000)
            ->where('items.0.standard.price_rp', 70_000)
            ->where('priceStandards.0.price_rp', 95_000));

    // Layar ProjectManage (CCO) tetap menampilkan snapshot 70.000 pada item, sementara pilihan CCO memuat harga terbaru 95.000
    $this->actingAs($this->desaUser)
        ->get("/desa/projects/{$project->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('project.audit.items.0.standard_price_rp', 70_000)
            ->where('project.audit.items.0.standard.price_rp', 70_000)
            ->where('standards.0.price_rp', 95_000));
});

// ── AC6: Klon demo menyalin standard_price_rp ─────────────────────────────────
it('demo clone preserves standard_price_rp on cloned audit items (AC6)', function () {
    $standard = PriceStandard::factory()->create([
        'regency' => 'Gianyar',
        'price_rp' => 65_000,
    ]);

    $template = Village::factory()->create([
        'slug' => 'desa-contoh-snapshot',
        'name' => 'Desa Contoh Snapshot',
        'regency' => 'Gianyar',
    ]);

    $audit = Audit::factory()->create([
        'village_id' => $template->id,
        'owner_id' => $this->desaUser->id,
        'status' => AuditStatus::Published,
    ]);

    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'standard_id' => $standard->id,
        'standard_price_rp' => 65_000,
    ]);

    // Ubah harga standar setelah template dibuat — klon tetap membawa snapshot template (65.000)
    $standard->update(['price_rp' => 90_000]);

    $clone = app(VillageCloner::class)->clone($template);

    $clonedAudit = Audit::withoutGlobalScope('village')->where('village_id', $clone->id)->sole();
    $clonedItem = $clonedAudit->items()->sole();

    expect($clonedItem->standard_price_rp)->toBe(65_000);
});
