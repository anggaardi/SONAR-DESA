<?php

declare(strict_types=1);

namespace App\Modules\Project\Http\Controllers;

use App\Modules\Project\Actions\StoreProgressPhoto;
use App\Modules\Project\Http\Requests\StoreProgressPhotoRequest;
use App\Modules\Project\Models\ProgressPhoto;
use App\Modules\Project\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class ProgressPhotoController
{
    public function store(StoreProgressPhotoRequest $request, Project $project, StoreProgressPhoto $action): RedirectResponse
    {
        $action->handle(
            $project,
            (int) $request->validated('progress_pct'),
            $request->file('photos'),
        );

        return back();
    }

    public function show(ProgressPhoto $photo): Response
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($photo->path)) {
            abort(404, 'Foto tidak ditemukan');
        }

        return $disk->response($photo->path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Type' => 'image/webp',
        ]);
    }
}
