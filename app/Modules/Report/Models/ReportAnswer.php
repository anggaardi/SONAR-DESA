<?php

declare(strict_types=1);

namespace App\Modules\Report\Models;

use App\Modules\Core\Village\OwnedThroughParent;
use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Report\Enums\MeasurementAnswer;
use Database\Factories\ReportAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One checklist answer of a report (PRD Fitur Inti 5.1 step 5, 5.4). measured_value and measured_unit are only set
 * for "Tidak" (C5); the unit is copied from the RAB measurement, never sent by the client.
 *
 * @property int $id
 * @property int $report_id
 * @property int $measurement_id
 * @property MeasurementAnswer $answer
 * @property string|null $measured_value
 * @property string|null $measured_unit
 */
#[Fillable(['report_id', 'measurement_id', 'answer', 'measured_value', 'measured_unit'])]
#[UseFactory(ReportAnswerFactory::class)]
final class ReportAnswer extends Model implements OwnedThroughParent
{
    /** @use HasFactory<ReportAnswerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'answer' => MeasurementAnswer::class,
        ];
    }

    public function villageId(): int
    {
        return $this->report->villageId();
    }

    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /** @return BelongsTo<ProjectMeasurement, $this> */
    public function measurement(): BelongsTo
    {
        return $this->belongsTo(ProjectMeasurement::class, 'measurement_id');
    }
}
