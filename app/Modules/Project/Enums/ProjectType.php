<?php

declare(strict_types=1);

namespace App\Modules\Project\Enums;

enum ProjectType: string
{
    case RabatBeton = 'rabat_beton';
    case SaluranIrigasi = 'saluran_irigasi';
    case Gedung = 'gedung';
    case LampuJalan = 'lampu_jalan';
    case Lainnya = 'lainnya';
}
