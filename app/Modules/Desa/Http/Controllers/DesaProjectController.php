<?php

declare(strict_types=1);

namespace App\Modules\Desa\Http\Controllers;

use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Village;
use App\Modules\Desa\Actions\CreateDesaProject;
use App\Modules\Desa\Http\Requests\StoreProjectRequest;
use App\Modules\Project\Enums\ProjectType;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DesaProjectController
{
    public function create(Request $request): Response
    {
        $villageId = app(CurrentVillage::class)->id();

        /** @var Village $village */
        $village = Village::findOrFail($villageId);

        $types = array_map(fn (ProjectType $t): array => [
            'value' => $t->value,
            'label' => $t->label(),
        ], ProjectType::cases());

        /** @var array<string, list<array{name: string, unit: string, default_value: string, how_to: string}>> $templates */
        $templates = config('sonar.measurement_templates', []);

        $fundSources = [
            'Dana Desa (DDS)',
            'Alokasi Dana Desa (ADD)',
            'Pendapatan Asli Desa (PADes)',
            'Bantuan Keuangan Khusus (BKK)',
            'Bagi Hasil Pajak & Retribusi (BHPR)',
            'Lainnya',
        ];

        $year = (int) ($request->query('year') ?? date('Y'));
        $priceStandards = PriceStandard::query()
            ->select(['id', 'code', 'name', 'unit', 'price_rp'])
            ->where('regency', $village->regency)
            ->where('year', $year)
            ->orderBy('name')
            ->get();

        return Inertia::render('desa/ProjectCreate', [
            'village' => [
                'id' => $village->id,
                'name' => $village->name,
                'slug' => $village->slug,
                'regency' => $village->regency,
            ],
            'types' => $types,
            'templates' => $templates,
            'fundSources' => $fundSources,
            'fund_sources' => $fundSources,
            'priceStandards' => $priceStandards,
        ]);
    }

    public function store(StoreProjectRequest $request, CreateDesaProject $action): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $validated['village_id'] = app(CurrentVillage::class)->id();
        $validated['owner_id'] = $user !== null ? $user->id : 0;
        $validated['type'] = ProjectType::from((string) $validated['type']);
        $validated['is_published'] = (bool) ($validated['is_published'] ?? false);
        $validated['file'] = $request->file('file');

        $project = $action->execute($validated);

        if ($project->isPublished()) {
            return redirect()
                ->route('projects.show', $project->slug)
                ->with('success', 'Proyek berhasil diterbitkan dan langsung dapat diakses warga.');
        }

        if ($validated['is_published'] ?? false) {
            return redirect()
                ->route('audits.show', $project->audit_id)
                ->with('warning', 'Draf proyek berhasil disimpan. Terdapat baris belanja yang perlu dikonfirmasi sebelum proyek dapat diterbitkan.');
        }

        return redirect()
            ->route('audits.show', $project->audit_id)
            ->with('success', 'Draf proyek dan dokumen audit berhasil disimpan.');
    }
}
