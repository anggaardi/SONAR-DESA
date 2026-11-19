<?php

declare(strict_types=1);

namespace App\Modules\Project\Http\Resources;

use App\Modules\Project\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Project $resource
 */
final class VillageProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $project = $this->resource;
        $audit = $project->audit;
        $items = $audit !== null ? $audit->items : collect();

        return [
            'id' => $project->id,
            'slug' => $project->slug,
            'name' => $project->name,
            'type' => $project->type->value,
            'fund_source' => $project->fund_source,
            'year' => $project->year,
            'total_amount_rp' => (int) ($items->sum('amount_rp') ?: $audit?->written_total_rp ?: 0),
            'progress_pct' => $project->progress_pct,
            'public_status' => $project->public_status->value,
        ];
    }
}
