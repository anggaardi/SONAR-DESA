<?php

declare(strict_types=1);

namespace App\Modules\Report\Domain;

use App\Modules\Project\Models\Project;
use App\Modules\Report\Enums\CitizenReportStatus;
use App\Modules\Report\Models\Report;

final readonly class CitizenReportEntry
{
    public function __construct(
        public Report $report,
        public Project $project,
        public CitizenReportStatus $status,
        public int $mismatchReportersCount,
        public int $threshold,
        public CitizenReportBpdReview $bpdReview,
    ) {}
}
