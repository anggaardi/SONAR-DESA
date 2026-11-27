<?php

declare(strict_types=1);

use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Report\Models\Report;
use App\Modules\Review\Actions\AddFollowUpNote;
use App\Modules\Review\Actions\SubmitReviewOutcome;
use App\Modules\Review\Enums\ReviewOutcome;
use App\Modules\Review\Models\ReviewCycle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

it('shows the guest view without leaking reports when not logged in (LS-01)', function () {
    Report::factory()->create();

    $response = $this->get(route('reports.mine'));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('report/MyReports')
        ->where('myReports.state', 'guest')
        ->where('myReports.session', null)
        ->where('myReports.reports', [])
        ->where('myReports.urls.google_login', route('reports.mine.login')));
});

it('redirects guests to Google with /laporan-saya as intended URL (LS-02)', function () {
    $this->get(route('reports.mine.login'))
        ->assertRedirect(route('auth.google'))
        ->assertSessionHas('url.intended', route('reports.mine'));

    $warga = User::factory()->create(['role' => Role::Warga, 'village_id' => null]);
    $this->actingAs($warga)
        ->get(route('reports.mine.login'))
        ->assertRedirect(route('reports.mine'));
});

it('forbids desa and BPD accounts with HTTP 403 (LS-03)', function (Role $role) {
    $village = Village::factory()->create();
    $official = User::factory()->create([
        'role' => $role,
        'village_id' => $village->id,
    ]);

    $this->actingAs($official)
        ->get(route('reports.mine'))
        ->assertForbidden()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('report/Forbidden')
            ->where('project', null));
})->with([Role::Desa, Role::Bpd]);

it('guides a fresh demo warga to the sample project with highest mismatch progress and hides internal demo email (LS-04, LS-12)', function () {
    $template = Village::factory()->create(['slug' => config('sonar.demo.template_slug'), 'name' => 'Desa Contoh']);
    $desa = User::factory()->desa($template)->create();
    User::factory()->bpd($template)->create();
    $firstAudit = Audit::factory()->create(['village_id' => $template->id, 'owner_id' => $desa->id]);
    $secondAudit = Audit::factory()->create(['village_id' => $template->id, 'owner_id' => $desa->id]);

    $first = Project::factory()->create([
        'village_id' => $template->id,
        'audit_id' => $firstAudit->id,
        'slug' => 'saluran-irigasi-desa',
        'name' => 'Saluran Irigasi',
        'public_status' => PublicStatus::None,
    ]);
    ProjectMeasurement::factory()->create(['project_id' => $first->id]);

    $target = Project::factory()->create([
        'village_id' => $template->id,
        'audit_id' => $secondAudit->id,
        'slug' => 'rabat-beton-jl-melati',
        'name' => 'Rabat Beton Jl. Melati',
        'public_status' => PublicStatus::None,
    ]);
    ProjectMeasurement::factory()->create(['project_id' => $target->id]);
    Report::factory()->count(2)->create([
        'project_id' => $target->id,
        'kind' => ReportKind::Mismatch,
    ]);

    $this->post('/demo/start/warga');

    $response = $this->get(route('reports.mine'));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('report/MyReports')
        ->where('myReports.state', 'authenticated')
        ->where('myReports.session.auth_type', 'demo')
        ->where('myReports.session.email', null)
        ->where('myReports.reports', [])
        ->where('myReports.suggested_project.name', 'Rabat Beton Jl. Melati')
        ->where('myReports.suggested_project.urls.check', fn (string $url) => str_ends_with($url, '/cek')));

    expect($response->getContent())->not->toContain('@demo.sonar-desa.test');
});

it('shows a demo citizen report in Laporan Saya right after submitting Cek Spek with the sample (LS-05)', function () {
    Storage::fake('local');
    $template = Village::factory()->create(['slug' => config('sonar.demo.template_slug'), 'name' => 'Desa Contoh']);
    $desa = User::factory()->desa($template)->create();
    User::factory()->bpd($template)->create();
    $audit = Audit::factory()->create(['village_id' => $template->id, 'owner_id' => $desa->id]);
    $project = Project::factory()->create([
        'village_id' => $template->id,
        'audit_id' => $audit->id,
        'slug' => 'rabat-beton-contoh',
        'name' => 'Rabat Beton Contoh',
    ]);
    $measurement = ProjectMeasurement::factory()->create(['project_id' => $project->id]);
    Report::factory()->count(2)->create([
        'project_id' => $project->id,
        'kind' => ReportKind::Mismatch,
    ]);

    $this->post('/demo/start/warga');
    $demoVillage = Village::query()->where('is_demo', true)->sole();
    $demoProject = Project::withoutGlobalScope('village')->where('village_id', $demoVillage->id)->sole();
    $sample = $this->getJson(route('demo.sample-location', $demoProject->slug))->json();

    $this->post(route('reports.store', $demoProject->slug), [
        'answers' => [['measurement_id' => $demoProject->measurements()->sole()->id, 'answer' => 'mismatch', 'measured_value' => '8']],
        'photos' => [UploadedFile::fake()->image('contoh.jpg', 800, 600)],
        'lat' => $sample['lat'],
        'lng' => $sample['lng'],
        'accuracy_m' => $sample['accuracy_m'],
        'note' => 'Tebal cor hanya 8 cm',
    ])->assertRedirect(route('reports.submitted', $demoProject->slug));

    $this->get(route('reports.submitted', $demoProject->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('submitted.urls.my_reports', route('reports.mine')));

    $this->get(route('reports.mine'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('myReports.summary.total_count', 1)
            ->where('myReports.summary.active_count', 1)
            ->where('myReports.summary.reviewed_or_in_review_count', 1)
            ->has('myReports.reports', 1)
            ->where('myReports.reports.0.project.name', 'Rabat Beton Contoh')
            ->where('myReports.reports.0.kind', 'mismatch')
            ->where('myReports.reports.0.status', 'active')
            ->where('myReports.reports.0.mismatch_count.current', 3)
            ->where('myReports.reports.0.mismatch_count.threshold', 3)
            ->where('myReports.reports.0.bpd_review.status', 'in_review'));

    expect($measurement->id)->toBeGreaterThan(0);
});

it('orders newest reports first and distinguishes active vs superseded reports (LS-06)', function () {
    $warga = User::factory()->create(['google_id' => 'g-order-1', 'email' => 'warga@gmail.com']);
    $project = Project::factory()->create(['name' => 'Jalan Usaha Tani']);
    $cycle = ReviewCycle::create(['project_id' => $project->id, 'opened_at' => now()->subDays(2)]);
    $project->update(['current_cycle_id' => $cycle->id]);

    $newest = Report::factory()->create([
        'project_id' => $project->id,
        'cycle_id' => $cycle->id,
        'user_id' => $warga->id,
        'kind' => ReportKind::Mismatch,
        'created_at' => now()->subHour(),
    ]);

    Report::factory()->create([
        'project_id' => $project->id,
        'cycle_id' => $cycle->id,
        'user_id' => $warga->id,
        'kind' => ReportKind::Match,
        'superseded_by' => $newest->id,
        'created_at' => now()->subDay(),
    ]);

    $this->actingAs($warga)
        ->get(route('reports.mine'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('myReports.summary.total_count', 2)
            ->where('myReports.summary.active_count', 1)
            ->where('myReports.reports.0.id', $newest->id)
            ->where('myReports.reports.0.status', 'active')
            ->where('myReports.reports.0.kind', 'mismatch')
            ->where('myReports.reports.1.status', 'superseded')
            ->where('myReports.reports.1.kind', 'match'));
});

it('shows neutral not_counted status and never leaks invalid_reason, coordinates, device_hash, or google_id (LS-07)', function () {
    $warga = User::factory()->create(['google_id' => 'secret-google-id-999', 'email' => 'ayu@gmail.com']);
    $project = Project::factory()->create();

    Report::factory()->create([
        'project_id' => $project->id,
        'user_id' => $warga->id,
        'kind' => ReportKind::Mismatch,
        'lat' => -8.5321000,
        'lng' => 115.3214000,
        'device_hash' => 'secret-device-hash-abc123',
        'invalid_reason' => 'Alasan sensitif internal BPD',
    ]);

    $response = $this->actingAs($warga)->get(route('reports.mine'));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('myReports.reports.0.status', 'not_counted')
        ->missing('myReports.reports.0.invalid_reason')
        ->missing('myReports.reports.0.lat')
        ->missing('myReports.reports.0.lng')
        ->missing('myReports.reports.0.device_hash'));

    expect($response->getContent())
        ->not->toContain('Alasan sensitif internal BPD')
        ->not->toContain('secret-device-hash-abc123')
        ->not->toContain('secret-google-id-999')
        ->not->toContain('-8.5321')
        ->not->toContain('115.3214');
});

it('keeps cycle threshold count and syncs BPD outcome and follow-up notes after BPD closes the cycle (LS-08, LS-09)', function () {
    $warga = User::factory()->create(['google_id' => 'g-sync-1', 'email' => 'sync@gmail.com']);
    $project = Project::factory()->create(['public_status' => PublicStatus::InReview]);
    $cycle = ReviewCycle::create([
        'project_id' => $project->id,
        'opened_at' => now()->subDays(5),
        'in_review_since' => now()->subDays(3),
    ]);
    $project->update(['current_cycle_id' => $cycle->id]);

    Report::factory()->create([
        'project_id' => $project->id,
        'cycle_id' => $cycle->id,
        'user_id' => $warga->id,
        'kind' => ReportKind::Mismatch,
    ]);
    Report::factory()->count(2)->create([
        'project_id' => $project->id,
        'cycle_id' => $cycle->id,
        'kind' => ReportKind::Mismatch,
    ]);

    $this->actingAs($warga)
        ->get(route('reports.mine'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('myReports.reports.0.mismatch_count.current', 3)
            ->where('myReports.reports.0.bpd_review.status', 'in_review'));

    app(CurrentVillage::class)->set($project->village_id);
    try {
        $closedCycle = app(SubmitReviewOutcome::class)->execute(
            $project,
            ReviewOutcome::FollowedUp,
            'Pemerintah desa sepakat menambah ketebalan cor jalan sesuai RAB dalam 14 hari kerja.',
        );
        app(AddFollowUpNote::class)->execute($closedCycle, 'Penambahan cor 4 cm telah selesai dan diperiksa bersama BPD.');
    } finally {
        app(CurrentVillage::class)->clear();
    }

    $this->actingAs($warga)
        ->get(route('reports.mine'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('myReports.reports.0.mismatch_count.current', 3)
            ->where('myReports.reports.0.bpd_review.status', 'reviewed')
            ->where('myReports.reports.0.bpd_review.outcome', 'followed_up')
            ->where('myReports.reports.0.bpd_review.note', 'Pemerintah desa sepakat menambah ketebalan cor jalan sesuai RAB dalam 14 hari kerja.')
            ->where('myReports.reports.0.bpd_review.notes.0.note', 'Penambahan cor 4 cm telah selesai dan diperiksa bersama BPD.')
            ->where('myReports.reports.0.bpd_review.submitted_after_review', false));
});

it('isolates reports across citizen accounts, unpublished projects, and demo sessions (LS-10)', function () {
    $wargaA = User::factory()->create(['google_id' => 'g-a', 'email' => 'a@gmail.com']);
    $wargaB = User::factory()->create(['google_id' => 'g-b', 'email' => 'b@gmail.com']);

    $published = Project::factory()->create(['name' => 'Proyek Publik A']);
    $draft = Project::factory()->create(['name' => 'Proyek Draf Rahasia', 'published_at' => null]);
    $otherDemoVillage = Village::factory()->create(['is_demo' => true, 'slug' => 'demo-lain']);
    $otherDemoProject = Project::factory()->create([
        'village_id' => $otherDemoVillage->id,
        'name' => 'Proyek Sesi Demo Lain',
    ]);

    Report::factory()->create(['project_id' => $published->id, 'user_id' => $wargaA->id]);
    Report::factory()->create(['project_id' => $draft->id, 'user_id' => $wargaA->id]);
    Report::factory()->create(['project_id' => $otherDemoProject->id, 'user_id' => $wargaA->id]);
    Report::factory()->create(['project_id' => $published->id, 'user_id' => $wargaB->id]);

    $this->actingAs($wargaA)
        ->get(route('reports.mine'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('myReports.reports', 1)
            ->where('myReports.reports.0.project.name', 'Proyek Publik A'));
});

it('shows Google session email and lets real warga sign out via POST /logout (LS-11)', function () {
    $warga = User::factory()->create(['name' => 'Warga', 'google_id' => 'g-out', 'email' => 'kadek@gmail.com']);

    $this->actingAs($warga)
        ->get(route('reports.mine'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('myReports.session.auth_type', 'google')
            ->where('myReports.session.email', 'kadek@gmail.com')
            ->where('myReports.urls.logout', route('logout')));

    $this->actingAs($warga)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});
