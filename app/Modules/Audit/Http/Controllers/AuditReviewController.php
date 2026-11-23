<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Domain\TotalChecker;
use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Models\Audit;
use App\Modules\Audit\Models\AuditItem;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Village;
use App\Modules\Project\Actions\GenerateProjectQrSvg;
use App\Modules\Project\Actions\PublishProject;
use App\Modules\Project\Enums\MeasurementOrigin;
use App\Modules\Project\Enums\ProjectType;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class AuditReviewController
{
    public function show(Request $request, Audit $audit, TotalChecker $totalChecker): Response
    {
        $villageId = app(CurrentVillage::class)->id();
        abort_unless($audit->village_id === $villageId, 403, 'Akses ditolak.');

        /** @var Village $village */
        $village = Village::findOrFail($villageId);

        $audit->load(['items.standard', 'project.measurements']);

        $items = $audit->items;

        $writtenTotal = $audit->written_total_rp;
        $totalCheck = $totalChecker->check($items, $writtenTotal, (int) config('sonar.price.row_tolerance_rp', 1000));

        $needsCheckCount = 0;
        $aboveStandardCount = 0;
        $withinStandardCount = 0;
        $noReferenceCount = 0;

        $itemsData = $items->map(function (AuditItem $item) use (
            &$needsCheckCount,
            &$aboveStandardCount,
            &$withinStandardCount,
            &$noReferenceCount
        ): array {
            $isYellow = $item->label === PriceLabel::NeedsCheck && ! $item->corrected;

            match ($item->label) {
                PriceLabel::NeedsCheck => $needsCheckCount += ($item->corrected ? 0 : 1),
                PriceLabel::AboveStandard => $aboveStandardCount++,
                PriceLabel::WithinStandard => $withinStandardCount++,
                PriceLabel::NoReference => $noReferenceCount++,
            };

            // Derive reason description for yellow rows
            $reasonText = null;
            if ($isYellow) {
                $reason = $item->doubt['reason'] ?? null;
                if ($reason === 'satuan_berbeda') {
                    $reasonText = 'Satuan berbeda: Berkas menyebut "'.($item->unit ?? '-').'", acuan resmi SSH menggunakan "'.($item->standard->unit ?? '-').'".';
                } elseif ($reason === 'deviasi_perkalian') {
                    $reasonText = 'Deviasi perkalian: Hasil kali volume dan harga satuan tidak cocok dengan subtotal.';
                } elseif ($reason === 'ai_ragu') {
                    $reasonText = 'Visual kabur: AI menandai keraguan pada pembacaan teks dokumen.';
                } else {
                    $reasonText = 'Perlu diperiksa: Konfirmasikan kebenaran angka dan satuan rincian belanja ini.';
                }
            }

            return [
                'id' => $item->id,
                'position' => $item->position,
                'raw_name' => $item->raw_name,
                'volume' => $item->volume !== null ? (string) (float) $item->volume : null,
                'unit' => $item->unit,
                'unit_price_rp' => $item->unit_price_rp,
                'amount_rp' => $item->amount_rp,
                'standard_id' => $item->standard_id,
                'standard' => $item->standard !== null ? [
                    'id' => $item->standard->id,
                    'code' => $item->standard->code,
                    'name' => $item->standard->name,
                    'unit' => $item->standard->unit,
                    'price_rp' => $item->standard->price_rp,
                ] : null,
                'label' => $item->label->value,
                'over_pct' => $item->over_pct,
                'diff_rp' => $item->diff_rp,
                'corrected' => (bool) $item->corrected,
                'is_yellow' => $isYellow,
                'reason_text' => $reasonText,
            ];
        })->values()->all();

        $year = $audit->project?->year ?? (int) date('Y');
        $priceStandards = PriceStandard::query()
            ->select(['id', 'code', 'name', 'unit', 'price_rp'])
            ->where('regency', $village->regency)
            ->where('year', $year)
            ->orderBy('name')
            ->get();

        $types = array_map(fn (ProjectType $t): array => [
            'value' => $t->value,
            'label' => $t->label(),
        ], ProjectType::cases());

        $templates = config('sonar.measurement_templates', []);

        $fundSources = [
            'Dana Desa (DDS)',
            'Alokasi Dana Desa (ADD)',
            'Pendapatan Asli Desa (PADes)',
            'Bantuan Keuangan Khusus (BKK)',
            'Bagi Hasil Pajak & Retribusi (BHPR)',
            'Lainnya',
        ];

        $hasFile = ! empty($audit->file_path);
        $isTotalOk = $totalCheck->isMatch || (bool) $audit->total_ack;
        $canPublish = $needsCheckCount === 0 && count($items) > 0 && $hasFile && $audit->status->value !== 'published' && $isTotalOk;

        $fileName = $audit->file_path ? basename($audit->file_path) : 'Dokumen_RAB.pdf';
        $fileUrl = $audit->file_path ? route('audits.document', $audit->id) : null;

        $project = $audit->project;
        $projectData = $project !== null ? [
            'id' => $project->id,
            'slug' => $project->slug,
            'name' => $project->name,
            'type' => $project->type->value,
            'fund_source' => $project->fund_source,
            'year' => $project->year,
            'lat' => $project->lat,
            'lng' => $project->lng,
            'is_published' => $project->isPublished(),
            'measurements' => $project->measurements->map(fn ($m): array => [
                'name' => $m->name,
                'value' => (string) (float) $m->value,
                'unit' => $m->unit,
                'how_to' => $m->how_to,
                'origin' => $m->origin->value,
            ])->all(),
        ] : null;

        return Inertia::render('audit/Review', [
            'audit' => [
                'id' => $audit->id,
                'status' => $audit->status->value,
                'source' => $audit->source,
                'file_path' => $audit->file_path,
                'file_name' => $fileName,
                'file_url' => $fileUrl,
                'written_total_rp' => $audit->written_total_rp,
                'total_ack' => (bool) $audit->total_ack,
                'created_at' => $audit->created_at?->toIso8601String(),
            ],
            'items' => $itemsData,
            'summary' => [
                'total_items' => count($items),
                'needs_check_count' => $needsCheckCount,
                'above_standard_count' => $aboveStandardCount,
                'within_standard_count' => $withinStandardCount,
                'no_reference_count' => $noReferenceCount,
                'total_calculated_rp' => $totalCheck->calculatedTotalRp,
                'is_total_match' => $totalCheck->isMatch,
                'total_diff_rp' => $totalCheck->diffRp,
                'can_publish' => $canPublish,
            ],
            'project' => $projectData,
            'village' => [
                'id' => $village->id,
                'name' => $village->name,
                'slug' => $village->slug,
                'regency' => $village->regency,
            ],
            'priceStandards' => $priceStandards,
            'types' => $types,
            'templates' => $templates,
            'fundSources' => $fundSources,
        ]);
    }

    public function document(Request $request, Audit $audit): SymfonyResponse
    {
        $villageId = app(CurrentVillage::class)->id();
        abort_unless($audit->village_id === $villageId, 403, 'Akses ditolak.');

        $filePath = $audit->file_path;
        if (! $filePath) {
            abort(404, 'Dokumen asli tidak ditemukan.');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($filePath)) {
            abort(404, 'File dokumen tidak ditemukan di penyimpanan.');
        }

        return $disk->response($filePath);
    }

    public function ackTotal(Request $request, Audit $audit): RedirectResponse
    {
        $villageId = app(CurrentVillage::class)->id();
        abort_unless($audit->village_id === $villageId, 403, 'Akses ditolak.');
        $audit->update(['total_ack' => true]);

        return back()->with('success', 'Ketidakcocokan total RAB tertulis telah dikonfirmasi (F3).');
    }

    public function publish(
        Request $request,
        Audit $audit,
        PublishProject $publishProject,
        GenerateProjectQrSvg $qrSvgAction
    ): RedirectResponse {
        $villageId = app(CurrentVillage::class)->id();
        abort_unless($audit->village_id === $villageId, 403, 'Akses ditolak.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ProjectType::class)],
            'fund_source' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:2020', 'max:2035'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'total_ack' => ['nullable', 'boolean'],
            'measurements' => ['required', 'array', 'min:1', 'max:10'],
            'measurements.*.name' => ['required', 'string', 'max:255'],
            'measurements.*.value' => ['required', 'numeric', 'min:0.001'],
            'measurements.*.unit' => ['required', 'string', 'max:32'],
            'measurements.*.how_to' => ['nullable', 'string', 'max:500'],
            'measurements.*.origin' => ['nullable', Rule::enum(MeasurementOrigin::class)],
        ], [
            'name.required' => 'Nama kegiatan proyek fisik wajib diisi.',
            'type.required' => 'Pilih jenis pekerjaan proyek fisik.',
            'fund_source.required' => 'Pilih atau isi sumber dana proyek.',
            'year.required' => 'Tahun anggaran wajib diisi.',
            'lat.required' => 'Titik koordinat GPS (Latitude) wajib diisi untuk mengaktifkan geofence warga (D3).',
            'lng.required' => 'Titik koordinat GPS (Longitude) wajib diisi untuk mengaktifkan geofence warga (D3).',
            'measurements.required' => 'Parameter ukuran fisik Cek Spek wajib diisi (D2).',
        ]);

        $project = $publishProject->execute($audit, [
            'name' => $validated['name'],
            'type' => ProjectType::from((string) $validated['type']),
            'fund_source' => $validated['fund_source'],
            'year' => (int) $validated['year'],
            'lat' => (string) $validated['lat'],
            'lng' => (string) $validated['lng'],
            'total_ack' => (bool) ($validated['total_ack'] ?? false),
            'measurements' => $validated['measurements'],
        ]);

        $qrSvg = $qrSvgAction->handle($project);
        $publicUrl = url('/p/'.$project->slug);

        return redirect()
            ->route('audits.show', $audit->id)
            ->with('published_project', [
                'id' => $project->id,
                'slug' => $project->slug,
                'name' => $project->name,
                'type' => $project->type->value,
                'fund_source' => $project->fund_source,
                'year' => $project->year,
                'total_amount_rp' => $audit->written_total_rp ?? (int) $audit->items()->sum('amount_rp'),
                'public_url' => $publicUrl,
                'qr_svg' => $qrSvg,
                'qr_poster_url' => route('projects.qr', $project->id),
            ])
            ->with('success', 'Proyek fisik berhasil diterbitkan ke publik!');
    }
}
