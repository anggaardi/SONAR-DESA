<?php

declare(strict_types=1);

namespace App\Modules\Core\Village;

use App\Modules\Core\Http\Middleware\SetCurrentVillage;
use Illuminate\Http\Request;

/**
 * Whether a public visitor may see a village's pages (PRD Arsitektur 6.7, P10).
 *
 * Demo villages are visible only to the session that owns them, so visitors never see each
 * other's changes (PRD Fitur M4). Lives in Core so feature modules never check for demo (F6-4).
 */
final class VillageVisibility
{
    public function allows(Request $request, Village $village): bool
    {
        if (! $village->is_demo) {
            return true;
        }

        return (int) $request->session()->get(SetCurrentVillage::DEMO_SESSION_KEY) === $village->id;
    }
}
