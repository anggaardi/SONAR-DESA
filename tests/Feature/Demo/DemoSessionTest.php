<?php

declare(strict_types=1);

use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Http\Middleware\SetCurrentVillage;
use App\Modules\Core\Village\Village;
use App\Modules\Demo\Actions\VillageCloner;
use App\Modules\Project\Models\ProgressPhoto;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Report\Models\Report;
use App\Modules\Review\Models\ReviewCycle;
use App\Modules\Review\Models\ReviewNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * A small Desa Contoh with every linked table filled, so the cloner is exercised end to end.
 */
function demoTemplate(): Village
{
    $template = Village::factory()->create(['slug' => 'desa-contoh-template', 'name' => 'Desa Contoh']);
    $desa = User::factory()->desa($template)->create();
    User::factory()->bpd($template)->create();

    $audit = Audit::factory()->create(['village_id' => $template->id, 'owner_id' => $desa->id]);
    $project = Project::factory()->inReview()->create([
        'village_id' => $template->id,
        'audit_id' => $audit->id,
        'slug' => 'rabat-beton-jl-melati',
        'name' => 'Rabat beton Jl. Melati',
    ]);
    ProjectMeasurement::factory()->create(['project_id' => $project->id]);
    ProgressPhoto::factory()->create(['project_id' => $project->id]);

    $cycle = ReviewCycle::query()->create(['project_id' => $project->id, 'opened_at' => now()]);
    ReviewNote::query()->create(['cycle_id' => $cycle->id, 'note' => 'Catatan BPD']);
    $project->forceFill(['current_cycle_id' => $cycle->id])->save();

    $first = Report::factory()->create(['project_id' => $project->id, 'cycle_id' => $cycle->id]);
    Report::factory()->create(['project_id' => $project->id, 'cycle_id' => $cycle->id, 'superseded_by' => $first->id]);

    return $template;
}

/** @return list<string> */
function tablesLinkedToVillages(): array
{
    $edges = DB::select(<<<'SQL'
        SELECT tc.table_name AS child, ccu.table_name AS parent
        FROM information_schema.table_constraints tc
        JOIN information_schema.constraint_column_usage ccu ON tc.constraint_name = ccu.constraint_name
        WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = 'public'
        SQL);

    $linked = ['villages'];
    do {
        $before = count($linked);
        foreach ($edges as $edge) {
            if (in_array($edge->parent, $linked, true) && ! in_array($edge->child, $linked, true)) {
                $linked[] = $edge->child;
            }
        }
    } while (count($linked) > $before);

    // villages is the clone root; users are demo accounts made by the cloner, not copied rows.
    return array_values(array_diff($linked, ['villages', 'users']));
}

it('registers every table linked to villages in the cloner (F6-2)', function () {
    $unregistered = array_diff(tablesLinkedToVillages(), array_keys(VillageCloner::TABLES));

    expect($unregistered)->toBe([]);
});

it('copies the same number of rows per table as the template (F6-2)', function () {
    $template = demoTemplate();

    $clone = app(VillageCloner::class)->clone($template);

    foreach (array_keys(VillageCloner::TABLES) as $table) {
        $count = fn (Village $village): int => DB::table($table)
            ->when(
                in_array($table, ['audits', 'projects'], true),
                fn ($q) => $q->where('village_id', $village->id),
                fn ($q) => $q->whereIn('id', cloneScopedIds($table, $village)),
            )
            ->count();

        expect($count($clone))->toBe($count($template), "row count for {$table}");
    }
});

/** @return list<int> */
function cloneScopedIds(string $table, Village $village): array
{
    $projectIds = DB::table('projects')->where('village_id', $village->id)->pluck('id');
    $auditIds = DB::table('audits')->where('village_id', $village->id)->pluck('id');
    $cycleIds = DB::table('review_cycles')->whereIn('project_id', $projectIds)->pluck('id');

    return match ($table) {
        'audit_items' => DB::table($table)->whereIn('audit_id', $auditIds)->pluck('id')->all(),
        'review_notes' => DB::table($table)->whereIn('cycle_id', $cycleIds)->pluck('id')->all(),
        default => DB::table($table)->whereIn('project_id', $projectIds)->pluck('id')->all(),
    };
}

it('points every cloned foreign key at the clone, never at the template', function () {
    $template = demoTemplate();

    $clone = app(VillageCloner::class)->clone($template);

    $project = Project::withoutGlobalScope('village')->where('village_id', $clone->id)->sole();
    $cycle = ReviewCycle::query()->where('project_id', $project->id)->sole();
    $reports = Report::query()->where('project_id', $project->id)->orderBy('id')->get();

    expect($project->current_cycle_id)->toBe($cycle->id)
        ->and($project->slug)->not->toBe('rabat-beton-jl-melati')
        ->and(Audit::withoutGlobalScope('village')->find($project->audit_id)?->village_id)->toBe($clone->id)
        ->and($reports->pluck('cycle_id')->unique()->all())->toBe([$cycle->id])
        ->and($reports[1]->superseded_by)->toBe($reports[0]->id)
        ->and(User::query()->where('village_id', $clone->id)->pluck('role')->map->value->sort()->values()->all())
        ->toBe(['bpd', 'desa']);
});

it('enters the demo in one click as warga and lands on the village page (M1)', function () {
    demoTemplate();

    $response = $this->post('/demo/start/warga');

    $village = Village::query()->where('is_demo', true)->sole();
    $response->assertRedirect(route('villages.projects', $village->slug));
    $this->assertGuest();

    $this->get(route('villages.projects', $village->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('project/Village')
            ->where('demo.role', 'warga')
            ->has('projects', 1));
});

it('logs in as the demo account of the chosen role', function (Role $role) {
    demoTemplate();

    $this->post("/demo/start/{$role->value}");

    $village = Village::query()->where('is_demo', true)->sole();
    expect(auth()->user()?->role)->toBe($role)
        ->and(auth()->user()?->village_id)->toBe($village->id);
})->with([Role::Desa, Role::Bpd]);

it('switches role inside the same demo village', function () {
    demoTemplate();
    $this->post('/demo/start/desa');
    $village = Village::query()->where('is_demo', true)->sole();

    $this->post('/demo/switch/bpd');

    expect(auth()->user()?->role)->toBe(Role::Bpd)
        ->and(auth()->user()?->village_id)->toBe($village->id)
        ->and(Village::query()->where('is_demo', true)->count())->toBe(1);
});

it('keeps one visitor demo invisible to another visitor (M4)', function () {
    demoTemplate();
    $this->post('/demo/start/warga');
    $mine = Village::query()->where('is_demo', true)->sole();
    $project = Project::withoutGlobalScope('village')->where('village_id', $mine->id)->sole();

    $this->flushSession();
    auth()->logout();

    $this->get(route('villages.projects', $mine->slug))->assertNotFound();
    $this->get(route('projects.show', $project->slug))->assertNotFound();
});

it('starts over from the template on "Mulai ulang demo" (M5)', function () {
    demoTemplate();
    $this->post('/demo/start/desa');
    $old = Village::query()->where('is_demo', true)->sole();
    Project::withoutGlobalScope('village')->where('village_id', $old->id)->update(['name' => 'Diubah pengunjung']);

    $this->post('/demo/reset');

    $new = Village::query()->where('is_demo', true)->sole();
    expect($new->id)->not->toBe($old->id)
        ->and(Village::query()->find($old->id))->toBeNull()
        ->and(Project::withoutGlobalScope('village')->where('village_id', $new->id)->value('name'))
        ->toBe('Rabat beton Jl. Melati')
        ->and(auth()->user()?->role)->toBe(Role::Desa)
        ->and(session(SetCurrentVillage::DEMO_SESSION_KEY))->toBe($new->id);
});

it('purges demo villages unused for 24 hours and keeps the template (F6-3)', function () {
    Storage::fake('local');
    $template = demoTemplate();
    $cloner = app(VillageCloner::class);
    $expired = $cloner->clone($template);
    $fresh = $cloner->clone($template);
    Storage::disk('local')->put("villages/{$expired->id}/rab.pdf", 'x');

    $this->travel(25)->hours();
    $fresh->update(['demo_expires_at' => now()->addHour()]);

    $this->artisan('demo:purge-expired')->assertSuccessful();

    expect(Village::query()->find($expired->id))->toBeNull()
        ->and(Village::query()->find($fresh->id))->not->toBeNull()
        ->and(Village::query()->find($template->id))->not->toBeNull()
        ->and(Project::withoutGlobalScope('village')->where('village_id', $template->id)->count())->toBe(1)
        ->and(User::query()->where('village_id', $expired->id)->count())->toBe(0);
    Storage::disk('local')->assertMissing("villages/{$expired->id}");
});

it('rejects an unknown demo role', function () {
    $this->post('/demo/start/kades')->assertNotFound();
});

it('keeps feature modules unaware of demo mode (F6-4)', function () {
    $featureModules = ['Standard', 'Audit', 'Project', 'Report', 'Review'];
    $hits = [];

    foreach ($featureModules as $module) {
        foreach (File::allFiles(app_path("Modules/{$module}")) as $file) {
            if (preg_match('/is_demo|DEMO_SESSION_KEY|demo_village_id/', $file->getContents()) === 1) {
                $hits[] = $file->getRelativePathname();
            }
        }
    }

    expect($hits)->toBe([]);
});
