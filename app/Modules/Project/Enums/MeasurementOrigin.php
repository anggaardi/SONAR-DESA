<?php

declare(strict_types=1);

namespace App\Modules\Project\Enums;

enum MeasurementOrigin: string
{
    case Template = 'template';
    case Desa = 'desa';
}
