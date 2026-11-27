<?php

declare(strict_types=1);

namespace App\Modules\Project\Http\Resources;

use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Review\Models\ReviewCycle;
use App\Modules\Review\Models\ReviewNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Project $resource
 */
final class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $project = $this->resource;
        $audit = $project->audit;
        $items = $audit !== null ? $audit->items : collect();

        // PRD Fitur Inti 4.3 (P7): count distinct accounts in-radius not marked invalid.
        $distinctCitizens = $project->reports()
            ->whereNull('invalid_reason')
            ->distinct('user_id')
            ->count('user_id');

        $latestPhoto = $project->progressPhotos->first();

        $withinCount = 0;
        $aboveCount = 0;
        $noRefCount = 0;

        foreach ($items as $item) {
            $lbl = $item->label;
            if ($lbl === PriceLabel::NeedsCheck) {
                $stdPrice = $item->standard_price_rp;
                if ($stdPrice !== null && $stdPrice > 0) {
                    $lbl = $item->unit_price_rp > $stdPrice
                        ? PriceLabel::AboveStandard
                        : PriceLabel::WithinStandard;
                } else {
                    $lbl = PriceLabel::NoReference;
                }
            }

            match ($lbl) {
                PriceLabel::WithinStandard => $withinCount++,
                PriceLabel::AboveStandard => $aboveCount++,
                PriceLabel::NoReference => $noRefCount++,
                default => null,
            };
        }

        $currentCycle = $project->currentCycle;
        $reviewCycles = $project->relationLoaded('reviewCycles')
            ? $project->reviewCycles
            : $project->reviewCycles()->with('notes')->get();

        $closedCycles = $reviewCycles
            ->filter(static fn (ReviewCycle $cycle): bool => $cycle->closed_at !== null)
            ->sortByDesc(static fn (ReviewCycle $cycle): array => [$cycle->closed_at?->getTimestamp() ?? 0, $cycle->id])
            ->values();

        if ($closedCycles->isEmpty() && $currentCycle?->closed_at !== null) {
            $currentCycle->loadMissing('notes');
            $closedCycles = collect([$currentCycle]);
        }

        $reviewedCycle = $closedCycles->first();
        $closedCycleIds = $closedCycles->pluck('id')->all();

        $reportsCollection = $project->relationLoaded('reports')
            ? $project->reports
            : $project->reports()->with(['answers.measurement', 'photos'])->get();

        $publishedReports = $reportsCollection
            ->whereNull('superseded_by')
            ->filter(static function ($report) use ($project, $closedCycleIds): bool {
                $hasBpdAction = $report->bpd_verdict !== null
                    || ($report->bpd_reply !== null && trim((string) $report->bpd_reply) !== '');

                if ($hasBpdAction) {
                    return true;
                }

                if (($project->public_status === PublicStatus::Reviewed || $closedCycleIds !== []) && $report->invalid_reason === null) {
                    return $closedCycleIds === [] || in_array($report->cycle_id, $closedCycleIds, true);
                }

                return false;
            })
            ->sortByDesc('created_at')
            ->values();

        $publishedReportCounts = array_fill_keys(array_column(ReportKind::cases(), 'value'), 0);
        foreach ($publishedReports as $report) {
            $publishedReportCounts[$report->kind->value]++;
        }

        $inReviewSince = $project->public_status === PublicStatus::InReview
            ? $currentCycle?->in_review_since
            : $reviewedCycle?->in_review_since;

        $effectivePublicStatus = $project->public_status === PublicStatus::None && $reviewedCycle !== null
            ? PublicStatus::Reviewed
            : $project->public_status;

        return [
            'id' => $project->id,
            'slug' => $project->slug,
            'name' => $project->name,
            'type' => $project->type->value,
            'description' => $project->description,
            'village' => [
                'name' => $project->village->name,
                'slug' => $project->village->slug,
                'regency' => $project->village->regency,
            ],
            'fund_source' => $project->fund_source,
            'year' => $project->year,
            'total_amount_rp' => (int) ($items->sum('amount_rp') ?: $audit?->written_total_rp ?: 0),
            'progress_pct' => $project->progress_pct,
            'last_photo_date' => $latestPhoto?->taken_at?->toIso8601String(),
            'public_status' => $effectivePublicStatus->value,
            'in_review_since' => $inReviewSince?->toIso8601String(),
            'review' => $reviewedCycle !== null ? [
                'outcome' => $reviewedCycle->result?->value,
                'note' => $reviewedCycle->note,
                'reviewed_at' => $reviewedCycle->closed_at?->toIso8601String(),
                'notes' => $reviewedCycle->notes
                    ->sortBy('created_at')
                    ->values()
                    ->map(fn (ReviewNote $n): array => [
                        'note' => $n->note,
                        'created_at' => $n->created_at?->toIso8601String(),
                    ])->all(),
            ] : null,
            'published_reports' => $publishedReports->map(fn ($r, $idx) => [
                'id' => $r->id,
                'reporter_label' => 'Warga Pelapor '.($idx + 1),
                'kind' => $r->kind->value,
                'note' => $r->note,
                'bpd_verdict' => $r->bpd_verdict !== null
                    ? $r->bpd_verdict->value
                    : ($r->invalid_reason !== null
                        ? 'invalid'
                        : ($project->public_status === PublicStatus::Reviewed ? 'valid' : null)),
                'bpd_verified_at' => $r->bpd_verified_at?->toIso8601String(),
                'invalid_reason' => $r->invalid_reason,
                'bpd_reply' => $r->bpd_reply,
                'bpd_replied_at' => $r->bpd_replied_at?->toIso8601String(),
                'created_at' => $r->created_at->toIso8601String(),
                'photos' => $r->invalid_reason === null
                    ? $r->photos
                        ->sortBy('position')
                        ->values()
                        ->map(fn ($p): array => [
                            'id' => $p->id,
                            'position' => $p->position,
                            'url' => route('media.report-photo', $p->id),
                        ])->all()
                    : [],
                'answers' => $r->answers->map(fn ($a) => [
                    'id' => $a->id,
                    'measurement_name' => $a->measurement?->name,
                    'target_value' => $a->target_value !== null
                        ? (string) (float) $a->target_value
                        : ($a->measurement?->value !== null ? (string) (float) $a->measurement->value : null),
                    'target_unit' => $a->target_unit ?? $a->measurement?->unit,
                    'answer' => $a->answer,
                    'measured_value' => $a->measured_value !== null ? (string) (float) $a->measured_value : null,
                    'measured_unit' => $a->measured_unit,
                ])->all(),
            ])->all(),
            'published_report_counts' => $publishedReportCounts,
            'label_counts' => [
                'within_standard' => $withinCount,
                'above_standard' => $aboveCount,
                'no_reference' => $noRefCount,
            ],
            'citizen_reports_count' => $distinctCitizens,
            'has_citizen_reports' => $distinctCitizens > 0,
            'measurements' => $project->measurements->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'value' => (string) (float) $m->value,
                'unit' => $m->unit,
                'how_to' => $m->how_to,
                'origin' => $m->origin->value,
            ])->all(),
            'items' => $items->map(function ($item) use ($project) {
                $label = $item->label;

                if ($label === PriceLabel::NeedsCheck) {
                    $stdPrice = $item->standard_price_rp;
                    if ($stdPrice !== null && $stdPrice > 0) {
                        $label = $item->unit_price_rp > $stdPrice
                            ? PriceLabel::AboveStandard
                            : PriceLabel::WithinStandard;
                    } else {
                        $label = PriceLabel::NoReference;
                    }
                }

                $explanation = match ($label) {
                    PriceLabel::AboveStandard => "Harga di RAB {$item->over_pct}% di atas standar harga Kabupaten Gianyar {$project->year}. Belum tentu salah: BPD bisa meminta penjelasan desa.",
                    PriceLabel::NoReference => "Item ini tidak ada di daftar standar Gianyar {$project->year}, jadi tidak dinilai",
                    default => null,
                };

                $standard = $item->standard;

                return [
                    'id' => $item->id,
                    'position' => $item->position,
                    'name' => $item->raw_name,
                    'volume' => $item->volume !== null ? (string) (float) $item->volume : null,
                    'unit' => $item->unit,
                    'unit_price_rp' => $item->unit_price_rp,
                    'amount_rp' => $item->amount_rp,
                    'standard_price_rp' => $item->standard_price_rp,
                    'unit_diff_rp' => $item->unit_price_rp && $item->standard_price_rp ? max(0, $item->unit_price_rp - $item->standard_price_rp) : null,
                    'total_diff_rp' => $item->diff_rp,
                    'over_pct' => $item->over_pct,
                    'label' => $label->value,
                    'standard_source' => $standard ? "Standar harga Kab. Gianyar {$standard->year}" : null,
                    'explanation' => $explanation,
                ];
            })->all(),
            'progress_photos' => $project->progressPhotos->map(fn ($p) => [
                'id' => $p->id,
                'url' => route('media.progress', $p->id),
                'progress_pct' => $p->progress_pct,
                'taken_at' => $p->taken_at->toIso8601String(),
            ])->all(),
            'changes' => $project->projectChanges->map(fn ($c) => [
                'id' => $c->id,
                'item_label' => $c->item_label,
                'field' => $c->field,
                'old_value' => $c->old_value,
                'new_value' => $c->new_value,
                'reason' => $c->reason,
                'changed_at' => $c->changed_at->toIso8601String(),
            ])->all(),
            'document_url' => $audit?->file_path ? route('projects.document', $project->slug) : null,
        ];
    }
}
