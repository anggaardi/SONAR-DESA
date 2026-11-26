<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Models\Audit;
use App\Modules\Audit\Models\AuditItem;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Enums\MeasurementOrigin;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Report\Models\Report;
use App\Modules\Report\Models\ReportAnswer;
use App\Modules\Report\Models\ReportPhoto;
use App\Modules\Review\Enums\ReviewOutcome;
use App\Modules\Review\Models\ReviewCycle;
use App\Modules\Review\Models\ReviewNote;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Support\Facades\Storage;
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

it('exposes published citizen reports and BPD review notes only when project is reviewed by BPD (B1-B10)', function () {
    $village = Village::factory()->create();
    $audit = Audit::factory()->create(['village_id' => $village->id]);

    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
        'slug' => 'posyandu-kaja',
        'public_status' => PublicStatus::Reviewed,
        'published_at' => now(),
    ]);

    $cycle = ReviewCycle::query()->create([
        'project_id' => $project->id,
        'opened_at' => now()->subDays(10),
        'in_review_since' => now()->subDays(8),
        'closed_at' => now()->subDays(1),
        'result' => ReviewOutcome::NoMismatch,
        'note' => 'Pemeriksaan fisik sesuai spesifikasi RAB.',
    ]);

    ReviewNote::query()->create([
        'cycle_id' => $cycle->id,
        'note' => 'Catatan susulan BPD memverifikasi dimensi fisik bersama perwakilan warga.',
    ]);

    $project->update(['current_cycle_id' => $cycle->id]);

    $measurement = ProjectMeasurement::factory()->create([
        'project_id' => $project->id,
        'name' => 'Luas bangunan',
        'value' => '48.000',
        'unit' => 'm2',
    ]);

    $citizen = User::factory()->create([
        'role' => Role::Warga,
        'email' => 'citizen_secret@example.com',
        'village_id' => null,
    ]);

    $report = Report::factory()->create([
        'project_id' => $project->id,
        'cycle_id' => $cycle->id,
        'user_id' => $citizen->id,
        'kind' => ReportKind::Match,
        'note' => 'Dimensi bangunan dan lantai keramik rapi sesuai rencana.',
        'lat' => -8.5430000,
        'lng' => 115.3230000,
        'invalid_reason' => null,
    ]);

    ReportAnswer::query()->create([
        'report_id' => $report->id,
        'measurement_id' => $measurement->id,
        'answer' => 'match',
        'measured_value' => '48.000',
        'measured_unit' => 'm2',
    ]);

    $response = $this->get('/p/posyandu-kaja')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('project.public_status', 'reviewed')
            ->where('project.review.outcome', 'no_mismatch')
            ->where('project.review.note', 'Pemeriksaan fisik sesuai spesifikasi RAB.')
            ->has('project.review.notes', 1)
            ->where('project.review.notes.0.note', 'Catatan susulan BPD memverifikasi dimensi fisik bersama perwakilan warga.')
            ->has('project.published_reports', 1)
            ->where('project.published_reports.0.reporter_label', 'Warga Pelapor 1')
            ->where('project.published_reports.0.kind', 'match')
            ->where('project.published_reports.0.note', 'Dimensi bangunan dan lantai keramik rapi sesuai rencana.')
            ->has('project.published_reports.0.answers', 1)
            ->where('project.published_reports.0.answers.0.measurement_name', 'Luas bangunan')
            ->where('project.published_report_counts', ['mismatch' => 0, 'match' => 1, 'unmeasurable' => 0]));

    // Check privacy: citizen email, raw coordinates must NOT be in the HTML content
    $content = $response->getContent();
    expect($content)->not->toContain('citizen_secret@example.com');
    expect($content)->not->toContain('-8.5430000');
    expect($content)->not->toContain('115.3230000');
});

it('serves published report photos only for closed cycles on reviewed projects and hides invalid/unreviewed photos (C11)', function () {
    Storage::fake('local');
    Storage::disk('local')->put('reports/reviewed-ok.webp', 'valid-webp');
    Storage::disk('local')->put('reports/invalid-report.webp', 'invalid-webp');
    Storage::disk('local')->put('reports/in-review.webp', 'in-review-webp');

    $village = Village::factory()->create();
    $audit = Audit::factory()->create(['village_id' => $village->id]);

    $reviewedProject = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
        'slug' => 'posyandu-reviewed-photo',
        'public_status' => PublicStatus::Reviewed,
        'published_at' => now(),
    ]);

    $closedCycle = ReviewCycle::query()->create([
        'project_id' => $reviewedProject->id,
        'opened_at' => now()->subDays(5),
        'in_review_since' => now()->subDays(4),
        'closed_at' => now()->subDay(),
        'result' => ReviewOutcome::NoMismatch,
        'note' => 'Sudah diperiksa BPD dan sesuai spesifikasi rencana.',
    ]);
    $openCycle = ReviewCycle::query()->create([
        'project_id' => $reviewedProject->id,
        'opened_at' => now(),
    ]);
    $reviewedProject->update(['current_cycle_id' => $openCycle->id]);

    $validReport = Report::factory()->create([
        'project_id' => $reviewedProject->id,
        'cycle_id' => $closedCycle->id,
        'kind' => ReportKind::Match,
    ]);
    $validPhoto = ReportPhoto::factory()->create([
        'report_id' => $validReport->id,
        'position' => 1,
        'path' => 'reports/reviewed-ok.webp',
    ]);

    $invalidReport = Report::factory()->create([
        'project_id' => $reviewedProject->id,
        'cycle_id' => $closedCycle->id,
        'invalid_reason' => 'Foto tidak relevan',
    ]);
    $invalidPhoto = ReportPhoto::factory()->create([
        'report_id' => $invalidReport->id,
        'position' => 1,
        'path' => 'reports/invalid-report.webp',
    ]);

    $unreviewedReport = Report::factory()->create([
        'project_id' => $reviewedProject->id,
        'cycle_id' => $openCycle->id,
    ]);
    $unreviewedPhoto = ReportPhoto::factory()->create([
        'report_id' => $unreviewedReport->id,
        'position' => 1,
        'path' => 'reports/in-review.webp',
    ]);

    $this->get('/p/posyandu-reviewed-photo')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('project.published_reports', 1)
            ->has('project.published_reports.0.photos', 1)
            ->where('project.published_reports.0.photos.0.id', $validPhoto->id)
            ->where('project.published_reports.0.photos.0.url', route('media.report-photo', $validPhoto->id)));

    $this->get(route('media.report-photo', $validPhoto->id))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');

    $this->get(route('media.report-photo', $invalidPhoto->id))->assertNotFound();
    $this->get(route('media.report-photo', $unreviewedPhoto->id))->assertNotFound();
});
