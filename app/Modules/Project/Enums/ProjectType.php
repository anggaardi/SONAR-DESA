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

    public function label(): string
    {
        return match ($this) {
            self::RabatBeton => 'Pembangunan Jalan Rabat Beton',
            self::SaluranIrigasi => 'Pembangunan Saluran Drainase & Irigasi',
            self::Gedung => 'Pembangunan / Rehab Gedung & Balai',
            self::LampuJalan => 'Penerangan Jalan Umum (PJU)',
            self::Lainnya => 'Pekerjaan Fisik Lainnya',
        };
    }
}
