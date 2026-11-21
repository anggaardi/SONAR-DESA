<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Models\Audit;
use App\Modules\Audit\Models\AuditItem;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Enums\MeasurementOrigin;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Report\Models\Report;
use App\Modules\Standard\Models\PriceStandard;
use Inertia\Testing\AssertableInertia;

it('renders the public project page without authentication (P1)', function () {
    $village = Village::factory()->create(['name' => 'Desa Melati', 'regency' => 'Gianyar']);
    $audit = Audit::factory()->create(['village_id' => $village->id]);

    $standard = PriceStandard::factory()->create([
        'year' => 2026,
        'price_rp' => 65_000,
        'source_ref' => 'Perbup Gianyar No. 15 Tahun 2026',
    ]);

    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'raw_name' => 'Semen 50 kg',
        'unit_price_rp' => 85_000,
        'amount_rp' => 32_300_000,
        'standard_id' => $standard->id,
        'label' => PriceLabel::AboveStandard,
        'over_pct' => 31,
        'diff_rp' => 20_000,
    ]);

    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
        'slug' => 'rabat-beton-melati',
        'name' => 'Rabat beton Jl. Melati',
        'published_at' => now(),
    ]);

    ProjectMeasurement::factory()->create([
        'project_id' => $project->id,
        'name' => 'Tebal beton',
        'value' => '12.000',
        'unit' => 'cm',
        'origin' => MeasurementOrigin::Template,
    ]);

    $this->get('/p/rabat-beton-melati')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('project/Show')
            ->where('project.slug', 'rabat-beton-melati')
            ->where('project.name', 'Rabat beton Jl. Melati')
            ->where('project.village.name', 'Desa Melati')
            ->has('project.items', 1)
            ->where('project.items.0.label', 'above_standard')
            ->where('project.items.0.standard_price_rp', 65_000)
            ->where('project.items.0.unit_diff_rp', 20_000)
            ->has('project.measurements', 1)
            ->where('project.measurements.0.name', 'Tebal beton')
            ->where('project.measurements.0.value', '12'));
});

it('returns friendly not found page when project is draft or does not exist (P10)', function () {
    $this->get('/p/nonexistent-project-slug')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('project/NotFound')
            ->where('message', 'Proyek belum diterbitkan atau tidak ditemukan'));

    $village = Village::factory()->create();
    $audit = Audit::factory()->create(['village_id' => $village->id]);
    $project = Project::factory()->unpublished()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
        'slug' => 'draft-project',
    ]);

    $this->get('/p/draft-project')
        ->assertNotFound()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('project/NotFound')
            ->where('message', 'Proyek belum diterbitkan atau tidak ditemukan'));
});

it('does not leak citizen personal data in props (F3-2, R-05)', function () {
    $village = Village::factory()->create();
    $audit = Audit::factory()->create(['village_id' => $village->id]);
    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
        'slug' => 'test-privacy',
        'published_at' => now(),
    ]);

    $citizen = User::factory()->create([
        'role' => Role::Warga,
        'email' => 'warga_rahasia@example.com',
        'village_id' => null,
    ]);

    Report::factory()->create([
        'project_id' => $project->id,
        'user_id' => $citizen->id,
        'note' => 'Catatan privat warga',
        'lat' => -8.5340000,
        'lng' => 115.3210000,
        'invalid_reason' => null,
    ]);

    $response = $this->get('/p/test-privacy')->assertOk();

    $content = $response->getContent();
    expect($content)->not->toContain('warga_rahasia@example.com');
    expect($content)->not->toContain('-8.5340000');
    expect($content)->not->toContain('Catatan privat warga');
});

it('displays citizen count only when in-radius valid reports exist (P7)', function () {
    $village = Village::factory()->create();
    $audit = Audit::factory()->create(['village_id' => $village->id]);
    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
        'slug' => 'test-citizen-count',
        'published_at' => now(),
    ]);

    // 0 reports: has_citizen_reports is false
    $this->get('/p/test-citizen-count')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('project.citizen_reports_count', 0)
            ->where('project.has_citizen_reports', false));

    // Add 4 distinct citizens reporting within radius
    for ($i = 0; $i < 4; $i++) {
        $citizen = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);
        Report::factory()->create([
            'project_id' => $project->id,
            'user_id' => $citizen->id,
            'invalid_reason' => null,
        ]);
    }

    // Add 1 invalid report. Out of radius is never saved (D-009).
    $invalidCitizen = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);
    Report::factory()->create([
        'project_id' => $project->id,
        'user_id' => $invalidCitizen->id,
        'invalid_reason' => 'foto tidak relevan',
    ]);

    $this->get('/p/test-citizen-count')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('project.citizen_reports_count', 4)
            ->where('project.has_citizen_reports', true));
});

it('formats neutral explanation text according to PRD 4.3 (P3)', function () {
    $village = Village::factory()->create();
    $audit = Audit::factory()->create(['village_id' => $village->id]);
    $standard = PriceStandard::factory()->create(['year' => 2026]);

    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'standard_id' => $standard->id,
        'label' => PriceLabel::AboveStandard,
        'over_pct' => 25,
    ]);

    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'standard_id' => null,
        'label' => PriceLabel::NoReference,
    ]);

    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
        'slug' => 'test-neutral-text',
        'year' => 2026,
        'published_at' => now(),
    ]);

    $this->get('/p/test-neutral-text')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('project.items.0.explanation', 'Harga di RAB 25% di atas standar harga Kabupaten Gianyar 2026. Belum tentu salah: BPD bisa meminta penjelasan desa.')
            ->where('project.items.1.explanation', 'Item ini tidak ada di daftar standar Gianyar 2026, jadi tidak dinilai'));
});
