<?php

declare(strict_types=1);

namespace App\Modules\Core\Auth;

enum Role: string
{
    case Desa = 'desa';
    case Bpd = 'bpd';
    case Warga = 'warga';

    /** Desa and BPD belong to exactly one village; warga do not (C3). */
    public function belongsToVillage(): bool
    {
        return $this !== self::Warga;
    }
}
