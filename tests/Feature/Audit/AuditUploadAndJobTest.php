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
