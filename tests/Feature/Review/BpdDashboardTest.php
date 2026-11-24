<?php

declare(strict_types=1);

use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Report\Models\Report;
use App\Modules\Review\Actions\EvaluateReviewThreshold;
use App\Modules\Review\Enums\ReviewOutcome;
use App\Modules\Review\Models\ReviewCycle;
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
    expect($reports[0]->refresh()->invalid_reason)->toBe('Foto tidak relevan');
    expect($project->refresh()->public_status)->toBe(PublicStatus::InReview);

    // Restore works while cycle is open.
    $this->actingAs($bpd)
        ->post("/bpd/reports/{$reports[0]->id}/restore")
        ->assertRedirect();

    expect($reports[0]->refresh()->invalid_reason)->toBeNull();
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
