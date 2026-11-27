<?php

declare(strict_types=1);

namespace App\Modules\Report\Domain;

use App\Modules\Project\Models\Project;

final readonly class SuggestedSampleProject
{
    public function __construct(
        public Project $project,
    ) {}
}
