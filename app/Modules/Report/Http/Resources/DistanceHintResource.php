<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Resources;

use App\Modules\Report\Domain\DistanceHint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DistanceHint
 */
final class DistanceHintResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'within_radius' => $this->withinRadius,
            'distance_m' => $this->distanceMeters,
        ];
    }
}
