<?php

declare(strict_types=1);

namespace App\Modules\Project\Http\Resources;

use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Project\Models\Project;
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
            ->where('in_radius', true)
            ->whereNull('invalid_reason')
            ->distinct('user_id')
            ->count('user_id');

        $latestPhoto = $project->progressPhotos->first();

        $withinCount = 0;
        $aboveCount = 0;
        $noRefCount = 0;

        foreach ($items as $item) {
            match ($item->label) {
                PriceLabel::WithinStandard => $withinCount++,
                PriceLabel::AboveStandard => $aboveCount++,
                PriceLabel::NoReference => $noRefCount++,
                default => null,
            };
        }

        $currentCycle = $project->currentCycle;

        return [
            'id' => $project->id,
            'slug' => $project->slug,
            'name' => $project->name,
            'type' => $project->type->value,
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
            'public_status' => $project->public_status->value,
            'in_review_since' => $currentCycle?->in_review_since?->toIso8601String(),
            'review' => $currentCycle && $project->public_status === PublicStatus::Reviewed ? [
                'outcome' => $currentCycle->result?->value,
                'note' => $currentCycle->note,
                'reviewed_at' => $currentCycle->closed_at?->toIso8601String(),
                'notes' => $currentCycle->notes->map(fn ($n) => [
                    'note' => $n->note,
                    'created_at' => $n->created_at->toIso8601String(),
                ])->all(),
            ] : null,
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
                $standard = $item->standard;
                $explanation = match ($item->label) {
                    PriceLabel::AboveStandard => "Harga di RAB {$item->over_pct}% di atas standar harga Kabupaten Gianyar {$project->year}. Belum tentu salah: BPD bisa meminta penjelasan desa.",
                    PriceLabel::NoReference => "Item ini tidak ada di daftar standar Gianyar {$project->year}, jadi tidak dinilai",
                    default => null,
                };

                return [
                    'id' => $item->id,
                    'position' => $item->position,
                    'name' => $item->raw_name,
                    'volume' => $item->volume !== null ? (string) (float) $item->volume : null,
                    'unit' => $item->unit,
                    'unit_price_rp' => $item->unit_price_rp,
                    'amount_rp' => $item->amount_rp,
                    'standard_price_rp' => $standard?->price_rp,
                    'unit_diff_rp' => $item->unit_price_rp && $standard?->price_rp ? max(0, $item->unit_price_rp - $standard->price_rp) : null,
                    'total_diff_rp' => $item->diff_rp,
                    'over_pct' => $item->over_pct,
                    'label' => $item->label->value,
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
                'changed_at' => $c->changed_at->toIso8601String(),
            ])->all(),
            'document_url' => $audit?->file_path ? route('projects.document', $project->slug) : null,
        ];
    }
}
