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
use App\Modules\Project\Enums\ProjectType;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');

    $this->village = Village::factory()->create();
    app(CurrentVillage::class)->set($this->village->id);

    $this->desaUser = User::factory()->create([
        'role' => Role::Desa,
        'village_id' => $this->village->id,
    ]);
});

it('redirects guests to login when accessing audit review endpoints', function () {
    $audit = Audit::factory()->create(['village_id' => $this->village->id]);
    $item = AuditItem::factory()->create(['audit_id' => $audit->id]);

    $this->get("/audits/{$audit->id}")
        ->assertRedirect(route('login'));

    $this->get("/audits/{$audit->id}/document")
        ->assertRedirect(route('login'));

    $this->post("/audits/{$audit->id}/items/{$item->id}/confirm")
        ->assertRedirect(route('login'));

    $this->patch("/audits/{$audit->id}/items/{$item->id}", [])
        ->assertRedirect(route('login'));

    $this->delete("/audits/{$audit->id}/items/{$item->id}")
        ->assertRedirect(route('login'));

    $this->post("/audits/{$audit->id}/publish", [])
        ->assertRedirect(route('login'));
});

it('forbids warga and bpd from accessing audit review endpoints', function (Role $role) {
    $user = User::factory()->create([
        'role' => $role,
        'village_id' => $role->belongsToVillage() ? $this->village->id : null,
    ]);

    $audit = Audit::factory()->create(['village_id' => $this->village->id]);
    $item = AuditItem::factory()->create(['audit_id' => $audit->id]);

    $this->actingAs($user)
        ->get("/audits/{$audit->id}")
        ->assertForbidden();

    $this->actingAs($user)
        ->get("/audits/{$audit->id}/document")
        ->assertForbidden();

    $this->actingAs($user)
        ->post("/audits/{$audit->id}/items/{$item->id}/confirm")
        ->assertForbidden();

    $this->actingAs($user)
        ->patch("/audits/{$audit->id}/items/{$item->id}", [
            'raw_name' => 'Pasir Pasang',
            'volume' => '10',
            'unit' => 'm3',
            'unit_price_rp' => 200000,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete("/audits/{$audit->id}/items/{$item->id}")
        ->assertForbidden();

    $this->actingAs($user)
        ->post("/audits/{$audit->id}/publish", [])
        ->assertForbidden();
})->with([Role::Warga, Role::Bpd]);

it('forbids or isolates desa users from accessing an audit of another village', function () {
    $otherVillage = Village::factory()->create();
    $otherAudit = Audit::factory()->create(['village_id' => $otherVillage->id]);
    $otherItem = AuditItem::factory()->create(['audit_id' => $otherAudit->id]);

    $this->actingAs($this->desaUser)
        ->get("/audits/{$otherAudit->id}")
        ->assertNotFound();

    $this->actingAs($this->desaUser)
        ->get("/audits/{$otherAudit->id}/document")
        ->assertNotFound();

    $this->actingAs($this->desaUser)
        ->post("/audits/{$otherAudit->id}/items/{$otherItem->id}/confirm")
        ->assertNotFound();
});

it('renders the dual-view review screen with items, total checker, and can_publish flag', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::NeedsReview,
        'file_path' => 'audits/rab-test.pdf',
        'written_total_rp' => 100_000_000,
    ]);

    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'label' => PriceLabel::NeedsCheck,
        'corrected' => false,
        'amount_rp' => 100_000_000,
    ]);

    $this->actingAs($this->desaUser)
        ->get("/audits/{$audit->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('audit/Review')
            ->has('audit.id')
            ->where('summary.needs_check_count', 1)
            ->where('summary.can_publish', false)
            ->has('items', 1)
            ->has('priceStandards')
            ->has('types')
            ->has('templates')
            ->has('fundSources')
        );
});

it('serves the physical document file via document endpoint', function () {
    $fakePdfContent = '%PDF-1.4 Fake document content';
    Storage::disk('local')->put('audits/dokumen-berstempel.pdf', $fakePdfContent);

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'file_path' => 'audits/dokumen-berstempel.pdf',
    ]);

    $response = $this->actingAs($this->desaUser)
        ->get("/audits/{$audit->id}/document");

    $response->assertOk();
    expect($response->streamedContent())->toBe($fakePdfContent);
});

it('confirms a yellow row with "Sudah Benar" action and transitions audit to ready when 0 yellow rows remain', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::NeedsReview,
    ]);

    $yellowItem1 = AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'label' => PriceLabel::NeedsCheck,
        'corrected' => false,
    ]);

    $yellowItem2 = AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'label' => PriceLabel::NeedsCheck,
        'corrected' => false,
    ]);

    // Confirm 1st yellow item
    $this->actingAs($this->desaUser)
        ->post("/audits/{$audit->id}/items/{$yellowItem1->id}/confirm")
        ->assertRedirect();

    expect($yellowItem1->fresh()->corrected)->toBeTrue();
    // Audit still has yellowItem2 uncorrected
    expect($audit->fresh()->status)->toBe(AuditStatus::NeedsReview);

    // Confirm 2nd yellow item
    $this->actingAs($this->desaUser)
        ->post("/audits/{$audit->id}/items/{$yellowItem2->id}/confirm")
        ->assertRedirect();

    expect($yellowItem2->fresh()->corrected)->toBeTrue();
    // All yellow rows resolved -> status becomes Ready!
    expect($audit->fresh()->status)->toBe(AuditStatus::Ready);
});

it('allows inline editing of an audit item, recalculating labels and updating written total', function () {
    $standard = PriceStandard::factory()->create([
        'name' => 'Pasir Pasang Gianyar',
        'unit' => 'm3',
        'price_rp' => 250_000,
    ]);

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'written_total_rp' => 500_000,
        'status' => AuditStatus::NeedsReview,
    ]);

    $item = AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'raw_name' => 'Pasir',
        'volume' => '2',
        'unit' => 'm3',
        'unit_price_rp' => 250_000,
        'amount_rp' => 500_000,
        'standard_id' => null,
        'label' => PriceLabel::NoReference,
        'corrected' => false,
    ]);

    $this->actingAs($this->desaUser)
        ->patch("/audits/{$audit->id}/items/{$item->id}", [
            'raw_name' => 'Pasir Pasang Gianyar (Koreksi)',
            'volume' => '10',
            'unit' => 'm3',
            'unit_price_rp' => 240_000,
            'amount_rp' => 2_400_000,
            'standard_id' => $standard->id,
        ])
        ->assertRedirect();

    $item->refresh();
    expect($item->raw_name)->toBe('Pasir Pasang Gianyar (Koreksi)')
        ->and((float) $item->volume)->toBe(10.0)
        ->and($item->unit_price_rp)->toBe(240_000)
        ->and($item->amount_rp)->toBe(2_400_000)
        ->and($item->label)->toBe(PriceLabel::WithinStandard)
        ->and($item->corrected)->toBeTrue();

    // Written total from paper is preserved (F3 requirement)
    expect($audit->fresh()->written_total_rp)->toBe(500_000)
        ->and((int) $audit->fresh()->items()->sum('amount_rp'))->toBe(2_400_000);
});

it('allows deleting an audit item and updates audit total', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'written_total_rp' => 3_000_000,
        'status' => AuditStatus::NeedsReview,
    ]);

    $item1 = AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'amount_rp' => 1_000_000,
    ]);

    $item2 = AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'amount_rp' => 2_000_000,
    ]);

    $this->actingAs($this->desaUser)
        ->delete("/audits/{$audit->id}/items/{$item1->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('audit_items', ['id' => $item1->id]);
    // Written total from paper is preserved (F3 requirement)
    expect($audit->fresh()->written_total_rp)->toBe(3_000_000)
        ->and((int) $audit->fresh()->items()->sum('amount_rp'))->toBe(2_000_000);
});

it('blocks publishing when yellow rows exist (D1 gatekeeper)', function () {
    Storage::disk('local')->put('audits/rab.pdf', 'dummy pdf');

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'file_path' => 'audits/rab.pdf',
        'status' => AuditStatus::NeedsReview,
    ]);

    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'label' => PriceLabel::NeedsCheck,
        'corrected' => false,
    ]);

    $payload = [
        'name' => 'Proyek Terkunci',
        'type' => ProjectType::RabatBeton->value,
        'fund_source' => 'Dana Desa (DDS)',
        'year' => 2026,
        'lat' => -8.4124,
        'lng' => 115.2482,
        'measurements' => [
            ['name' => 'Ketebalan Beton Cor', 'value' => 12, 'unit' => 'cm'],
            ['name' => 'Lebar Badan Jalan', 'value' => 2.5, 'unit' => 'meter'],
            ['name' => 'Panjang Total Pekerjaan', 'value' => 450, 'unit' => 'meter'],
        ],
    ];

    $response = $this->actingAs($this->desaUser)
        ->from("/audits/{$audit->id}")
        ->post("/audits/{$audit->id}/publish", $payload);

    $response->assertRedirect("/audits/{$audit->id}");
    $response->assertSessionHasErrors(['gatekeeper']);
});

it('publishes project successfully when all gatekeeper rules are met, setting published_at and returning published_project flash', function () {
    Storage::disk('local')->put('audits/rab.pdf', 'dummy pdf');

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'file_path' => 'audits/rab.pdf',
        'status' => AuditStatus::Ready,
        'written_total_rp' => 100_000_000,
    ]);

    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'label' => PriceLabel::WithinStandard,
        'corrected' => true,
        'amount_rp' => 100_000_000,
    ]);

    $payload = [
        'name' => 'Pembangunan Jalan Rabat Beton Banjar Melati',
        'type' => ProjectType::RabatBeton->value,
        'fund_source' => 'Dana Desa (DDS)',
        'year' => 2026,
        'lat' => -8.4124,
        'lng' => 115.2482,
        'measurements' => [
            ['name' => 'Ketebalan Beton Cor', 'value' => 12, 'unit' => 'cm', 'how_to' => 'Ukur di 3 titik'],
            ['name' => 'Lebar Badan Jalan', 'value' => 2.5, 'unit' => 'meter', 'how_to' => 'Rentangkan meteran'],
            ['name' => 'Panjang Total Pekerjaan', 'value' => 450, 'unit' => 'meter', 'how_to' => 'Ukur titik nol ke akhir'],
        ],
    ];

    $response = $this->actingAs($this->desaUser)
        ->post("/audits/{$audit->id}/publish", $payload);

    $response->assertRedirect(route('audits.show', $audit->id));
    $response->assertSessionHas('published_project');
    $response->assertSessionHas('success');

    $flashProject = session('published_project');
    expect($flashProject['name'])->toBe('Pembangunan Jalan Rabat Beton Banjar Melati')
        ->and($flashProject['qr_svg'])->toBeString()
        ->and($flashProject['public_url'])->toContain('/p/')
        ->and($flashProject['qr_poster_url'])->toContain('/projects/');

    $project = $audit->fresh()->project;
    expect($project)->not->toBeNull()
        ->and($project->isPublished())->toBeTrue()
        ->and($project->published_at)->not->toBeNull();
});
