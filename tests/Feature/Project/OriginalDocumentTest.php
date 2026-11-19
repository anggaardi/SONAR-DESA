<?php

declare(strict_types=1);

use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Models\Project;
use Illuminate\Support\Facades\Storage;

it('streams original RAB document on GET /p/{slug}/dokumen (P11)', function () {
    Storage::fake('local');
    $filePath = 'audits/sample-rab.pdf';
    Storage::disk('local')->put($filePath, '%PDF-1.4 dummy content');

    $village = Village::factory()->create();
    $audit = Audit::factory()->create([
        'village_id' => $village->id,
        'file_path' => $filePath,
    ]);

    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
        'slug' => 'rabat-beton-dokumen',
        'published_at' => now(),
    ]);

    $response = $this->get('/p/rabat-beton-dokumen/dokumen');

    $response->assertOk();
    expect($response->streamedContent())->toBe('%PDF-1.4 dummy content');
});

it('returns 404 if project is draft or document file does not exist', function () {
    $village = Village::factory()->create();
    $audit = Audit::factory()->create([
        'village_id' => $village->id,
        'file_path' => null,
    ]);

    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
        'slug' => 'tanpa-dokumen',
        'published_at' => now(),
    ]);

    $this->get('/p/tanpa-dokumen/dokumen')->assertNotFound();

    $auditDraft = Audit::factory()->create(['village_id' => $village->id]);
    $projectDraft = Project::factory()->unpublished()->create([
        'village_id' => $village->id,
        'audit_id' => $auditDraft->id,
        'slug' => 'proyek-draft-dokumen',
    ]);

    $this->get('/p/proyek-draft-dokumen/dokumen')->assertNotFound();
});
