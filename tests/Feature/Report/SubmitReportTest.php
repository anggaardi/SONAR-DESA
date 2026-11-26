<?php

declare(strict_types=1);

use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Report\Enums\MeasurementAnswer;
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Report\Events\ReportSubmitted;
use App\Modules\Report\Http\Middleware\EnsureDeviceCookie;
use App\Modules\Report\Models\Report;
use App\Modules\Report\Models\ReportAnswer;
use App\Modules\Report\Models\ReportPhoto;
use App\Modules\Review\Models\ReviewCycle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

// Project point is -8.5321, 115.3214 (ProjectFactory). 0.001 degree of latitude is about 111.2 m.
const SUBMIT_SLUG = 'rabat-beton-melati';

// Every accepted send writes photos to the private disk; without this they would land in storage/app.
// File-level on purpose: almost every test here sends a report.
beforeEach(fn () => Storage::fake('local'));

/** A device cookie value as EnsureDeviceCookie makes it (40 letters and digits). */
function deviceToken(string $seed): string
{
    return str_pad($seed, 40, '0');
}

/** A published project with two measurements: tebal (cm) and lebar (m). */
function projectWithMeasurements(array $attributes = []): Project
{
    $project = Project::factory()->create(['slug' => SUBMIT_SLUG, ...$attributes]);
    ProjectMeasurement::factory()->create(['project_id' => $project->id, 'name' => 'Tebal beton', 'unit' => 'cm']);
    ProjectMeasurement::factory()->create(['project_id' => $project->id, 'name' => 'Lebar jalan', 'value' => '3.000', 'unit' => 'm']);

    return $project;
}

/**
 * A valid send: every measurement answered "Sesuai", one camera photo, at the project point.
 *
 * @param  list<array<string, mixed>>|null  $answers
 */
function reportPayload(Project $project, ?array $answers = null, array $overrides = []): array
{
    return [
        'answers' => $answers ?? $project->measurements()->orderBy('id')->pluck('id')
            ->map(fn (int $id): array => ['measurement_id' => $id, 'answer' => 'match', 'measured_value' => ''])
            ->all(),
        'photos' => [UploadedFile::fake()->image('foto.jpg', 640, 480)],
        'note' => 'Dicek pagi hari',
        'lat' => -8.5321,
        'lng' => 115.3214,
        'accuracy_m' => 15,
        ...$overrides,
    ];
}

/** @return list<array<string, mixed>> */
function answersFor(Project $project, string ...$answers): array
{
    return $project->measurements()->orderBy('id')->pluck('id')->values()
        ->map(fn (int $id, int $index): array => [
            'measurement_id' => $id,
            'answer' => $answers[$index],
            'measured_value' => $answers[$index] === 'mismatch' ? '8,5' : '',
        ])
        ->all();
}

function citizen(): User
{
    return User::factory()->create();
}

it('saves the report, recomputes the distance on the server, and dispatches ReportSubmitted', function () {
    $project = projectWithMeasurements();
    $cycle = ReviewCycle::create(['project_id' => $project->id, 'opened_at' => now()]);
    $project->update(['current_cycle_id' => $cycle->id]);
    $warga = citizen();
    Event::fake([ReportSubmitted::class]);

    $response = $this->actingAs($warga)->post(
        route('reports.store', SUBMIT_SLUG),
        reportPayload($project, answersFor($project, 'match', 'mismatch'), ['lat' => -8.5321 + 0.001]),
    );

    $response->assertSessionHasNoErrors()->assertRedirect(route('reports.submitted', SUBMIT_SLUG));
    $report = Report::query()->sole();
    expect($report->user_id)->toBe($warga->id)
        ->and($report->project_id)->toBe($project->id)
        ->and($report->cycle_id)->toBe($cycle->id)
        ->and($report->kind)->toBe(ReportKind::Mismatch)
        ->and((float) $report->distance_m)->toEqualWithDelta(111.2, 1.0)
        ->and((float) $report->accuracy_m)->toBe(15.0)
        ->and($report->note)->toBe('Dicek pagi hari')
        ->and($report->superseded_by)->toBeNull();
    Event::assertDispatched(
        ReportSubmitted::class,
        fn (ReportSubmitted $event): bool => $event->reportId === $report->id && $event->projectId === $project->id,
    );
});

it('decides the kind from the answers (PRD Fitur Inti 5.3, C9)', function (array $answers, ReportKind $kind) {
    $project = projectWithMeasurements();

    $this->actingAs(citizen())
        ->post(route('reports.store', SUBMIT_SLUG), reportPayload($project, answersFor($project, ...$answers)))
        ->assertSessionHasNoErrors();

    expect(Report::query()->sole()->kind)->toBe($kind);
})->with([
    'one "Tidak" among "Sesuai" is Selisih' => [['match', 'mismatch'], ReportKind::Mismatch],
    'all "Sesuai"' => [['match', 'match'], ReportKind::Match],
    '"Sesuai" and "Tidak bisa ukur"' => [['unmeasurable', 'match'], ReportKind::Match],
    'all "Tidak bisa ukur"' => [['unmeasurable', 'unmeasurable'], ReportKind::Unmeasurable],
]);

it('refuses a report from outside the radius and saves nothing, whatever the client claims (D-009, F4-2)', function () {
    $project = projectWithMeasurements();
    Event::fake([ReportSubmitted::class]);

    // 0.0045 degree is about 500 m; the client also claims to be inside the radius.
    $this->actingAs(citizen())->post(route('reports.store', SUBMIT_SLUG), reportPayload($project, overrides: [
        'lat' => -8.5321 + 0.0045,
        'distance_m' => 0,
        'in_radius' => true,
    ]))->assertSessionHasErrors([
        'location' => 'Kamu berada di luar 300 m dari titik proyek. Datangi lokasi proyek, lalu kirim lagi. Isianmu tidak hilang.',
    ]);

    expect(Report::query()->count())->toBe(0);
    Event::assertNotDispatched(ReportSubmitted::class);
});

it('accepts a report exactly at the radius and refuses one just beyond it (D-009)', function (float $latitudeOffset, bool $accepted) {
    $project = projectWithMeasurements();

    $response = $this->actingAs(citizen())->post(
        route('reports.store', SUBMIT_SLUG),
        reportPayload($project, overrides: ['lat' => -8.5321 + $latitudeOffset]),
    );

    $accepted ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('location');
    expect(Report::query()->count())->toBe($accepted ? 1 : 0);
})->with([
    // 0.002697 degree is about 299.9 m; 0.002700 degree is about 300.2 m.
    'just inside' => [0.002697, true],
    'just outside' => [0.002700, false],
]);

it('refuses a report with a coarse location fix (D-009, C7)', function () {
    $project = projectWithMeasurements();

    $this->actingAs(citizen())
        ->post(route('reports.store', SUBMIT_SLUG), reportPayload($project, overrides: ['accuracy_m' => 100.5]))
        ->assertSessionHasErrors([
            'accuracy_m' => 'Sinyal lokasi lemah (ketelitian lebih dari 100 m). Tunggu sebentar di tempat terbuka, lalu kirim lagi.',
        ]);

    expect(Report::query()->count())->toBe(0);
});

it('accepts the edge case of 280 m with 90 m accuracy (PRD Fitur Inti 9)', function () {
    $project = projectWithMeasurements();

    // 0.002518 degree is about 280 m.
    $this->actingAs(citizen())
        ->post(route('reports.store', SUBMIT_SLUG), reportPayload($project, overrides: ['lat' => -8.5321 + 0.002518, 'accuracy_m' => 90]))
        ->assertSessionHasNoErrors();

    expect((float) Report::query()->sole()->distance_m)->toEqualWithDelta(280.0, 1.0);
});

it('limits submissions per IP address across accounts (D-010)', function () {
    config(['sonar.report.submits_per_minute' => 2]);
    $project = projectWithMeasurements();
    $url = route('reports.store', SUBMIT_SLUG);

    $this->actingAs(citizen())->post($url, reportPayload($project))->assertSessionHasNoErrors();
    $this->actingAs(citizen())->post($url, reportPayload($project))->assertSessionHasNoErrors();

    $this->actingAs(citizen())->post($url, reportPayload($project))->assertStatus(429);
    expect(Report::query()->count())->toBe(2);
});

it('saves every answer with the measured value in the RAB unit, and the photos as WebP (M4-02, C5)', function () {
    $project = projectWithMeasurements();
    [$thickness, $width] = $project->measurements()->orderBy('id')->get()->all();
    $answers = [
        ['measurement_id' => $thickness->id, 'answer' => 'mismatch', 'measured_value' => '8,5'],
        // A value sent with "Sesuai" is dropped.
        ['measurement_id' => $width->id, 'answer' => 'match', 'measured_value' => '9'],
    ];
    $photos = [UploadedFile::fake()->image('a.jpg', 3200, 2400), UploadedFile::fake()->image('b.jpg', 640, 480)];

    $this->actingAs(citizen())
        ->post(route('reports.store', SUBMIT_SLUG), reportPayload($project, $answers, ['photos' => $photos]))
        ->assertSessionHasNoErrors();

    $report = Report::query()->sole();
    $saved = ReportAnswer::query()->where('report_id', $report->id)->orderBy('measurement_id')->get();
    expect($saved)->toHaveCount(2)
        ->and($saved[0]->answer)->toBe(MeasurementAnswer::Mismatch)
        ->and($saved[0]->measured_value)->toBe('8.500')
        ->and($saved[0]->measured_unit)->toBe('cm')
        ->and($saved[1]->answer)->toBe(MeasurementAnswer::Match)
        ->and($saved[1]->measured_value)->toBeNull()
        ->and($saved[1]->measured_unit)->toBeNull();

    $savedPhotos = ReportPhoto::query()->where('report_id', $report->id)->orderBy('position')->get();
    expect($savedPhotos->pluck('position')->all())->toBe([1, 2]);
    foreach ($savedPhotos as $photo) {
        Storage::disk('local')->assertExists($photo->path);
        expect($photo->path)->toStartWith('reports/')->toEndWith('.webp');
    }
    [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($savedPhotos[0]->path));
    expect(max($width, $height))->toBe(1600);
});

it('leaves no photo file behind when the send is refused', function () {
    $project = projectWithMeasurements();
    $warga = citizen();
    Report::factory()->count(3)->create(['project_id' => $project->id, 'user_id' => $warga->id, 'superseded_by' => 999]);

    $this->actingAs($warga)->post(route('reports.store', SUBMIT_SLUG), reportPayload($project))->assertSessionHasErrors('report');

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('refuses a send from a device another account already reported this project with (D-010)', function () {
    $project = projectWithMeasurements();
    $url = route('reports.store', SUBMIT_SLUG);

    $this->withCookie(EnsureDeviceCookie::COOKIE, deviceToken('hp1'))->actingAs(citizen())
        ->post($url, reportPayload($project))->assertSessionHasNoErrors();
    $this->withCookie(EnsureDeviceCookie::COOKIE, deviceToken('hp1'))->actingAs(citizen())
        ->post($url, reportPayload($project))
        ->assertSessionHasErrors(['device' => 'Perangkat ini sudah dipakai akun lain untuk melaporkan proyek ini. Satu perangkat hanya untuk satu pelapor.']);

    expect(Report::query()->count())->toBe(1);
});

it('lets the same account on the same device replace its report, and other devices report freely (D-010)', function () {
    $project = projectWithMeasurements();
    $warga = citizen();
    $url = route('reports.store', SUBMIT_SLUG);

    $this->withCookie(EnsureDeviceCookie::COOKIE, deviceToken('hp1'))->actingAs($warga)->post($url, reportPayload($project))->assertSessionHasNoErrors();
    $this->withCookie(EnsureDeviceCookie::COOKIE, deviceToken('hp1'))->actingAs($warga)->post($url, reportPayload($project))->assertSessionHasNoErrors();
    $this->withCookie(EnsureDeviceCookie::COOKIE, deviceToken('hp2'))->actingAs(citizen())->post($url, reportPayload($project))->assertSessionHasNoErrors();

    expect(Report::query()->count())->toBe(3)
        ->and(Report::query()->whereNull('superseded_by')->count())->toBe(2);
});

it('stores only a hash of the device cookie, not the cookie itself (D-010)', function () {
    $project = projectWithMeasurements();

    $this->withCookie(EnsureDeviceCookie::COOKIE, deviceToken('hp1'))->actingAs(citizen())
        ->post(route('reports.store', SUBMIT_SLUG), reportPayload($project))->assertSessionHasNoErrors();

    $hash = Report::query()->sole()->device_hash;
    expect($hash)->toMatch('/^[0-9a-f]{64}$/')->not->toBe(deviceToken('hp1'));
});

it('gives the checklist page a device cookie (D-010)', function () {
    Project::factory()->create(['slug' => SUBMIT_SLUG]);

    $this->actingAs(citizen())->get(route('reports.check', SUBMIT_SLUG))
        ->assertOk()
        ->assertCookie(EnsureDeviceCookie::COOKIE);
});

it('allows only one active report per account and per device in a cycle, in the database (F4-4)', function (array $second) {
    $project = projectWithMeasurements();
    $warga = citizen();
    Report::factory()->create(['project_id' => $project->id, 'user_id' => $warga->id, 'device_hash' => str_repeat('a', 64)]);

    $insertSecond = fn () => Report::factory()->create([
        'project_id' => $project->id,
        'user_id' => $second['same_user'] ? $warga->id : citizen()->id,
        'device_hash' => $second['device_hash'],
    ]);

    expect($insertSecond)->toThrow(UniqueConstraintViolationException::class);
})->with([
    'same account, no cycle yet' => [['same_user' => true, 'device_hash' => str_repeat('b', 64)]],
    'same device, another account' => [['same_user' => false, 'device_hash' => str_repeat('a', 64)]],
]);

it('keeps the reporter, photos, and answers off the public project page (C11)', function () {
    $project = projectWithMeasurements();
    $warga = User::factory()->create(['email' => 'pelapor.rahasia@contoh.test']);
    $answers = answersFor($project, 'mismatch', 'match');
    $answers[0]['measured_value'] = '7,25';

    $this->actingAs($warga)
        ->post(route('reports.store', SUBMIT_SLUG), reportPayload($project, $answers, ['note' => 'Catatan rahasia pelapor']))
        ->assertSessionHasNoErrors();
    $photoPath = ReportPhoto::query()->sole()->path;

    $this->flushSession();
    auth()->logout();
    $content = (string) $this->get(route('projects.show', SUBMIT_SLUG))->assertOk()->getContent();

    expect($content)
        ->not->toContain('pelapor.rahasia@contoh.test')
        ->not->toContain('Catatan rahasia pelapor')
        ->not->toContain($photoPath)
        ->not->toContain('7.250')
        ->not->toContain('7,25');
});

it('supersedes the earlier active report of the same account in the same cycle (C10)', function () {
    $project = projectWithMeasurements();
    $warga = citizen();
    $url = route('reports.store', SUBMIT_SLUG);

    $this->actingAs($warga)->post($url, reportPayload($project, answersFor($project, 'match', 'match')));
    $this->actingAs($warga)->post($url, reportPayload($project, answersFor($project, 'match', 'mismatch')));

    [$first, $second] = Report::query()->orderBy('id')->get()->all();
    expect($first->superseded_by)->toBe($second->id)
        ->and($second->superseded_by)->toBeNull()
        ->and($second->kind)->toBe(ReportKind::Mismatch)
        ->and(Report::query()->whereNull('superseded_by')->count())->toBe(1);
});

it('does not supersede reports of another account or of an earlier cycle', function () {
    $project = projectWithMeasurements();
    $warga = citizen();
    $oldCycle = ReviewCycle::create(['project_id' => $project->id, 'opened_at' => now()->subMonth(), 'closed_at' => now()->subWeek()]);
    $newCycle = ReviewCycle::create(['project_id' => $project->id, 'opened_at' => now()->subDay()]);
    $project->update(['current_cycle_id' => $newCycle->id]);
    $fromOldCycle = Report::factory()->create(['project_id' => $project->id, 'user_id' => $warga->id, 'cycle_id' => $oldCycle->id]);
    $fromNeighbour = Report::factory()->create(['project_id' => $project->id, 'cycle_id' => $newCycle->id]);

    $this->actingAs($warga)->post(route('reports.store', SUBMIT_SLUG), reportPayload($project))->assertSessionHasNoErrors();

    expect($fromOldCycle->fresh()->superseded_by)->toBeNull()
        ->and($fromNeighbour->fresh()->superseded_by)->toBeNull();
});

it('refuses the fourth send of the day from one account on one project (C10)', function () {
    $project = projectWithMeasurements();
    $warga = citizen();
    $url = route('reports.store', SUBMIT_SLUG);

    foreach (range(1, 3) as $send) {
        $this->actingAs($warga)->post($url, reportPayload($project))->assertSessionHasNoErrors();
    }

    $this->actingAs($warga)->post($url, reportPayload($project))
        ->assertSessionHasErrors(['report' => 'Kamu sudah mengirim 3 laporan untuk proyek ini hari ini. Coba lagi besok.']);
    expect(Report::query()->count())->toBe(3);
});

it('resets the daily limit at midnight WITA, not UTC', function () {
    $project = projectWithMeasurements();
    $warga = citizen();
    $url = route('reports.store', SUBMIT_SLUG);

    // 15:59 UTC is 23:59 WITA.
    $this->travelTo('2026-11-21 15:59:00');
    foreach (range(1, 3) as $send) {
        $this->actingAs($warga)->post($url, reportPayload($project))->assertSessionHasNoErrors();
    }

    // 16:01 UTC is 00:01 WITA the next day, still the same UTC date.
    $this->travelTo('2026-11-21 16:01:00');
    $this->actingAs($warga)->post($url, reportPayload($project))->assertSessionHasNoErrors();

    expect(Report::query()->count())->toBe(4);
});

it('counts the daily limit per project', function () {
    $project = projectWithMeasurements();
    $warga = citizen();
    Report::factory()->count(3)->create(['user_id' => $warga->id]);

    $this->actingAs($warga)->post(route('reports.store', SUBMIT_SLUG), reportPayload($project))->assertSessionHasNoErrors();

    expect(Report::query()->where('project_id', $project->id)->count())->toBe(1);
});

it('sends a guest to the sign-in page and saves nothing', function () {
    $project = projectWithMeasurements();

    $this->post(route('reports.store', SUBMIT_SLUG), reportPayload($project))->assertRedirect(route('login'));

    expect(Report::query()->count())->toBe(0);
});

it('forbids a desa account from sending a report', function () {
    $project = projectWithMeasurements();
    $official = User::factory()->desa()->create(['village_id' => $project->village_id]);

    $this->actingAs($official)->post(route('reports.store', SUBMIT_SLUG), reportPayload($project))->assertForbidden();

    expect(Report::query()->count())->toBe(0);
});

it('returns 404 for a project that cannot take reports', function (Closure $makeProject) {
    $makeProject();

    $this->actingAs(citizen())->post(route('reports.store', SUBMIT_SLUG), [])->assertNotFound();

    expect(Report::query()->count())->toBe(0);
})->with([
    'unknown' => [fn () => null],
    'not published' => [fn () => projectWithMeasurements(['published_at' => null])],
    'without a location' => [fn () => projectWithMeasurements(['lat' => null, 'lng' => null])],
    "another visitor's demo" => [fn () => projectWithMeasurements(['village_id' => Village::factory()->create(['is_demo' => true])->id])],
]);

it('requires answers, a photo, and the location', function () {
    projectWithMeasurements();

    $this->actingAs(citizen())->post(route('reports.store', SUBMIT_SLUG), [])->assertSessionHasErrors([
        'answers' => 'Jawab semua ukuran sebelum mengirim.',
        'photos' => 'Ambil minimal 1 foto dari kamera.',
        'lat' => 'Lokasi belum terbaca. Izinkan lokasi, lalu kirim lagi.',
        'lng' => 'Lokasi belum terbaca. Izinkan lokasi, lalu kirim lagi.',
        'accuracy_m' => 'Lokasi belum terbaca. Izinkan lokasi, lalu kirim lagi.',
    ]);

    expect(Report::query()->count())->toBe(0);
});

it('accepts an optional empty measured value for a "Tidak" answer and snapshots target (D15)', function () {
    $project = projectWithMeasurements();
    $answers = answersFor($project, 'mismatch', 'unmeasurable');
    $answers[0]['measured_value'] = '';

    $this->actingAs(citizen())
        ->post(route('reports.store', SUBMIT_SLUG), reportPayload($project, $answers))
        ->assertSessionHasNoErrors();

    $report = Report::query()->sole();
    $saved = ReportAnswer::query()->where('report_id', $report->id)->orderBy('measurement_id')->get();
    expect($report->kind)->toBe(ReportKind::Mismatch)
        ->and($saved[0]->answer)->toBe(MeasurementAnswer::Mismatch)
        ->and($saved[0]->measured_value)->toBeNull()
        ->and($saved[0]->measured_unit)->toBeNull()
        ->and($saved[0]->target_value)->not->toBeNull()
        ->and($saved[0]->target_unit)->toBe('cm');
});

it('rejects an invalid non-empty measured value for a "Tidak" answer', function (string $measuredValue, string $message) {
    $project = projectWithMeasurements();
    $answers = answersFor($project, 'mismatch', 'match');
    $answers[0]['measured_value'] = $measuredValue;

    $this->actingAs(citizen())
        ->post(route('reports.store', SUBMIT_SLUG), reportPayload($project, $answers))
        ->assertSessionHasErrors(['answers.0.measured_value' => $message]);
})->with([
    'not a number' => ['delapan', 'Hasil ukur harus berupa angka, contoh: 8 atau 8,5.'],
    'zero' => ['0', 'Hasil ukur harus lebih dari 0.'],
]);

it('accepts a measured value written with a decimal comma', function () {
    $project = projectWithMeasurements();
    $answers = answersFor($project, 'mismatch', 'match');
    $answers[0]['measured_value'] = ' 8,5 ';

    $this->actingAs(citizen())
        ->post(route('reports.store', SUBMIT_SLUG), reportPayload($project, $answers))
        ->assertSessionHasNoErrors();
});

it('ignores a measured value sent with an answer other than "Tidak"', function () {
    $project = projectWithMeasurements();
    $answers = answersFor($project, 'match', 'match');
    $answers[0]['measured_value'] = 'bukan angka';

    $this->actingAs(citizen())
        ->post(route('reports.store', SUBMIT_SLUG), reportPayload($project, $answers))
        ->assertSessionHasNoErrors();
});

it('requires every measurement of the project to be answered exactly once (C4)', function (Closure $makeAnswers, string $field, string $message) {
    $project = projectWithMeasurements();

    $this->actingAs(citizen())
        ->post(route('reports.store', SUBMIT_SLUG), reportPayload($project, $makeAnswers($project)))
        ->assertSessionHasErrors([$field => $message]);

    expect(Report::query()->count())->toBe(0);
})->with([
    'one left out' => [
        fn (Project $project) => array_slice(answersFor($project, 'match', 'match'), 0, 1),
        'answers',
        'Jawab semua ukuran sebelum mengirim.',
    ],
    'one answered twice' => [
        fn (Project $project) => [answersFor($project, 'match', 'match')[0], answersFor($project, 'match', 'match')[0]],
        'answers.0.measurement_id',
        'Satu ukuran terjawab dua kali. Muat ulang halaman, lalu coba lagi.',
    ],
    "another project's measurement" => [
        fn (Project $project) => [
            answersFor($project, 'match', 'match')[0],
            ['measurement_id' => ProjectMeasurement::factory()->create()->id, 'answer' => 'match', 'measured_value' => ''],
        ],
        'answers.1.measurement_id',
        'Ada ukuran yang bukan milik proyek ini. Muat ulang halaman, lalu coba lagi.',
    ],
    'unknown answer' => [
        fn (Project $project) => [
            answersFor($project, 'match', 'match')[0],
            [...answersFor($project, 'match', 'match')[1], 'answer' => 'mungkin'],
        ],
        'answers.1.answer',
        'Pilih Sesuai, Tidak, atau Tidak bisa ukur.',
    ],
]);

it('accepts at most the configured number of photos (C6)', function () {
    $project = projectWithMeasurements();
    $photos = array_map(fn (int $i) => UploadedFile::fake()->image("foto-{$i}.jpg"), range(1, 4));

    $this->actingAs(citizen())
        ->post(route('reports.store', SUBMIT_SLUG), reportPayload($project, overrides: ['photos' => $photos]))
        ->assertSessionHasErrors(['photos' => 'Foto paling banyak 3.']);
});

it('refuses a photo that is not an image', function () {
    $project = projectWithMeasurements();
    $file = UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf');

    $this->actingAs(citizen())
        ->post(route('reports.store', SUBMIT_SLUG), reportPayload($project, overrides: ['photos' => [$file]]))
        ->assertSessionHasErrors(['photos.0' => 'Foto harus diambil dari kamera.']);
});

it('limits the note to the configured length', function () {
    $project = projectWithMeasurements();

    $this->actingAs(citizen())
        ->post(route('reports.store', SUBMIT_SLUG), reportPayload($project, overrides: ['note' => str_repeat('a', 281)]))
        ->assertSessionHasErrors(['note' => 'Catatan paling banyak 280 huruf.']);
});

it('stores an empty note as no note', function () {
    $project = projectWithMeasurements();

    $this->actingAs(citizen())
        ->post(route('reports.store', SUBMIT_SLUG), reportPayload($project, overrides: ['note' => '   ']))
        ->assertSessionHasNoErrors();

    expect(Report::query()->sole()->note)->toBeNull();
});
