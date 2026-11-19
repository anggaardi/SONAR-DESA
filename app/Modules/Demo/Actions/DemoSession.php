<?php

declare(strict_types=1);

namespace App\Modules\Demo\Actions;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Http\Middleware\SetCurrentVillage;
use App\Modules\Core\Village\Village;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Start, switch, and reset a demo session (PRD Arsitektur 6.7, M6-03; PRD Fitur Inti 7.2).
 * One demo village per browser session; it is kept until reset or purge.
 */
final class DemoSession
{
    private const ROLE_SESSION_KEY = SetCurrentVillage::DEMO_ROLE_SESSION_KEY;

    public function __construct(private readonly VillageCloner $cloner) {}

    /** "Coba sebagai <peran>": reuse this session's demo village, or clone a new one. */
    public function start(Request $request, Role $role): Village
    {
        $village = $this->current($request) ?? $this->cloner->clone($this->template());
        $this->enter($request, $village, $role);

        return $village;
    }

    /** "Ganti peran": same village, other account. Null when the session has no demo village. */
    public function switch(Request $request, Role $role): ?Village
    {
        $village = $this->current($request);
        if ($village !== null) {
            $this->enter($request, $village, $role);
        }

        return $village;
    }

    /** "Mulai ulang demo": drop this session's village and start again from the template. */
    public function reset(Request $request): Village
    {
        $role = Role::tryFrom((string) $request->session()->get(self::ROLE_SESSION_KEY)) ?? Role::Warga;
        $old = $this->current($request);

        $village = $this->cloner->clone($this->template());
        $this->enter($request, $village, $role);

        if ($old !== null) {
            self::destroy($old);
        }

        return $village;
    }

    /** Deletes a demo village (cascades to every linked row) and its uploaded files. */
    public static function destroy(Village $village): void
    {
        $village->delete();
        Storage::disk('local')->deleteDirectory("villages/{$village->id}");
    }

    private function enter(Request $request, Village $village, Role $role): void
    {
        // Leave any account (real or demo) without dropping the session that holds the demo village.
        Auth::guard('web')->logout();

        if ($role !== Role::Warga) {
            $account = User::query()->where('village_id', $village->id)->where('role', $role)->firstOrFail();
            Auth::guard('web')->login($account);
        }

        $request->session()->regenerate();
        $request->session()->put([
            SetCurrentVillage::DEMO_SESSION_KEY => $village->id,
            self::ROLE_SESSION_KEY => $role->value,
        ]);

        // Activity keeps the session alive; unused villages expire (PRD Fitur 7.2: 24 hours).
        $village->update(['demo_expires_at' => now()->addHours((int) config('sonar.demo.ttl_hours'))]);
    }

    private function current(Request $request): ?Village
    {
        $id = $request->session()->get(SetCurrentVillage::DEMO_SESSION_KEY);

        return is_numeric($id)
            ? Village::query()->whereKey((int) $id)->where('is_demo', true)->first()
            : null;
    }

    private function template(): Village
    {
        $template = Village::query()->where('slug', config('sonar.demo.template_slug'))->first();

        abort_if($template === null, 503, 'Data demo belum tersedia. Coba lagi nanti.');

        return $template;
    }
}
