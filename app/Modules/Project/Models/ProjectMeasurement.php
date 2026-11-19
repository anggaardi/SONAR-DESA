<?php

declare(strict_types=1);

namespace App\Modules\Project\Models;

use App\Modules\Core\Village\OwnedThroughParent;
use App\Modules\Project\Enums\MeasurementOrigin;
use Database\Factories\ProjectMeasurementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string $value
 * @property string $unit
 * @property string $how_to
 * @property MeasurementOrigin $origin
 */
#[Fillable(['project_id', 'name', 'value', 'unit', 'how_to', 'origin'])]
#[UseFactory(ProjectMeasurementFactory::class)]
final class ProjectMeasurement extends Model implements OwnedThroughParent
{
    /** @use HasFactory<ProjectMeasurementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'origin' => MeasurementOrigin::class,
        ];
    }

    public function villageId(): int
    {
        return $this->project->village_id;
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
