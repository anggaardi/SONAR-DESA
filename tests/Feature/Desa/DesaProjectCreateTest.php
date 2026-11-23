<?php

declare(strict_types=1);

use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Enums\MeasurementOrigin;
use App\Modules\Project\Enums\ProjectType;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
});

it('redirects guests attempting to access the project create form to login', function () {
    $this->get('/desa/projects/create')
        ->assertRedirect(route('login'));

    $this->post('/desa/projects', [])
        ->assertRedirect(route('login'));
});

it('forbids warga and bpd from accessing the project create form and submitting projects', function (Role $role) {
    $village = Village::factory()->create();
    $user = User::factory()->create([
        'role' => $role,
        'village_id' => $role->belongsToVillage() ? $village->id : null,
    ]);

    $this->actingAs($user)
        ->get('/desa/projects/create')
        ->assertForbidden();

    $this->actingAs($user)
        ->post('/desa/projects', [
            'name' => 'Proyek Ilegal',
            'type' => ProjectType::RabatBeton->value,
            'fund_source' => 'Dana Desa (DDS)',
            'year' => 2026,
            'amount_rp' => 150000000,
            'measurements' => [
                ['name' => 'Tebal', 'value' => 12, 'unit' => 'cm'],
            ],
        ])
        ->assertForbidden();
})->with([Role::Warga, Role::Bpd]);

it('renders the project create form with types, templates, and price standards for desa users', function () {
    $village = Village::factory()->create(['name' => 'Desa Melinggih', 'slug' => 'desa-melinggih']);
    $desa = User::factory()->desa($village)->create();

    $this->actingAs($desa)
        ->get('/desa/projects/create')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('desa/ProjectCreate')
            ->has('village', fn (AssertableInertia $v) => $v
                ->where('name', 'Desa Melinggih')
                ->where('slug', 'desa-melinggih')
                ->etc()
            )
            ->has('types')
            ->has('templates')
            ->has('fundSources')
            ->has('priceStandards')
        );
});

it('validates required fields including file and items when creating a project (D8, D9)', function () {
    $village = Village::factory()->create();
    $desa = User::factory()->desa($village)->create();

    $this->actingAs($desa)
        ->post('/desa/projects', [])
        ->assertSessionHasErrors([
            'file',
            'name',
            'type',
            'fund_source',
            'year',
            'items',
            'measurements',
        ]);
});

it('validates measurement items when creating a project', function () {
    $village = Village::factory()->create();
    $desa = User::factory()->desa($village)->create();

    $file = UploadedFile::fake()->create('rab.pdf', 200, 'application/pdf');

    $this->actingAs($desa)
        ->post('/desa/projects', [
            'file' => $file,
            'name' => 'Betonisasi Jalan Banjar',
            'type' => ProjectType::RabatBeton->value,
            'fund_source' => 'Dana Desa (DDS)',
            'year' => 2026,
            'items' => [
                [
                    'raw_name' => 'Semen Portland',
                    'volume' => 10,
                    'unit' => 'sak',
                    'unit_price_rp' => 70000,
                ],
            ],
            'measurements' => [
                ['name' => '', 'value' => 0, 'unit' => ''],
            ],
        ])
        ->assertSessionHasErrors([
            'measurements.0.name',
            'measurements.0.value',
            'measurements.0.unit',
        ]);
});

it('allows desa users to save a project as draft with file and items (D9)', function () {
    $village = Village::factory()->create(['name' => 'Desa Melinggih', 'slug' => 'desa-melinggih']);
    $desa = User::factory()->desa($village)->create();

    $standard = PriceStandard::factory()->create([
        'code' => 'MAT-SMN-01',
        'name' => 'Semen Portland Komposit 50kg',
        'unit' => 'sak',
        'price_rp' => 72000,
    ]);

    $file = UploadedFile::fake()->create('rab-gedung.pdf', 500, 'application/pdf');

    $payload = [
        'file' => $file,
        'name' => 'Pembangunan Balai Banjar Kaja',
        'type' => ProjectType::Gedung->value,
        'fund_source' => 'Alokasi Dana Desa (ADD)',
        'year' => 2026,
        'lat' => -8.541234,
        'lng' => 115.321456,
        'is_published' => false,
        'items' => [
            [
                'raw_name' => 'Semen Portland 50kg',
                'volume' => 100,
                'unit' => 'sak',
                'unit_price_rp' => 70000,
                'standard_id' => $standard->id,
            ],
        ],
        'measurements' => [
            [
                'name' => 'Panjang Bangunan',
                'value' => 15.0,
                'unit' => 'meter',
                'how_to' => 'Ukur sisi terpanjang balai',
                'origin' => MeasurementOrigin::Template->value,
            ],
            [
                'name' => 'Lebar Bangunan',
                'value' => 10.0,
                'unit' => 'meter',
                'how_to' => 'Ukur sisi terpendek balai',
                'origin' => MeasurementOrigin::Template->value,
            ],
            [
                'name' => 'Tinggi Dinding Bangunan',
                'value' => 3.8,
                'unit' => 'meter',
                'how_to' => 'Ukur dari lantai ke balok atas',
                'origin' => MeasurementOrigin::Template->value,
            ],
        ],
    ];

    $response = $this->actingAs($desa)->post('/desa/projects', $payload);

    $project = Project::where('name', 'Pembangunan Balai Banjar Kaja')->first();
    expect($project)->not->toBeNull();

    $response->assertRedirect(route('audits.show', $project->audit_id))
        ->assertSessionHas('success');
    expect($project->village_id)->toBe($village->id);
    expect($project->type)->toBe(ProjectType::Gedung);
    expect($project->fund_source)->toBe('Alokasi Dana Desa (ADD)');
    expect($project->year)->toBe(2026);
    expect($project->published_at)->toBeNull();
    expect($project->isPublished())->toBeFalse();

    // Verify Audit record & physical file path (D9)
    $audit = Audit::find($project->audit_id);
    expect($audit)->not->toBeNull();
    expect($audit->file_path)->not->toBeNull();
    expect($audit->source)->toBe('manual');
    expect($audit->written_total_rp)->toBe(7000000);
    expect($audit->items()->count())->toBe(1);

    // Verify Measurements
    expect(ProjectMeasurement::where('project_id', $project->id)->count())->toBe(3);

    // DoD Check: Draft must NOT appear in public village page or public project page
    $this->get("/d/{$village->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('projects', 0)
        );

    $this->get("/p/{$project->slug}")
        ->assertNotFound();
});

it('fulfills DoD: directly published project routes through PublishProject and appears on citizen pages', function () {
    $village = Village::factory()->create(['name' => 'Desa Melinggih', 'slug' => 'desa-melinggih']);
    $desa = User::factory()->desa($village)->create();

    $standard = PriceStandard::factory()->create([
        'code' => 'MAT-SMN-01',
        'name' => 'Semen Portland Komposit 50kg',
        'unit' => 'sak',
        'price_rp' => 72000,
    ]);

    $file = UploadedFile::fake()->create('rab-rabat.pdf', 500, 'application/pdf');

    $payload = [
        'file' => $file,
        'name' => 'Betonisasi Jalan Usaha Tani Banjar Melinggih',
        'type' => ProjectType::RabatBeton->value,
        'fund_source' => 'Dana Desa (DDS)',
        'year' => 2026,
        'lat' => -8.541234,
        'lng' => 115.321456,
        'is_published' => true,
        'items' => [
            [
                'raw_name' => 'Semen Portland Komposit 50kg',
                'volume' => 2000,
                'unit' => 'sak',
                'unit_price_rp' => 70000,
                'standard_id' => $standard->id,
            ],
            [
                'raw_name' => 'Pasir Pasang',
                'volume' => 100,
                'unit' => 'm3',
                'unit_price_rp' => 250000,
                'standard_id' => null, // Tanpa acuan
            ],
        ],
        'measurements' => [
            [
                'name' => 'Ketebalan Beton Cor',
                'value' => 12.0,
                'unit' => 'cm',
                'how_to' => 'Ukur ketebalan cor di batas bekisting tepi jalan.',
                'origin' => MeasurementOrigin::Template->value,
            ],
            [
                'name' => 'Lebar Badan Jalan',
                'value' => 2.5,
                'unit' => 'meter',
                'how_to' => 'Rentangkan meteran tegak lurus dari tepi kiri ke tepi kanan.',
                'origin' => MeasurementOrigin::Template->value,
            ],
            [
                'name' => 'Panjang Total Pekerjaan',
                'value' => 450.0,
                'unit' => 'meter',
                'how_to' => 'Ukur bentang jalan dari titik nol sampai titik akhir.',
                'origin' => MeasurementOrigin::Template->value,
            ],
        ],
    ];

    $response = $this->actingAs($desa)->post('/desa/projects', $payload);

    $project = Project::where('name', 'Betonisasi Jalan Usaha Tani Banjar Melinggih')->first();
    expect($project)->not->toBeNull();
    expect($project->isPublished())->toBeTrue();
    expect($project->published_at)->not->toBeNull();

    $response->assertRedirect(route('projects.show', $project->slug))
        ->assertSessionHas('success');

    // -------------------------------------------------------------
    // DoD Verification 1: Citizen Directory (/d/{village_slug})
    // -------------------------------------------------------------
    $expectedTotal = (2000 * 70000) + (100 * 250000); // 140.000.000 + 25.000.000 = 165.000.000

    $this->get("/d/{$village->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('project/Village')
            ->where('village.name', 'Desa Melinggih')
            ->has('projects', 1)
            ->where('projects.0.slug', $project->slug)
            ->where('projects.0.name', 'Betonisasi Jalan Usaha Tani Banjar Melinggih')
            ->where('projects.0.fund_source', 'Dana Desa (DDS)')
            ->where('projects.0.year', 2026)
            ->where('projects.0.total_amount_rp', $expectedTotal)
        );

    // -------------------------------------------------------------
    // DoD Verification 2: Citizen Project Detail (/p/{project_slug})
    // -------------------------------------------------------------
    $this->get("/p/{$project->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('project/Show')
            ->where('project.name', 'Betonisasi Jalan Usaha Tani Banjar Melinggih')
            ->where('project.year', 2026)
            ->where('project.fund_source', 'Dana Desa (DDS)')
            ->where('project.total_amount_rp', $expectedTotal)
            ->has('project.measurements', 3)
            ->where('project.measurements.0.name', 'Ketebalan Beton Cor')
            ->where('project.measurements.0.value', '12')
            ->where('project.measurements.0.unit', 'cm')
        );
});
