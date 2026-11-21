<?php

declare(strict_types=1);

namespace App\Modules\Report\Enums;

/** A citizen's answer for one Cek Spek measurement (PRD Fitur Inti 5.1 step 5). */
enum MeasurementAnswer: string
{
    case Match = 'match';
    case Mismatch = 'mismatch';
    case Unmeasurable = 'unmeasurable';
}
