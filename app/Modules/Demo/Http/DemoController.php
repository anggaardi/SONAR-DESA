<?php

declare(strict_types=1);

namespace App\Modules\Demo\Http;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Http\Middleware\SetCurrentVillage;
use App\Modules\Core\Village\Village;
use App\Modules\Demo\Actions\DemoSession;
use App\Modules\Demo\Actions\OpenSampleRab;
use App\Modules\Demo\Actions\SampleCheckLocation;
use App\Modules\Project\Actions\FindPublicProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET /demo, POST /demo/start/{role}, /demo/switch/{role}, /demo/reset,
 * POST /demo/contoh-rab/{sample}, GET /demo/lokasi-contoh/{slug} .
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

    public function sampleRab(Request $request, string $sample, OpenSampleRab $openSample): RedirectResponse
    {
        $audit = $openSample->execute($request, $sample);

        return redirect()->route('audits.show', $audit->id);
    }

    /**
     * M6-05: sample position for Cek Spek. Only for a project of this session's demo village: a real project
     * never gets a sample position, so this cannot be used to skip the trip to the site (D-009).
     */
    public function sampleLocation(
        Request $request,
        string $slug,
        FindPublicProject $findProject,
        SampleCheckLocation $sample,
    ): JsonResponse {
        $project = $findProject->handle($request, $slug);
        $demoVillageId = $request->session()->get(SetCurrentVillage::DEMO_SESSION_KEY);

        if ($project === null || $project->lat === null || $project->lng === null
            || ! $project->village->is_demo || $project->village_id !== (int) $demoVillageId) {
            abort(404);
        }

        return response()->json($sample->for($project))->header('Cache-Control', 'no-store, private');
    }

    /**
     * Desa lands on /desa (M2-06); BPD lands on /bpd (M5); warga lands on the village page.
     */
    private function landing(Village $village): RedirectResponse
    {
        $user = auth()->user();

        if ($user?->hasRole(Role::Desa)) {
            return redirect()->route('desa.dashboard');
        }

        if ($user?->hasRole(Role::Bpd)) {
            return redirect()->route('bpd.index');
        }

        return redirect()->route('villages.projects', $village->slug);
    }
}
