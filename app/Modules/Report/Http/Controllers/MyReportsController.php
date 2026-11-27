<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Controllers;

use App\Modules\Core\Auth\User;
use App\Modules\Report\Actions\GetCitizenReports;
use App\Modules\Report\Http\Resources\CitizenReportHistoryResource;
use App\Modules\Report\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Citizen's personal Cek Spek report history (/laporan-saya), closing the oversight loop (LS-R1..LS-R6).
 */
final class MyReportsController
{
    public function index(Request $request, GetCitizenReports $getCitizenReports): Response|InertiaResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Inertia::render('report/MyReports', [
                'myReports' => CitizenReportHistoryResource::guest(),
            ]);
        }

        if (Gate::denies('viewOwn', Report::class)) {
            return Inertia::render('report/Forbidden', [
                'project' => null,
            ])->toResponse($request)->setStatusCode(403);
        }

        $history = $getCitizenReports->handle($request, $user);

        return Inertia::render('report/MyReports', [
            'myReports' => (new CitizenReportHistoryResource($history, $user))->resolve($request),
        ]);
    }

    public function signIn(Request $request): RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('reports.mine');
        }

        redirect()->setIntendedUrl(route('reports.mine'));

        return redirect()->route('auth.google');
    }
}
