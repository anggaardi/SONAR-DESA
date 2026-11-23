<?php

declare(strict_types=1);

namespace App\Modules\Desa\Http\Controllers;

use App\Modules\Audit\Models\AuditItem;
use App\Modules\Project\Actions\ApplyProjectCco;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class DesaProjectManageController
{
    public function show(Request $request, Project $project): Response
    {
        Gate::authorize('manage', $project);

        $project->load([
            'village',
            'audit.items.standard',
            'measurements',
            'progressPhotos' => fn ($query) => $query->latest('id'),
            'projectChanges' => fn ($query) => $query->latest('changed_at'),
        ]);

        $standards = PriceStandard::where('regency', $project->village->regency)
            ->where('year', $project->year)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'unit', 'price_rp']);

        return Inertia::render('desa/ProjectManage', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'type' => $project->type->value,
                'fund_source' => $project->fund_source,
                'year' => $project->year,
                'lat' => $project->lat,
                'lng' => $project->lng,
                'progress_pct' => $project->progress_pct,
                'public_status' => $project->public_status->value,
                'is_published' => $project->isPublished(),
                'published_at' => $project->published_at?->toIso8601String(),
                'created_at' => $project->created_at?->toIso8601String(),
                'audit' => $project->audit ? [
                    'id' => $project->audit->id,
                    'status' => $project->audit->status->value,
                    'file_path' => $project->audit->file_path,
                    'written_total_rp' => $project->audit->written_total_rp,
                    'items' => $project->audit->items->map(fn (AuditItem $item) => [
                        'id' => $item->id,
                        'raw_name' => $item->raw_name,
                        'volume' => $item->volume,
                        'unit' => $item->unit,
                        'unit_price_rp' => $item->unit_price_rp,
                        'amount_rp' => $item->amount_rp,
                        'label' => $item->label->value,
                        'diff_rp' => $item->diff_rp,
                        'over_pct' => $item->over_pct,
                        'standard_id' => $item->standard_id,
                        'standard' => $item->standard ? [
                            'id' => $item->standard->id,
                            'name' => $item->standard->name,
                            'code' => $item->standard->code,
                            'unit' => $item->standard->unit,
                            'price_rp' => $item->standard->price_rp,
                        ] : null,
                    ]),
                ] : null,
                'measurements' => $project->measurements->map(fn (ProjectMeasurement $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'unit' => $m->unit,
                    'value' => $m->value,
                    'how_to' => $m->how_to,
                ]),
                'progress_photos' => $project->progressPhotos->map(fn ($p) => [
                    'id' => $p->id,
                    'url' => route('media.progress', $p),
                    'created_at' => $p->created_at?->toIso8601String(),
                ]),
                'changes' => $project->projectChanges->map(fn ($c) => [
                    'id' => $c->id,
                    'field' => $c->field,
                    'item_label' => $c->item_label,
                    'old_value' => $c->old_value,
                    'new_value' => $c->new_value,
                    'changed_at' => $c->changed_at->toIso8601String(),
                ]),
                'village' => [
                    'id' => $project->village->id,
                    'name' => $project->village->name,
                    'slug' => $project->village->slug,
                    'regency' => $project->village->regency,
                ],
            ],
            'standards' => $standards,
        ]);
    }

    public function updateItemCco(
        Request $request,
        Project $project,
        AuditItem $item,
        ApplyProjectCco $action
    ): RedirectResponse {
        Gate::authorize('updateCco', $project);

        $validated = $request->validate([
            'volume' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:50'],
            'unit_price_rp' => ['required', 'integer', 'gt:0'],
            'standard_id' => ['nullable', 'integer', 'exists:price_standards,id'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'reason.required' => 'Alasan perubahan CCO wajib diisi untuk transparansi riwayat (D13).',
            'reason.min' => 'Alasan perubahan CCO minimal 3 karakter.',
        ]);

        $action->updateItem(
            project: $project,
            item: $item,
            data: [
                'volume' => $validated['volume'],
                'unit' => $validated['unit'],
                'unit_price_rp' => (int) $validated['unit_price_rp'],
                'standard_id' => $validated['standard_id'] ?? null,
            ],
            reason: $validated['reason'],
        );

        return back()->with('success', 'Perubahan CCO item belanja berhasil dicatat.');
    }

    public function updateMeasurementCco(
        Request $request,
        Project $project,
        ProjectMeasurement $measurement,
        ApplyProjectCco $action
    ): RedirectResponse {
        Gate::authorize('updateCco', $project);

        $validated = $request->validate([
            'value' => ['required', 'numeric', 'gt:0'],
            'how_to' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'reason.required' => 'Alasan perubahan CCO parameter ukuran wajib diisi (D13).',
        ]);

        $action->updateMeasurement(
            project: $project,
            measurement: $measurement,
            newValue: (string) $validated['value'],
            howTo: $validated['how_to'] ?? null,
            reason: $validated['reason'],
        );

        return back()->with('success', 'Perubahan CCO parameter spek fisik berhasil dicatat.');
    }

    public function updateGpsCco(
        Request $request,
        Project $project,
        ApplyProjectCco $action
    ): RedirectResponse {
        Gate::authorize('updateCco', $project);

        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'reason.required' => 'Alasan perubahan koordinat GPS wajib diisi (D13).',
        ]);

        $action->updateGps(
            project: $project,
            newLat: (string) $validated['lat'],
            newLng: (string) $validated['lng'],
            reason: $validated['reason'],
        );

        return back()->with('success', 'Perubahan CCO titik GPS geofence berhasil dicatat.');
    }

    /**
     * D10: Proyek terbit tidak bisa dihapus atau ditarik ke draf.
     * Menghapus draf proyek sekaligus membersihkan audit dan berkas fisik terkait.
     */
    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        DB::transaction(function () use ($project): void {
            $audit = $project->audit;
            $filePath = $audit?->file_path;

            $project->delete();

            if ($audit !== null) {
                $audit->items()->delete();
                $audit->delete();
            }

            if ($filePath && Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }
        });

        return redirect()->route('desa.dashboard')->with('success', 'Draf proyek berhasil dihapus.');
    }

    /**
     * D11: Ukuran Cek Spek tidak bisa dihapus pasca-terbit.
     */
    public function destroyMeasurement(Project $project, ProjectMeasurement $measurement): RedirectResponse
    {
        Gate::authorize('deleteMeasurement', $project);
        abort_unless($measurement->project_id === $project->id, 404);

        $measurement->delete();

        return back()->with('success', 'Parameter ukuran berhasil dihapus.');
    }
}
