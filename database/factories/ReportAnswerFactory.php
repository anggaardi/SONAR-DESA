<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Project\Models\ProjectMeasurement;
use App\Modules\Report\Enums\MeasurementAnswer;
use App\Modules\Report\Models\Report;
use App\Modules\Report\Models\ReportAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportAnswer>
 */
final class ReportAnswerFactory extends Factory
{
    protected $model = ReportAnswer::class;

    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'measurement_id' => ProjectMeasurement::factory(),
            'answer' => MeasurementAnswer::Mismatch,
            'measured_value' => '8.000',
            'measured_unit' => 'cm',
            'target_value' => null,
            'target_unit' => null,
        ];
    }
}
