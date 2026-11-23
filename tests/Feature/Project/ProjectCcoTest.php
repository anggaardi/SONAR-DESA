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
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectChange;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    $this->village = Village::factory()->create(['regency' => 'Gianyar']);
    app(CurrentVillage::class)->set($this->village->id);

    $this->desaUser = User::factory()->create([
        'role' => Role::Desa,
        'village_id' => $this->village->id,
    ]);

    $this->otherVillage = Village::factory()->create(['regency' => 'Badung']);
    $this->otherDesaUser = User::factory()->create([
        'role' => Role::Desa,
        'village_id' => $this->otherVillage->id,
    ]);

    $this->audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'file_path' => 'audits/rab_jalan.pdf',
        'status' => AuditStatus::Ready,
        'written_total_rp' => 50000000,
    ]);

    $this->project = Project::factory()->create([
        'village_id' => $this->village->id,
        'audit_id' => $this->audit->id,
        'name' => 'Rabat Beton Dusun Kangin',
        'type' => ProjectType::RabatBeton,
        'fund_source' => 'Dana Desa 2026',
        'year' => 2026,
        'lat' => -8.541234,
        'lng' => 115.321456,
        'progress_pct' => 40,
        'public_status' => PublicStatus::None,
        'published_at' => now(),
    ]);

    $this->standard = PriceStandard::factory()->create([
        'regency' => 'Gianyar',
        'name' => 'Semen Portland 50kg',
        'code' => 'SMN-50',
        'unit' => 'sak',
        'price_rp' => 65000,
    ]);

    $this->auditItem = AuditItem::factory()->create([
        'audit_id' => $this->audit->id,
        'raw_name' => 'Semen Portland Gresik 50kg',
        'volume' => 200,
        'unit' => 'sak',
        'unit_price_rp' => 65000,
        'amount_rp' => 13000000,
        'standard_id' => $this->standard->id,
        'label' => PriceLabel::WithinStandard,
    ]);

    $this->measurement = ProjectMeasurement::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'Tebal Beton',
        'unit' => 'cm',
        'value' => 12,
        'how_to' => 'Ukur dengan mistar ukur',
    ]);
});

it('redirects guests to login when accessing project management and CCO endpoints', function () {
    $this->get("/desa/projects/{$this->project->id}")
        ->assertRedirect(route('login'));

    $this->post("/desa/projects/{$this->project->id}/cco/items/{$this->auditItem->id}", [])
        ->assertRedirect(route('login'));

    $this->post("/desa/projects/{$this->project->id}/cco/measurements/{$this->measurement->id}", [])
        ->assertRedirect(route('login'));

    $this->post("/desa/projects/{$this->project->id}/cco/gps", [])
        ->assertRedirect(route('login'));
});

it('prevents cross-tenant access to manage and CCO endpoints', function () {
    $this->actingAs($this->otherDesaUser)
        ->get("/desa/projects/{$this->project->id}")
        ->assertNotFound();

    $this->actingAs($this->otherDesaUser)
        ->post("/desa/projects/{$this->project->id}/cco/items/{$this->auditItem->id}", [
            'volume' => 250,
            'unit' => 'sak',
            'unit_price_rp' => 65000,
            'reason' => 'Perubahan ilegal beda desa',
        ])
        ->assertNotFound();
});

it('renders project manage page with correct props for authorized desa user', function () {
    $this->actingAs($this->desaUser)
        ->get("/desa/projects/{$this->project->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('desa/ProjectManage')
            ->has('project', fn (AssertableInertia $p) => $p
                ->where('id', $this->project->id)
                ->where('name', $this->project->name)
                ->where('progress_pct', 40)
                ->where('is_published', true)
                ->has('audit.items', 1)
                ->has('measurements', 1)
                ->etc()
            )
            ->has('standards')
        );
});

it('applies CCO item update, recalculates PriceLabeler, updates written total, and records change (D13, D14)', function () {
    $this->actingAs($this->desaUser)
        ->post("/desa/projects/{$this->project->id}/cco/items/{$this->auditItem->id}", [
            'volume' => 300,
            'unit' => 'sak',
            'unit_price_rp' => 80000,
            'standard_id' => $this->standard->id,
            'reason' => 'Penambahan volume karena pelebaran bahu jalan',
        ])
        ->assertRedirect();

    $this->auditItem->refresh();
    expect((float) $this->auditItem->volume)->toBe(300.0)
        ->and($this->auditItem->unit_price_rp)->toBe(80000)
        ->and($this->auditItem->amount_rp)->toBe(24000000)
        ->and($this->auditItem->label)->toBe(PriceLabel::AboveStandard);

    $this->audit->refresh();
    expect($this->audit->written_total_rp)->toBe(50000000);

    // Verify project_changes audit trail
    $changes = ProjectChange::where('project_id', $this->project->id)->get();
    expect($changes->count())->toBeGreaterThanOrEqual(2);

    $volChange = $changes->firstWhere('field', 'Volume Item');
    expect($volChange)->not->toBeNull()
        ->and($volChange->old_value)->toContain('200')
        ->and($volChange->new_value)->toBe('300 sak')
        ->and($volChange->reason)->toBe('Penambahan volume karena pelebaran bahu jalan');

    $priceChange = $changes->firstWhere('field', 'Harga Satuan');
    expect($priceChange)->not->toBeNull()
        ->and($priceChange->old_value)->toContain('65.000')
        ->and($priceChange->new_value)->toContain('80.000')
        ->and($priceChange->reason)->toBe('Penambahan volume karena pelebaran bahu jalan');
});

it('applies CCO measurement update and records change in project_changes (D13)', function () {
    $this->actingAs($this->desaUser)
        ->post("/desa/projects/{$this->project->id}/cco/measurements/{$this->measurement->id}", [
            'value' => 15,
            'how_to' => 'Ukur dengan jangka sorong digital',
            'reason' => 'Peningkatan spek ketebalan dari 12cm menjadi 15cm',
        ])
        ->assertRedirect();

    $this->measurement->refresh();
    expect((float) $this->measurement->value)->toBe(15.0)
        ->and($this->measurement->how_to)->toBe('Ukur dengan jangka sorong digital');

    $change = ProjectChange::where('project_id', $this->project->id)
        ->where('field', 'Target Ukuran')
        ->first();

    expect($change)->not->toBeNull()
        ->and($change->old_value)->toBe('12 cm')
        ->and($change->new_value)->toBe('15 cm')
        ->and($change->reason)->toBe('Peningkatan spek ketebalan dari 12cm menjadi 15cm');
});

it('applies CCO GPS coordinates update and records change in project_changes (D13)', function () {
    $this->actingAs($this->desaUser)
        ->post("/desa/projects/{$this->project->id}/cco/gps", [
            'lat' => -8.549999,
            'lng' => 115.329999,
            'reason' => 'Koreksi titik geofence menyesuaikan tapak batas desa',
        ])
        ->assertRedirect();

    $this->project->refresh();
    expect((float) $this->project->lat)->toBe(-8.549999)
        ->and((float) $this->project->lng)->toBe(115.329999);

    $change = ProjectChange::where('project_id', $this->project->id)
        ->where('field', 'Titik Lokasi GPS')
        ->first();

    expect($change)->not->toBeNull()
        ->and($change->new_value)->toContain('-8.549999')
        ->and($change->reason)->toBe('Koreksi titik geofence menyesuaikan tapak batas desa');
});

it('prevents deleting a published project (D10)', function () {
    expect($this->project->isPublished())->toBeTrue();

    $this->actingAs($this->desaUser)
        ->delete("/desa/projects/{$this->project->id}")
        ->assertForbidden();

    expect(Project::find($this->project->id))->not->toBeNull();
});

it('allows deleting a draft project (D10)', function () {
    $draftProject = Project::factory()->create([
        'village_id' => $this->village->id,
        'published_at' => null,
    ]);

    expect($draftProject->isPublished())->toBeFalse();

    $this->actingAs($this->desaUser)
        ->delete("/desa/projects/{$draftProject->id}")
        ->assertRedirect(route('desa.dashboard'));

    expect(Project::find($draftProject->id))->toBeNull();
});

it('prevents deleting measurements of a published project (D11)', function () {
    expect($this->project->isPublished())->toBeTrue();

    $this->actingAs($this->desaUser)
        ->delete("/desa/projects/{$this->project->id}/measurements/{$this->measurement->id}")
        ->assertForbidden();

    expect(ProjectMeasurement::find($this->measurement->id))->not->toBeNull();
});

it('allows deleting measurements of a draft project (D11)', function () {
    $draftProject = Project::factory()->create([
        'village_id' => $this->village->id,
        'published_at' => null,
    ]);
    $draftMeasurement = ProjectMeasurement::factory()->create([
        'project_id' => $draftProject->id,
        'name' => 'Panjang',
        'value' => 100,
        'unit' => 'meter',
    ]);

    $this->actingAs($this->desaUser)
        ->delete("/desa/projects/{$draftProject->id}/measurements/{$draftMeasurement->id}")
        ->assertRedirect();

    expect(ProjectMeasurement::find($draftMeasurement->id))->toBeNull();
});

it('freezes CCO on items, measurements, and GPS during in_review but allows progress updates (D12)', function () {
    $this->project->update(['public_status' => PublicStatus::InReview]);

    // CCO item is forbidden
    $this->actingAs($this->desaUser)
        ->post("/desa/projects/{$this->project->id}/cco/items/{$this->auditItem->id}", [
            'volume' => 300,
            'unit' => 'sak',
            'unit_price_rp' => 65000,
            'reason' => 'Perubahan saat freeze',
        ])
        ->assertForbidden();

    // CCO measurement is forbidden
    $this->actingAs($this->desaUser)
        ->post("/desa/projects/{$this->project->id}/cco/measurements/{$this->measurement->id}", [
            'value' => 20,
            'reason' => 'Perubahan spek saat freeze',
        ])
        ->assertForbidden();

    // CCO GPS is forbidden
    $this->actingAs($this->desaUser)
        ->post("/desa/projects/{$this->project->id}/cco/gps", [
            'lat' => -8.549999,
            'lng' => 115.329999,
            'reason' => 'Perubahan GPS saat freeze',
        ])
        ->assertForbidden();

    // Progress photo & % update is still allowed
    $photo = UploadedFile::fake()->image('progres_lapangan.jpg');

    $this->actingAs($this->desaUser)
        ->post("/projects/{$this->project->id}/progress", [
            'progress_pct' => 60,
            'photos' => [$photo],
        ])
        ->assertRedirect();

    $this->project->refresh();
    expect($this->project->progress_pct)->toBe(60)
        ->and($this->project->progressPhotos()->count())->toBe(1);
});

it('requires a reason when decreasing progress percentage and records it in project_changes (D13)', function () {
    expect($this->project->progress_pct)->toBe(40);

    // Decrease from 40% to 25% without reason -> validation fails
    $photo = UploadedFile::fake()->image('foto1.jpg');
    $this->actingAs($this->desaUser)
        ->post("/projects/{$this->project->id}/progress", [
            'progress_pct' => 25,
            'photos' => [$photo],
        ])
        ->assertSessionHasErrors(['decrease_reason']);

    // Decrease with reason -> succeeds
    $photo2 = UploadedFile::fake()->image('foto2.jpg');
    $this->actingAs($this->desaUser)
        ->post("/projects/{$this->project->id}/progress", [
            'progress_pct' => 25,
            'photos' => [$photo2],
            'decrease_reason' => 'Koreksi hitungan konsultan pengawas',
        ])
        ->assertRedirect();

    $this->project->refresh();
    expect($this->project->progress_pct)->toBe(25);

    $change = ProjectChange::where('project_id', $this->project->id)
        ->where('field', 'Progres Fisik')
        ->first();

    expect($change)->not->toBeNull()
        ->and($change->old_value)->toBe('40%')
        ->and($change->new_value)->toBe('25%')
        ->and($change->reason)->toBe('Koreksi hitungan konsultan pengawas');
});
