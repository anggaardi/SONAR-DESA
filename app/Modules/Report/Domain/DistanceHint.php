<?php

declare(strict_types=1);

namespace App\Modules\Report\Domain;

/**
 * What a visitor may learn about their distance to a project (D-008).
 * The exact distance and the project point never leave the server: the value is rounded up to a coarse step
 * and dropped beyond a maximum, so a few probes from made-up positions cannot be used to locate the project.
 */
final readonly class DistanceHint
{
    public function __construct(
        public bool $withinRadius,
        public ?int $distanceMeters,
    ) {}

    public static function from(float $exactMeters, bool $withinRadius, int $roundUpToMeters, int $maxMeters): self
    {
        $rounded = (int) (ceil($exactMeters / $roundUpToMeters) * $roundUpToMeters);

        return new self($withinRadius, $rounded > $maxMeters ? null : $rounded);
    }
}
