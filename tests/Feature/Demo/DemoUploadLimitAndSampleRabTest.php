<?php

declare(strict_types=1);

use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Jobs\ProcessRabDocumentJob;
use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
});

it('limits custom AI uploads to 3 per demo session and directs the 4th to sample RABs (M6-05, M6)', function () {
    $this->seed(DatabaseSeeder::class);
    $this->post('/demo/start/desa');

    $demoVillage = Village::query()->where('is_demo', true)->sole();

    foreach (range(1, 3) as $attempt) {
        $file = UploadedFile::fake()->create("rab-{$attempt}.pdf", 200, 'application/pdf');
        $this->post('/audits', ['file' => $file])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    expect($demoVillage->fresh()->demo_ai_uploads_count)->toBe(3);

    // 4th custom upload is refused without queuing AI extraction.
    $fourthFile = UploadedFile::fake()->create('rab-4.pdf', 200, 'application/pdf');
    $this->from('/audits/create')
        ->post('/audits', ['file' => $fourthFile])
        ->assertRedirect('/audits/create')
        ->assertSessionHasErrors('file');

    expect(session('errors')->first('file'))->toContain('Batas demo tercapai');
    Queue::assertPushed(ProcessRabDocumentJob::class, 3);

    // Deleting an audit does not reset the demo upload quota.
    $latestAudit = Audit::withoutGlobalScopes()
        ->where('village_id', $demoVillage->id)
        ->where('source', 'ocr_ai')
        ->latest('id')
        ->firstOrFail();
    $this->delete("/audits/{$latestAudit->id}")->assertRedirect();

    $fifthFile = UploadedFile::fake()->create('rab-5.pdf', 200, 'application/pdf');
    $this->from('/audits/create')
        ->post('/audits', ['file' => $fifthFile])
        ->assertRedirect('/audits/create')
        ->assertSessionHasErrors('file');

    // Using the built-in sample RAB still works after reaching the custom upload limit.
    $this->post('/audits', ['use_sample' => true])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Queue::assertPushed(ProcessRabDocumentJob::class, 4);
});

it('does not burn demo upload quota when upload validation fails', function () {
    $this->seed(DatabaseSeeder::class);
    $this->post('/demo/start/desa');

    $demoVillage = Village::query()->where('is_demo', true)->sole();
    $invalid = UploadedFile::fake()->create('not-a-pdf.txt', 20, 'text/plain');

    $this->from('/audits/create')
        ->post('/audits', ['file' => $invalid])
        ->assertRedirect('/audits/create')
        ->assertSessionHasErrors('file');

    expect($demoVillage->fresh()->demo_ai_uploads_count)->toBe(0);
    Queue::assertNothingPushed();
});

it('blocks replace-file on demo villages once the 3-upload AI quota is spent', function () {
    $this->seed(DatabaseSeeder::class);
    $this->post('/demo/start/desa');

    $demoVillage = Village::query()->where('is_demo', true)->sole();
    $demoVillage->update(['demo_ai_uploads_count' => 3]);

    $audit = Audit::withoutGlobalScopes()
        ->where('village_id', $demoVillage->id)
        ->where('status', AuditStatus::Published)
        ->firstOrFail();
    $unpublished = Audit::withoutGlobalScopes()->create([
        'village_id' => $demoVillage->id,
        'owner_id' => $audit->owner_id,
        'status' => AuditStatus::ReadFailed,
        'source' => 'ocr_ai',
        'file_path' => 'audits/failed.pdf',
    ]);

    $replacement = UploadedFile::fake()->create('replacement.pdf', 200, 'application/pdf');
    $this->from("/audits/{$unpublished->id}")
        ->post("/audits/{$unpublished->id}/replace-file", ['file' => $replacement])
        ->assertRedirect("/audits/{$unpublished->id}")
        ->assertSessionHasErrors('file');

    Queue::assertNothingPushed();
});

it('does not cap uploads for real non-demo villages', function () {
    $realVillage = Village::factory()->create(['is_demo' => false]);
    $desa = User::factory()->create(['role' => Role::Desa, 'village_id' => $realVillage->id]);

    foreach (range(1, 4) as $attempt) {
        $file = UploadedFile::fake()->create("rab-real-{$attempt}.pdf", 200, 'application/pdf');
        $this->actingAs($desa)
            ->post('/audits', ['file' => $file])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    Queue::assertPushed(ProcessRabDocumentJob::class, 4);
});

it('opens pre-cached sample RAB 1, 2, 3 in one click as Pemerintah Desa without calling AI (M1, M3, S2-AC1)', function (
    string $sample,
    AuditStatus $expectedStatus,
    int $expectedItems,
) {
    $this->seed(DatabaseSeeder::class);

    $response = $this->post("/demo/contoh-rab/{$sample}");

    $demoVillage = Village::query()->where('is_demo', true)->sole();
    $audit = Audit::withoutGlobalScopes()
        ->where('village_id', $demoVillage->id)
        ->where('source', 'demo_sample')
        ->latest('id')
        ->firstOrFail();

    $response->assertRedirect(route('audits.show', $audit->id));
    expect(auth()->user()?->role)->toBe(Role::Desa)
        ->and($audit->status)->toBe($expectedStatus)
        ->and($audit->items()->count())->toBe($expectedItems)
        ->and($demoVillage->fresh()->demo_ai_uploads_count)->toBe(0);

    Queue::assertNothingPushed();

    $this->get(route('audits.show', $audit->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('audit/Review')
            ->where('demo.role', 'desa')
            ->where('demo.uploads_used', 0)
            ->where('demo.upload_limit', 3)
            ->has('items', $expectedItems));
})->with([
    'Contoh RAB 1 (Melati)' => ['1', AuditStatus::Ready, 14],
    'Contoh RAB 2 (Subak Uma)' => ['2', AuditStatus::NeedsReview, 15],
    'Contoh RAB 3 (Posyandu)' => ['3', AuditStatus::Ready, 15],
]);

it('rejects unknown sample RAB keys', function () {
    $this->post('/demo/contoh-rab/99')->assertNotFound();
});

it('marks sample RAB 2 multiplication error as NeedsCheck while sample RAB 1 is ready to publish', function () {
    $this->seed(DatabaseSeeder::class);

    $this->post('/demo/contoh-rab/1');
    $melatiAudit = Audit::withoutGlobalScopes()->where('source', 'demo_sample')->latest('id')->firstOrFail();
    expect($melatiAudit->items()->where('label', PriceLabel::AboveStandard)->count())->toBe(3)
        ->and($melatiAudit->items()->where('label', PriceLabel::WithinStandard)->count())->toBe(1)
        ->and($melatiAudit->items()->where('label', PriceLabel::NeedsCheck)->count())->toBe(0);

    $this->post('/demo/contoh-rab/2');
    $subakAudit = Audit::withoutGlobalScopes()->where('source', 'demo_sample')->latest('id')->firstOrFail();
    expect($subakAudit->items()->where('label', PriceLabel::NeedsCheck)->count())->toBe(1);
});
