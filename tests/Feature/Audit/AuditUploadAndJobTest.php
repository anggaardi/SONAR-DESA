<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Audit\Domain\PriceLabeler;
use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Jobs\ProcessRabDocumentJob;
use App\Modules\Audit\Models\Audit;
use App\Modules\Audit\Reading\RabReader;
use App\Modules\Audit\Reading\RabReadResult;
use App\Modules\Audit\Reading\RabReadRow;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Village;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();

    $this->village = Village::factory()->create();
    app(CurrentVillage::class)->set($this->village->id);

    $this->desaUser = User::factory()->create([
        'role' => Role::Desa,
        'village_id' => $this->village->id,
    ]);
});

it('requires authentication for audit upload endpoints', function () {
    $this->get('/audits/create')->assertRedirect(route('login'));
    $this->post('/audits', [])->assertRedirect(route('login'));
});

it('renders audit upload page for desa user', function () {
    $this->actingAs($this->desaUser)
        ->get('/audits/create')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('audit/Create')
            ->has('village')
            ->has('sampleAvailable')
        );
});

it('rejects file larger than 10MB or invalid format', function () {
    $oversized = UploadedFile::fake()->create('large.pdf', 11000, 'application/pdf');

    $this->actingAs($this->desaUser)
        ->post('/audits', ['file' => $oversized])
        ->assertSessionHasErrors(['file']);

    $invalidMime = UploadedFile::fake()->create('script.sh', 50, 'text/x-shellscript');

    $this->actingAs($this->desaUser)
        ->post('/audits', ['file' => $invalidMime])
        ->assertSessionHasErrors(['file']);
});

it('successfully uploads valid PDF, creates audit in reading status, and dispatches job', function () {
    $file = UploadedFile::fake()->create('rab-jalan.pdf', 500, 'application/pdf');

    $response = $this->actingAs($this->desaUser)
        ->post('/audits', ['file' => $file]);

    $audit = Audit::latest('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->status)->toBe(AuditStatus::Reading);
    expect($audit->source)->toBe('ocr_ai');
    expect(Storage::disk('local')->exists($audit->file_path))->toBeTrue();

    Queue::assertPushed(ProcessRabDocumentJob::class, function ($job) use ($audit) {
        return $job->auditId === $audit->id;
    });

    $response->assertRedirect(route('audits.show', $audit));
});

it('can retry reading when in read_failed status', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::ReadFailed,
        'error_code' => 'EXTRACTION_FAILED',
        'file_path' => 'audits/test.pdf',
    ]);

    $response = $this->actingAs($this->desaUser)
        ->post("/audits/{$audit->id}/retry");

    $audit->refresh();
    expect($audit->status)->toBe(AuditStatus::Reading);
    expect($audit->error_code)->toBeNull();

    Queue::assertPushed(ProcessRabDocumentJob::class, function ($job) use ($audit) {
        return $job->auditId === $audit->id;
    });

    $response->assertRedirect();
});

it('processes job and populates audit items with deterministic labels', function () {
    Storage::disk('local')->put('audits/doc.pdf', 'fake-pdf-content');

    $standard = PriceStandard::factory()->create([
        'code' => 'K-100',
        'name' => 'Semen Portland',
        'unit' => 'sak',
        'price_rp' => 70000,
        'regency' => $this->village->regency,
    ]);

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::Reading,
        'file_path' => 'audits/doc.pdf',
    ]);

    // Mock RabReader to return 2 extracted rows (1 normal, 1 yellow with doubt)
    $mockReader = mock(RabReader::class);
    $mockReader->shouldReceive('read')
        ->once()
        ->andReturn(new RabReadResult(
            rows: [
                new RabReadRow(
                    position: 1,
                    rawName: 'Semen Portland 50kg',
                    volume: '100',
                    unit: 'sak',
                    unitPriceRp: 70000,
                    amountRp: 7000000,
                    standardCode: 'K-100',
                    doubtFields: [],
                ),
                new RabReadRow(
                    position: 2,
                    rawName: 'Pasir Cor Khusus',
                    volume: '20',
                    unit: 'm3',
                    unitPriceRp: 250000,
                    amountRp: 5000000,
                    standardCode: null,
                    doubtFields: ['volume'],
                ),
            ],
            writtenTotalRp: 12000000,
            readMs: 1234,
        ));

    $job = new ProcessRabDocumentJob($audit->id);
    $job->handle($mockReader, new PriceLabeler);

    $audit->refresh();
    expect($audit->status)->toBe(AuditStatus::NeedsReview);
    expect($audit->written_total_rp)->toBe(12000000);
    expect($audit->read_ms)->toBe(1234);
    expect($audit->items)->toHaveCount(2);

    $item1 = $audit->items()->where('position', 1)->first();
    expect($item1->label)->toBe(PriceLabel::WithinStandard);

    $item2 = $audit->items()->where('position', 2)->first();
    expect($item2->label)->toBe(PriceLabel::NeedsCheck);
    expect($item2->doubt)->not->toBeNull();
});

it('allows manually adding an item to an audit', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::NeedsReview,
    ]);

    $this->actingAs($this->desaUser)
        ->post("/audits/{$audit->id}/items", [
            'raw_name' => 'Batu Belah',
            'volume' => '15',
            'unit' => 'm3',
            'unit_price_rp' => 180000,
        ])
        ->assertRedirect();

    $item = $audit->items()->where('raw_name', 'Batu Belah')->first();
    expect($item)->not->toBeNull();
    expect((float) $item->volume)->toBe(15.0);
    expect($item->unit_price_rp)->toBe(180000);
    expect($item->amount_rp)->toBe(2700000);
});

it('returns publish_block_reason in audit review summary', function () {
    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::Reading,
        'file_path' => 'audits/sample.pdf',
    ]);
    Storage::disk('local')->put('audits/sample.pdf', 'dummy');

    $this->actingAs($this->desaUser)
        ->get("/audits/{$audit->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('audit/Review')
            ->where('summary.publish_block_reason', 'reading')
            ->where('summary.can_publish', false)
        );
});

it('returns in_progress_audits in desa dashboard', function () {
    Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::NeedsReview,
        'file_path' => 'audits/doc1.pdf',
    ]);

    $this->actingAs($this->desaUser)
        ->get('/desa')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('desa/Dashboard')
            ->has('in_progress_audits', 1)
        );
});

it('allows desa user to replace an unpublished audit file and re-dispatches extraction job', function () {
    Storage::disk('local')->put('audits/old-file.pdf', 'old-content');

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::ReadFailed,
        'file_path' => 'audits/old-file.pdf',
        'error_code' => 'NO_RAB_ITEMS_FOUND',
    ]);

    $newFile = UploadedFile::fake()->create('rab-baru.pdf', 300, 'application/pdf');

    $this->actingAs($this->desaUser)
        ->post("/audits/{$audit->id}/replace-file", ['file' => $newFile])
        ->assertRedirect(route('audits.show', $audit));

    $audit->refresh();
    expect($audit->status)->toBe(AuditStatus::Reading)
        ->and($audit->error_code)->toBeNull()
        ->and($audit->file_path)->not->toBe('audits/old-file.pdf')
        ->and(Storage::disk('local')->exists('audits/old-file.pdf'))->toBeFalse()
        ->and(Storage::disk('local')->exists((string) $audit->file_path))->toBeTrue();

    Queue::assertPushed(ProcessRabDocumentJob::class, fn ($job) => $job->auditId === $audit->id);
});

it('allows desa user to delete an unpublished audit and its stored file, but forbids deleting published audit', function () {
    Storage::disk('local')->put('audits/to-delete.pdf', 'delete-me');

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::NeedsReview,
        'file_path' => 'audits/to-delete.pdf',
    ]);

    $this->actingAs($this->desaUser)
        ->delete("/audits/{$audit->id}")
        ->assertRedirect(route('desa.dashboard'));

    expect(Audit::find($audit->id))->toBeNull()
        ->and(Storage::disk('local')->exists('audits/to-delete.pdf'))->toBeFalse();

    $publishedAudit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::Published,
        'file_path' => 'audits/published.pdf',
    ]);

    $this->actingAs($this->desaUser)
        ->delete("/audits/{$publishedAudit->id}")
        ->assertForbidden();

    $this->actingAs($this->desaUser)
        ->post("/audits/{$publishedAudit->id}/replace-file", [
            'file' => UploadedFile::fake()->create('hack.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();
});

it('enforces daily global and demo AI read budgets across upload, retry, replace-file, and job execution (D19, S7)', function () {
    config()->set('sonar.ai_budget.daily_reads', 1);
    config()->set('sonar.ai_budget.demo_daily_reads', 1);

    // 1st upload from normal village consumes the global daily limit.
    $file1 = UploadedFile::fake()->create('rab-1.pdf', 200, 'application/pdf');
    $this->actingAs($this->desaUser)
        ->post('/audits', ['file' => $file1])
        ->assertRedirect();

    $firstAudit = Audit::latest('id')->firstOrFail();
    expect($firstAudit->status)->toBe(AuditStatus::Reading)
        ->and($firstAudit->error_code)->toBeNull()
        ->and(DB::table('ai_usage')->sum('reads_count'))->toBe(1);

    // 2nd upload is blocked cleanly without queuing AI and marked AI_BUDGET.
    $file2 = UploadedFile::fake()->create('rab-2.pdf', 200, 'application/pdf');
    $this->actingAs($this->desaUser)
        ->post('/audits', ['file' => $file2])
        ->assertRedirect()
        ->assertSessionHas('error');

    $blockedAudit = Audit::latest('id')->firstOrFail();
    expect($blockedAudit->id)->not->toBe($firstAudit->id)
        ->and($blockedAudit->status)->toBe(AuditStatus::ReadFailed)
        ->and($blockedAudit->error_code)->toBe('AI_BUDGET');

    Queue::assertPushed(ProcessRabDocumentJob::class, 1);

    // Retry and replace-file must also respect AI_BUDGET without dispatching jobs.
    $this->actingAs($this->desaUser)
        ->post("/audits/{$blockedAudit->id}/retry")
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->actingAs($this->desaUser)
        ->post("/audits/{$blockedAudit->id}/replace-file", [
            'file' => UploadedFile::fake()->create('rab-3.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect(route('audits.show', $blockedAudit))
        ->assertSessionHas('error');

    Queue::assertPushed(ProcessRabDocumentJob::class, 1);

    // Demo village cap is enforced separately from real villages when demo_daily_reads < daily_reads.
    config()->set('sonar.ai_budget.daily_reads', 5);
    config()->set('sonar.ai_budget.demo_daily_reads', 1);

    $demoVillage = Village::factory()->create(['is_demo' => true, 'demo_ai_uploads_count' => 0]);
    $demoDesa = User::factory()->create(['role' => Role::Desa, 'village_id' => $demoVillage->id]);

    $this->actingAs($demoDesa)
        ->post('/audits', ['file' => UploadedFile::fake()->create('demo-1.pdf', 200, 'application/pdf')])
        ->assertRedirect();
    expect(DB::table('ai_usage')->sum('demo_reads_count'))->toBe(1);

    // 2nd demo upload hits demo_daily_reads=1 without burning demo_ai_uploads_count.
    $this->actingAs($demoDesa)
        ->post('/audits', ['file' => UploadedFile::fake()->create('demo-2.pdf', 200, 'application/pdf')])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Audit::withoutGlobalScopes()->where('village_id', $demoVillage->id)->latest('id')->firstOrFail()->error_code)
        ->toBe('AI_BUDGET')
        ->and($demoVillage->fresh()->demo_ai_uploads_count)->toBe(1);

    // Real village can still use remaining global budget (2 < 5).
    $this->actingAs($this->desaUser)
        ->post('/audits', ['file' => UploadedFile::fake()->create('real-still-ok.pdf', 200, 'application/pdf')])
        ->assertRedirect();

    expect(Audit::latest('id')->firstOrFail()->status)->toBe(AuditStatus::Reading);

    // Direct job execution when unreserved enforces AiReadBudget and writes AI_BUDGET.
    Storage::disk('local')->put('audits/direct.pdf', 'pdf');
    config()->set('sonar.ai_budget.daily_reads', 0);
    $directAudit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::Reading,
        'source' => 'ocr_ai',
        'file_path' => 'audits/direct.pdf',
    ]);

    $neverCalledReader = mock(RabReader::class);
    $neverCalledReader->shouldNotReceive('read');

    (new ProcessRabDocumentJob($directAudit->id))->handle($neverCalledReader, new PriceLabeler);

    expect($directAudit->refresh()->status)->toBe(AuditStatus::ReadFailed)
        ->and($directAudit->error_code)->toBe('AI_BUDGET');
});

it('aligns timeout defaults and records AI_TIMEOUT when ProcessRabDocumentJob fails (D18, S7)', function () {
    expect((int) config('sonar.rab_reader.timeout_seconds'))->toBe(60)
        ->and((int) config('queue.connections.database.retry_after'))->toBe(120);

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::Reading,
        'file_path' => 'audits/slow.pdf',
    ]);

    $job = new ProcessRabDocumentJob($audit->id);
    expect($job->timeout)->toBe(75);

    $job->failed(new TimeoutExceededException('Job timed out'));

    expect($audit->refresh()->status)->toBe(AuditStatus::ReadFailed)
        ->and($audit->error_code)->toBe('AI_TIMEOUT');
});

it('filters price standards strictly by regency and year in ProcessRabDocumentJob and AuditItemController (S7 P3)', function () {
    Storage::disk('local')->put('audits/gianyar-doc.pdf', 'fake-pdf');

    $gianyar2026 = PriceStandard::factory()->create([
        'regency' => 'Gianyar',
        'year' => 2026,
        'code' => 'MAT-SMN-01',
        'name' => 'Semen Gianyar 2026',
        'unit' => 'sak',
        'price_rp' => 72000,
    ]);
    PriceStandard::factory()->create([
        'regency' => 'Gianyar',
        'year' => 2024,
        'code' => 'MAT-SMN-01',
        'name' => 'Semen Gianyar 2024',
        'unit' => 'sak',
        'price_rp' => 60000,
    ]);
    $badung2026 = PriceStandard::factory()->create([
        'regency' => 'Badung',
        'year' => 2026,
        'code' => 'MAT-SMN-01',
        'name' => 'Semen Badung 2026',
        'unit' => 'sak',
        'price_rp' => 85000,
    ]);

    $this->village->update(['regency' => 'Gianyar']);

    $audit = Audit::factory()->create([
        'village_id' => $this->village->id,
        'status' => AuditStatus::Reading,
        'source' => 'ocr_ai',
        'file_path' => 'audits/gianyar-doc.pdf',
    ]);

    $mockReader = mock(RabReader::class);
    $mockReader->shouldReceive('read')
        ->once()
        ->withArgs(fn (string $path, array $refs): bool => count($refs) === 1 && $refs[0]->name === 'Semen Gianyar 2026')
        ->andReturn(new RabReadResult(
            rows: [
                new RabReadRow(
                    position: 1,
                    rawName: 'Semen Portland',
                    volume: '10',
                    unit: 'sak',
                    unitPriceRp: 72000,
                    amountRp: 720000,
                    standardCode: 'MAT-SMN-01',
                    doubtFields: [],
                ),
            ],
            writtenTotalRp: 720000,
            readMs: 500,
        ));

    (new ProcessRabDocumentJob($audit->id, $audit->village_id, true))->handle($mockReader, new PriceLabeler);

    $item = $audit->items()->firstOrFail();
    expect($item->standard_id)->toBe($gianyar2026->id)
        ->and($item->label)->toBe(PriceLabel::WithinStandard);

    // Manual item addition/update must also forbid cross-regency standard_id.
    $this->actingAs($this->desaUser)
        ->post("/audits/{$audit->id}/items", [
            'raw_name' => 'Semen Ilegal',
            'volume' => '5',
            'unit' => 'sak',
            'unit_price_rp' => 70000,
            'standard_id' => $badung2026->id,
        ])
        ->assertSessionHasErrors('standard_id');
});
