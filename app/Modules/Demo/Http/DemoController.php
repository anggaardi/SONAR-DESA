<?php

declare(strict_types=1);

namespace App\Modules\Demo\Http;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Village\Village;
use App\Modules\Demo\Actions\DemoSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET /demo, POST /demo/start/{role}, /demo/switch/{role}, /demo/reset .
 */
final class DemoController
{
    public function __construct(private readonly DemoSession $demo) {}

    public function index(): Response
    {
        return Inertia::render('demo/Index');
    }

    public function start(Request $request, Role $role): RedirectResponse
    {
        return $this->landing($this->demo->start($request, $role));
    }

    public function switch(Request $request, Role $role): RedirectResponse
    {
        $village = $this->demo->switch($request, $role);

        return $village === null ? redirect()->route('home') : $this->landing($village);
    }

    public function reset(Request $request): RedirectResponse
    {
        return $this->landing($this->demo->reset($request));
    }

    /**
     * Every role lands on the village page for now.
     * TODO(M2-06): desa -> /audits. TODO(M5-04): bpd -> /bpd.
     */
    private function landing(Village $village): RedirectResponse
    {
        return redirect()->route('villages.projects', $village->slug);
    }
}
