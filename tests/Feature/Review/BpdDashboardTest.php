<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Models\Audit;
use App\Modules\Audit\Models\AuditItem;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectChange;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Report\Actions\SubmitReport;
use App\Modules\Report\Domain\ReportSubmission;
use App\Modules\Report\Domain\SubmittedAnswer;
use App\Modules\Report\Enums\MeasurementAnswer;
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Report\Events\ReportSubmitted;
use App\Modules\Report\Models\Report;
use App\Modules\Report\Models\ReportAnswer;
use App\Modules\Report\Models\ReportPhoto;
use App\Modules\Review\Actions\EvaluateReviewThreshold;
use App\Modules\Review\Enums\ReportVerdict;
use App\Modules\Review\Enums\ReviewOutcome;
use App\Modules\Review\Models\ReviewCycle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function setupBpdVillage(): array
{
    $village = Village::factory()->create();
    $bpd = User::factory()->create([
        'village_id' => $village->id,
        'role' => Role::Bpd,
    ]);
    app(CurrentVillage::class)->set($village->id);

    return [$village, $bpd];
}

/** projects.audit_id is unique: every project needs its own audit from the same village. */
function auditFor(Village $village): int
{
    return Audit::factory()->create(['village_id' => $village->id])->id;
}

it('orders InReview projects first on BPD dashboard and anonymizes reporters (B1, B2)', function () {
    [$village, $bpd] = setupBpdVillage();

    Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => auditFor($village),
        'public_status' => PublicStatus::None,
        'published_at' => now(),
    ]);

    $inReviewProject = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => auditFor($village),
        'public_status' => PublicStatus::InReview,
        'published_at' => now(),
    ]);

    $cycle = ReviewCycle::create([
        'project_id' => $inReviewProject->id,
        'opened_at' => now(),
        'in_review_since' => now(),
    ]);
    $inReviewProject->update(['current_cycle_id' => $cycle->id]);

    $warga1 = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);
    $warga2 = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);

    Report::factory()->create([
        'project_id' => $inReviewProject->id,
        'cycle_id' => $cycle->id,
        'user_id' => $warga1->id,
        'kind' => ReportKind::Mismatch,
    ]);
    Report::factory()->create([
        'project_id' => $inReviewProject->id,
        'cycle_id' => $cycle->id,
        'user_id' => $warga2->id,
        'kind' => ReportKind::Mismatch,
    ]);

    $this->actingAs($bpd)
        ->get('/bpd')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('review/Index')
            ->where('projects.0.id', $inReviewProject->id)
            ->where('selectedProject.reports.0.reporter_label', 'Pelapor A')
            ->where('selectedProject.reports.1.reporter_label', 'Pelapor B')
        );
});

it('transitions project to InReview on 3rd valid mismatch report and keeps InReview if invalidated (B3, B4, B5)', function () {
    [$village, $bpd] = setupBpdVillage();

    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => auditFor($village),
        'public_status' => PublicStatus::None,
        'published_at' => now(),
    ]);

    $cycle = ReviewCycle::create([
        'project_id' => $project->id,
        'opened_at' => now(),
    ]);
    $project->update(['current_cycle_id' => $cycle->id]);

    $reports = [];
    for ($i = 0; $i < 3; $i++) {
        $warga = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);
        $reports[] = Report::factory()->create([
            'project_id' => $project->id,
            'cycle_id' => $cycle->id,
            'user_id' => $warga->id,
            'kind' => ReportKind::Mismatch,
        ]);
    }

    $triggered = app(EvaluateReviewThreshold::class)->execute($project);
    expect($triggered)->toBeTrue();
    expect($project->refresh()->public_status)->toBe(PublicStatus::InReview);

    // B3 & B5: Marking one report invalid does NOT drop InReview status.
    $this->actingAs($bpd)
        ->post("/bpd/reports/{$reports[0]->id}/invalidate", ['reason' => 'Foto tidak relevan'])
        ->assertRedirect();

    app(CurrentVillage::class)->set($village->id);
    expect($reports[0]->refresh()->invalid_reason)->toBe('Foto tidak relevan')
        ->and($reports[0]->bpd_verdict)->toBe(ReportVerdict::Invalid);
    expect($project->refresh()->public_status)->toBe(PublicStatus::InReview);

    // The verdict is final: an invalid report cannot be validated or re-marked afterwards.
    $this->actingAs($bpd)
        ->post("/bpd/reports/{$reports[0]->id}/validate")
        ->assertSessionHasErrors('report');
    $this->actingAs($bpd)
        ->post("/bpd/reports/{$reports[0]->id}/invalidate", ['reason' => 'Alasan lain'])
        ->assertSessionHasErrors('reason');

    expect($reports[0]->refresh()->invalid_reason)->toBe('Foto tidak relevan')
        ->and($reports[0]->bpd_verdict)->toBe(ReportVerdict::Invalid);

    // Validating a pending report stores the verdict together with one optional note.
    $this->actingAs($bpd)
        ->post("/bpd/reports/{$reports[1]->id}/validate", ['note' => 'Sudah dicek bersama perangkat desa.'])
        ->assertRedirect();

    $reports[1]->refresh();
    expect($reports[1]->bpd_verdict)->toBe(ReportVerdict::Valid)
        ->and($reports[1]->bpd_reply)->toBe('Sudah dicek bersama perangkat desa.')
        ->and($reports[1]->opened_at)->not->toBeNull();

    // Invalidating a pending report with a note stores both too.
    $this->actingAs($bpd)
        ->post("/bpd/reports/{$reports[2]->id}/invalidate", ['reason' => 'Duplikat', 'note' => 'Laporan ganda dari lokasi yang sama.'])
        ->assertRedirect();

    $reports[2]->refresh();
    expect($reports[2]->bpd_verdict)->toBe(ReportVerdict::Invalid)
        ->and($reports[2]->bpd_reply)->toBe('Laporan ganda dari lokasi yang sama.');
});

it('rejects short notes (< 30 chars) and non-InReview outcome submissions, then closes cycle and supports follow-up notes (B6, B7, B8, B9)', function () {
    [$village, $bpd] = setupBpdVillage();

    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => auditFor($village),
        'public_status' => PublicStatus::None,
        'published_at' => now(),
    ]);

    $cycle = ReviewCycle::create([
        'project_id' => $project->id,
        'opened_at' => now(),
    ]);
    $project->update(['current_cycle_id' => $cycle->id]);

    // B6: Cannot submit outcome when not InReview.
    $this->actingAs($bpd)
        ->post("/bpd/projects/{$project->id}/outcome", [
            'result' => ReviewOutcome::FollowedUp->value,
            'note' => 'Catatan ini cukup panjang lebih dari tiga puluh karakter.',
        ])
        ->assertSessionHasErrors('note');

    // Move to InReview
    app(CurrentVillage::class)->set($village->id);
    $project->update(['public_status' => PublicStatus::InReview]);
    $cycle->update(['in_review_since' => now()]);

    // B7: Note < 30 chars rejected.
    $this->actingAs($bpd)
        ->post("/bpd/projects/{$project->id}/outcome", [
            'result' => ReviewOutcome::FollowedUp->value,
            'note' => 'Terlalu pendek',
        ])
        ->assertSessionHasErrors('note');

    // B8: Valid outcome closes cycle, publishes status, starts new cycle.
    $validNote = 'BPD sudah meninjau lokasi bersama pemerintah desa dan menyepakati perbaikan.';
    $this->actingAs($bpd)
        ->post("/bpd/projects/{$project->id}/outcome", [
            'result' => ReviewOutcome::FollowedUp->value,
            'note' => $validNote,
        ])
        ->assertRedirect();

    app(CurrentVillage::class)->set($village->id);
    $project->refresh();
    $cycle->refresh();

    expect($project->public_status)->toBe(PublicStatus::Reviewed);
    expect($project->current_cycle_id)->not->toBe($cycle->id);
    expect($cycle->closed_at)->not->toBeNull();
    expect($cycle->result)->toBe(ReviewOutcome::FollowedUp);
    expect($cycle->note)->toBe($validNote);

    // B9: Add dated follow-up note to closed cycle.
    $this->actingAs($bpd)
        ->post("/bpd/cycles/{$cycle->id}/notes", [
            'note' => 'Perbaikan ketebalan jalan sudah diselesaikan pada 20 Desember.',
        ])
        ->assertRedirect();

    expect($cycle->notes()->count())->toBe(1);
});

it('forbids non-BPD roles from accessing /bpd', function () {
    $village = Village::factory()->create();
    $desa = User::factory()->create([
        'village_id' => $village->id,
        'role' => Role::Desa,
    ]);

    $this->actingAs($desa)
        ->get('/bpd')
        ->assertForbidden();
});

it('displays report measurement answers vs RAB, above-standard items, and serves report photos exclusively to owning village BPD (B2, C11)', function () {
    Storage::fake('local');
    Storage::disk('local')->put('reports/test-bukti.webp', 'fake-webp-bytes');

    [$village, $bpd] = setupBpdVillage();

    $audit = Audit::factory()->create(['village_id' => $village->id]);
    AuditItem::factory()->create([
        'audit_id' => $audit->id,
        'position' => 1,
        'raw_name' => 'Semen Portland 50kg',
        'unit_price_rp' => 85000,
        'label' => PriceLabel::AboveStandard,
        'over_pct' => 18,
        'doubt' => ['neutral_explanation' => 'Biaya langsir ke lokasi menanjak.'],
    ]);

    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
        'public_status' => PublicStatus::InReview,
        'published_at' => now(),
    ]);

    $cycle = ReviewCycle::create([
        'project_id' => $project->id,
        'opened_at' => now(),
        'in_review_since' => now(),
    ]);
    $project->update(['current_cycle_id' => $cycle->id]);

    $measurement = ProjectMeasurement::factory()->create([
        'project_id' => $project->id,
        'name' => 'Tebal beton',
        'value' => 12,
        'unit' => 'cm',
    ]);

    $warga = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);
    $report = Report::factory()->create([
        'project_id' => $project->id,
        'cycle_id' => $cycle->id,
        'user_id' => $warga->id,
        'kind' => ReportKind::Mismatch,
        'distance_m' => 14,
        'opened_at' => null,
    ]);

    ReportAnswer::factory()->create([
        'report_id' => $report->id,
        'measurement_id' => $measurement->id,
        'answer' => MeasurementAnswer::Mismatch,
        'measured_value' => 8.5,
        'measured_unit' => 'cm',
    ]);

    $photo = ReportPhoto::factory()->create([
        'report_id' => $report->id,
        'position' => 1,
        'path' => 'reports/test-bukti.webp',
    ]);

    ProjectChange::factory()->create([
        'project_id' => $project->id,
        'item_label' => 'Semen Portland 50kg',
        'field' => 'volume',
        'old_value' => '450 sak',
        'new_value' => '480 sak',
        'reason' => 'Penambahan tebal cor pada tanjakan.',
    ]);

    $this->actingAs($bpd)
        ->get('/bpd')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('review/Index')
            ->where('selectedProject.valid_mismatch_count', 1)
            ->where('selectedProject.above_standard_items.0.name', 'Semen Portland 50kg')
            ->where('selectedProject.above_standard_items.0.over_pct', 18)
            ->where('selectedProject.above_standard_items.0.explanation', 'Biaya langsir ke lokasi menanjak.')
            ->where('selectedProject.changes.0.item_label', 'Semen Portland 50kg')
            ->where('selectedProject.changes.0.old_value', '450 sak')
            ->where('selectedProject.changes.0.new_value', '480 sak')
            ->where('selectedProject.changes.0.reason', 'Penambahan tebal cor pada tanjakan.')
            ->where('selectedProject.reports.0.answers.0.measurement_name', 'Tebal beton')
            ->where('selectedProject.reports.0.answers.0.rab_value', '12')
            ->where('selectedProject.reports.0.answers.0.rab_unit', 'cm')
            ->where('selectedProject.reports.0.answers.0.answer', 'mismatch')
            ->where('selectedProject.reports.0.answers.0.measured_value', '8.5')
            ->where('selectedProject.reports.0.answers.0.measured_unit', 'cm')
            ->where('selectedProject.reports.0.photos.0.id', $photo->id)
            ->where('selectedProject.reports.0.photos.0.url', route('bpd.photos.show', $photo->id))
        );

    // Bulk mark all reports opened for the project.
    expect($report->refresh()->opened_at)->toBeNull();
    $this->actingAs($bpd)
        ->post(route('bpd.projects.reports.open-all', $project->id))
        ->assertRedirect();
    expect($report->refresh()->opened_at)->not->toBeNull();

    // Dedicated citizen reports inbox page (/bpd/reports) & BPD reply note.
    $this->actingAs($bpd)
        ->get(route('bpd.reports.index', ['report' => $report->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('review/Reports')
            ->where('selectedReportId', $report->id)
            ->where('reports.0.id', $report->id)
            ->where('reports.0.reporter_name', $warga->name)
            ->where('reports.0.is_google_account', true)
        );

    $this->actingAs($bpd)
        ->post(route('bpd.reports.reply', $report->id), [
            'reply' => 'Terima kasih atas laporannya, BPD akan mengecek ketebalan beton besok pagi.',
        ])
        ->assertRedirect();

    $report->refresh();
    expect($report->bpd_reply)->toBe('Terima kasih atas laporannya, BPD akan mengecek ketebalan beton besok pagi.');
    expect($report->bpd_replied_at)->not->toBeNull();

    // Only one note allowed per report: a second submission is rejected and preserves the first note.
    $this->actingAs($bpd)
        ->post(route('bpd.reports.reply', $report->id), [
            'reply' => 'Catatan kedua yang seharusnya ditolak karena sudah pernah dikirim.',
        ])
        ->assertSessionHasErrors('reply');

    expect($report->refresh()->bpd_reply)
        ->toBe('Terima kasih atas laporannya, BPD akan mengecek ketebalan beton besok pagi.');

    // Verification is final: after marking valid, no re-verification is allowed.
    $this->actingAs($bpd)
        ->post(route('bpd.reports.validate', $report->id))
        ->assertRedirect();

    $report->refresh();
    expect($report->bpd_verdict)->toBe(ReportVerdict::Valid)
        ->and($report->bpd_verified_at)->not->toBeNull()
        ->and($report->isVerified())->toBeTrue();

    $this->actingAs($bpd)
        ->post(route('bpd.reports.invalidate', $report->id), ['reason' => 'Duplikat'])
        ->assertSessionHasErrors('reason');
    $this->actingAs($bpd)
        ->post(route('bpd.reports.reply', $report->id), ['reply' => 'Catatan kedua pada laporan yang sudah punya balasan tetap ditolak.'])
        ->assertSessionHasErrors('reply');

    expect($report->refresh()->bpd_verdict)->toBe(ReportVerdict::Valid)
        ->and($report->invalid_reason)->toBeNull();

    // If a report is verified first without a note, BPD can still send their 1 reply message afterwards.
    $warga2 = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);
    $secondReport = Report::factory()->create([
        'project_id' => $project->id,
        'cycle_id' => $cycle->id,
        'user_id' => $warga2->id,
        'kind' => ReportKind::Mismatch,
        'opened_at' => null,
    ]);

    $this->actingAs($bpd)
        ->post(route('bpd.reports.validate', $secondReport->id), ['note' => ''])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($secondReport->refresh()->bpd_verdict)->toBe(ReportVerdict::Valid)
        ->and($secondReport->bpd_reply)->toBeNull();

    $this->actingAs($bpd)
        ->post(route('bpd.reports.reply', $secondReport->id), [
            'reply' => 'Balasan resmi BPD setelah laporan diverifikasi valid.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($secondReport->refresh()->bpd_reply)->toBe('Balasan resmi BPD setelah laporan diverifikasi valid.');

    // Owning village BPD can view the photo.
    $this->actingAs($bpd)
        ->get(route('bpd.photos.show', $photo->id))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');

    // Desa role of the same village is forbidden from viewing report photos (C11).
    $desa = User::factory()->create(['village_id' => $village->id, 'role' => Role::Desa]);
    $this->actingAs($desa)
        ->get(route('bpd.photos.show', $photo->id))
        ->assertForbidden();

    // BPD from another village cannot access the photo (IDOR guard -> 404).
    $otherVillage = Village::factory()->create();
    $otherBpd = User::factory()->create(['village_id' => $otherVillage->id, 'role' => Role::Bpd]);
    $this->actingAs($otherBpd)
        ->get(route('bpd.photos.show', $photo->id))
        ->assertNotFound();
});

it('evaluates distinct accounts and distinct devices via listener when ReportSubmitted is dispatched (D16, Adendum v1.2)', function () {
    [$village] = setupBpdVillage();

    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => auditFor($village),
        'public_status' => PublicStatus::None,
        'current_cycle_id' => null,
        'published_at' => now(),
    ]);

    $warga1 = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);
    $warga2 = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);
    $warga3 = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);

    // First cycleless mismatch reports from 2 distinct accounts & devices should open a cycle but stay None.
    $r1 = Report::factory()->create([
        'project_id' => $project->id,
        'cycle_id' => null,
        'user_id' => $warga1->id,
        'kind' => ReportKind::Mismatch,
        'device_hash' => str_repeat('a', 64),
    ]);
    $r2 = Report::factory()->create([
        'project_id' => $project->id,
        'cycle_id' => null,
        'user_id' => $warga2->id,
        'kind' => ReportKind::Mismatch,
        'device_hash' => str_repeat('b', 64),
    ]);

    ReportSubmitted::dispatch($r2->id, $project->id);
    $project->refresh();
    expect($project->public_status)->toBe(PublicStatus::None)
        ->and($project->current_cycle_id)->not->toBeNull()
        ->and($r1->refresh()->cycle_id)->toBe($project->current_cycle_id)
        ->and($r2->refresh()->cycle_id)->toBe($project->current_cycle_id);

    // 3rd report from a Match reporter with a 3rd device does not trigger InReview.
    $rMatch = Report::factory()->create([
        'project_id' => $project->id,
        'cycle_id' => $project->current_cycle_id,
        'user_id' => $warga3->id,
        'kind' => ReportKind::Match,
        'device_hash' => str_repeat('c', 64),
    ]);
    ReportSubmitted::dispatch($rMatch->id, $project->id);
    expect($project->refresh()->public_status)->toBe(PublicStatus::None);

    // When 3rd reporter submits a valid Mismatch on a 3rd distinct device, listener triggers InReview via EvaluateReviewThreshold.
    $warga4 = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);
    $r3 = Report::factory()->create([
        'project_id' => $project->id,
        'cycle_id' => $project->current_cycle_id,
        'user_id' => $warga4->id,
        'kind' => ReportKind::Mismatch,
        'device_hash' => str_repeat('d', 64),
    ]);
    ReportSubmitted::dispatch($r3->id, $project->id);

    $project->refresh();
    expect($project->public_status)->toBe(PublicStatus::InReview)
        ->and($project->currentCycle?->in_review_since)->not->toBeNull();
});

it('preserves historical target snapshot on citizen reports across subsequent CCO measurement edits (D15, S7)', function () {
    Storage::fake('local');
    [$village, $bpd] = setupBpdVillage();

    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => auditFor($village),
        'public_status' => PublicStatus::None,
        'published_at' => now(),
        'lat' => -8.5321,
        'lng' => 115.3214,
    ]);
    $cycle = ReviewCycle::create([
        'project_id' => $project->id,
        'opened_at' => now(),
    ]);
    $project->update(['current_cycle_id' => $cycle->id]);

    $measurement = ProjectMeasurement::factory()->create([
        'project_id' => $project->id,
        'name' => 'Tebal beton',
        'value' => 12,
        'unit' => 'cm',
    ]);

    $warga = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);
    $report = app(SubmitReport::class)->handle(
        project: $project,
        citizen: $warga,
        submission: new ReportSubmission(
            answers: [new SubmittedAnswer($measurement->id, MeasurementAnswer::Mismatch, '8.5')],
            note: 'Ketebalan di lapangan lebih tipis.',
            lat: -8.5321,
            lng: 115.3214,
            accuracyMeters: 15,
            deviceHash: str_repeat('e', 64),
            photos: [UploadedFile::fake()->image('bukti.jpg', 640, 480)],
        ),
    );

    $savedAnswer = $report->answers()->firstOrFail();
    expect((float) $savedAnswer->target_value)->toBe(12.0)
        ->and($savedAnswer->target_unit)->toBe('cm');

    // While still PublicStatus::None (D12 allows CCO outside InReview), Desa edits the target measurement via CCO.
    $desa = User::factory()->create(['village_id' => $village->id, 'role' => Role::Desa]);
    $this->actingAs($desa)
        ->post("/desa/projects/{$project->id}/cco/measurements/{$measurement->id}", [
            'value' => 15,
            'reason' => 'Penyesuaian spesifikasi kontrak CCO.',
        ])
        ->assertRedirect();

    expect((float) $measurement->refresh()->value)->toBe(15.0)
        ->and($measurement->unit)->toBe('cm');

    // Move to InReview so BPD dashboard shows the historical report.
    $project->update(['public_status' => PublicStatus::InReview]);
    $cycle->update(['in_review_since' => now()]);

    // BPD dashboard must still compare the report against its historical 12 cm snapshot, not 15 cm!
    $this->actingAs($bpd)
        ->get('/bpd?project='.$project->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedProject.reports.0.answers.0.rab_value', '12')
            ->where('selectedProject.reports.0.answers.0.rab_unit', 'cm')
            ->where('selectedProject.reports.0.answers.0.measured_value', '8.5')
            ->where('selectedProject.reports.0.answers.0.measured_unit', 'cm')
        );

    // After BPD publishes review outcome, public ProjectResource exposes the snapshot alongside updated measurement.
    $this->actingAs($bpd)
        ->post("/bpd/projects/{$project->id}/outcome", [
            'result' => ReviewOutcome::FollowedUp->value,
            'note' => 'Sudah ditindaklanjuti sesuai dengan adendum CCO resmi.',
        ])
        ->assertRedirect();

    $this->actingAs($bpd)
        ->post("/bpd/cycles/{$cycle->id}/notes", [
            'note' => 'Pemdes telah menyesuaikan dokumen kontrak dan berita acara lapangan.',
        ])
        ->assertRedirect();

    // A brand-new report in the newly opened cycle must not appear in published_reports until reviewed.
    $newCycleCitizen = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);
    Report::factory()->create([
        'project_id' => $project->id,
        'cycle_id' => $project->fresh()->current_cycle_id,
        'user_id' => $newCycleCitizen->id,
        'kind' => ReportKind::Match,
        'note' => 'Laporan siklus baru yang belum ditinjau BPD.',
    ]);

    $this->get("/p/{$project->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('project.public_status', 'reviewed')
            ->where('project.in_review_since', fn ($value) => is_string($value) && $value !== '')
            ->where('project.review.outcome', 'followed_up')
            ->where('project.review.note', 'Sudah ditindaklanjuti sesuai dengan adendum CCO resmi.')
            ->where('project.review.notes.0.note', 'Pemdes telah menyesuaikan dokumen kontrak dan berita acara lapangan.')
            ->has('project.published_reports', 1)
            ->where('project.measurements.0.value', '15')
            ->where('project.measurements.0.unit', 'cm')
            ->where('project.published_reports.0.answers.0.target_value', '12')
            ->where('project.published_reports.0.answers.0.target_unit', 'cm')
            ->where('project.published_reports.0.answers.0.measured_value', '8.5')
        );
});
