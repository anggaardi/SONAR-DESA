<?php

declare(strict_types=1);

namespace App\Modules\Report\Domain;

final readonly class CitizenReportHistory
{
    /**
     * @param  list<CitizenReportEntry>  $entries
     */
    public function __construct(
        public array $entries,
        public int $totalCount,
        public int $activeCount,
        public int $reviewedOrInReviewCount,
        public ?SuggestedSampleProject $suggestedProject,
        public ?string $villageName,
        public ?string $villageSlug,
    ) {}
}
