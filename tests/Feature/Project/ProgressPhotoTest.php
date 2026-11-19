<?php

declare(strict_types=1);

use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Models\ProgressPhoto;
use App\Modules\Project\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

it('allows desa of the project village to upload progress photos and updates pct (P8, M3-05)', function () {
    Storage::fake('local');

    $village = Village::factory()->create();
    $desaUser = User::factory()->create([
        'role' => Role::Desa,
        'village_id' => $village->id,
    ]);

    $audit = Audit::factory()->create(['village_id' => $village->id]);
    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
        'progress_pct' => 10,
    ]);

    $photo1 = UploadedFile::fake()->image('photo1.jpg', 2000, 1500);
    $photo2 = UploadedFile::fake()->image('photo2.png', 800, 600);

    $response = $this->actingAs($desaUser)
        ->post("/projects/{$project->id}/progress", [
            'progress_pct' => 60,
            'photos' => [$photo1, $photo2],
        ]);

    $response->assertRedirect();

    $project->refresh();
    expect($project->progress_pct)->toBe(60);

    $savedPhotos = ProgressPhoto::where('project_id', $project->id)->get();
    expect($savedPhotos)->toHaveCount(2);

    $firstPhoto = $savedPhotos->first();
    expect($firstPhoto->progress_pct)->toBe(60);
    expect($firstPhoto->path)->toEndWith('.webp');

    Storage::disk('local')->assertExists($firstPhoto->path);

    // Verify resized <= 1600 px
    $manager = new ImageManager(new Driver);
    $decoded = $manager->decode(Storage::disk('local')->get($firstPhoto->path));
    expect($decoded->width())->toBeLessThanOrEqual(1600);
    expect($decoded->height())->toBeLessThanOrEqual(1600);
});

it('prevents another village desa from uploading progress photos (C1, IDOR)', function () {
    Storage::fake('local');

    $villageA = Village::factory()->create();
    $villageB = Village::factory()->create();

    $desaUserB = User::factory()->create([
        'role' => Role::Desa,
        'village_id' => $villageB->id,
    ]);

    $auditA = Audit::factory()->create(['village_id' => $villageA->id]);
    $projectA = Project::factory()->create([
        'village_id' => $villageA->id,
        'audit_id' => $auditA->id,
    ]);

    $photo = UploadedFile::fake()->image('photo.jpg', 800, 600);

    // Should return 404 because Village B cannot even resolve Village A's project
    $this->actingAs($desaUserB)
        ->post("/projects/{$projectA->id}/progress", [
            'progress_pct' => 50,
            'photos' => [$photo],
        ])
        ->assertNotFound();
});

it('prevents non-desa users from uploading progress photos', function () {
    $village = Village::factory()->create();
    $warga = User::factory()->create([
        'role' => Role::Warga,
        'village_id' => null,
    ]);

    $audit = Audit::factory()->create(['village_id' => $village->id]);
    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
    ]);

    $photo = UploadedFile::fake()->image('photo.jpg', 800, 600);

    $this->actingAs($warga)
        ->post("/projects/{$project->id}/progress", [
            'progress_pct' => 50,
            'photos' => [$photo],
        ])
        ->assertForbidden();
});

it('validates maximum 5 photos and progress percentage between 0 and 100', function () {
    $village = Village::factory()->create();
    $desaUser = User::factory()->create([
        'role' => Role::Desa,
        'village_id' => $village->id,
    ]);

    $audit = Audit::factory()->create(['village_id' => $village->id]);
    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
    ]);

    $photos = [];
    for ($i = 0; $i < 6; $i++) {
        $photos[] = UploadedFile::fake()->image("photo{$i}.jpg", 400, 300);
    }

    $this->actingAs($desaUser)
        ->post("/projects/{$project->id}/progress", [
            'progress_pct' => 150,
            'photos' => $photos,
        ])
        ->assertSessionHasErrors(['progress_pct', 'photos']);
});

it('serves progress photo via GET /media/progress/{photo} with cache headers', function () {
    Storage::fake('local');
    $village = Village::factory()->create();
    $audit = Audit::factory()->create(['village_id' => $village->id]);
    $project = Project::factory()->create([
        'village_id' => $village->id,
        'audit_id' => $audit->id,
    ]);

    $filePath = 'progress/sample.webp';
    Storage::disk('local')->put($filePath, 'fake-webp-bytes');

    $photo = ProgressPhoto::create([
        'project_id' => $project->id,
        'path' => $filePath,
        'progress_pct' => 45,
        'taken_at' => now(),
    ]);

    $response = $this->get("/media/progress/{$photo->id}")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');

    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('public');
    expect($cacheControl)->toContain('max-age=31536000');
    expect($cacheControl)->toContain('immutable');

    expect($response->streamedContent())->toBe('fake-webp-bytes');
});
