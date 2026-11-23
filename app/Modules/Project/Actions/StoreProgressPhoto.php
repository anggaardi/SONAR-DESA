<?php

declare(strict_types=1);

namespace App\Modules\Project\Actions;

use App\Modules\Project\Models\ProgressPhoto;
use App\Modules\Project\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

final class StoreProgressPhoto
{
    private ImageManager $imageManager;

    public function __construct(
        private readonly ?RecordProjectChange $recordProjectChange = null,
    ) {
        $this->imageManager = new ImageManager(new Driver);
    }

    /**
     * @param  array<UploadedFile>  $photos
     */
    public function handle(
        Project $project,
        int $progressPct,
        array $photos,
        ?string $decreaseReason = null
    ): void {
        $disk = Storage::disk('local');
        $oldPct = $project->progress_pct;

        foreach ($photos as $uploadedFile) {
            $image = $this->imageManager->decode(file_get_contents($uploadedFile->getRealPath()));

            // PRD Arsitektur 6.8 (M3-05): resize to max 1600px and re-encode to WebP to strip EXIF.
            $image->scaleDown(1600, 1600);
            $encodedWebp = $image->encodeUsingMediaType('image/webp', 85);

            $filename = Str::uuid()->toString().'.webp';
            $storagePath = 'progress/'.$filename;

            $disk->put($storagePath, (string) $encodedWebp);

            ProgressPhoto::create([
                'project_id' => $project->id,
                'path' => $storagePath,
                'progress_pct' => $progressPct,
                'taken_at' => Carbon::now(),
            ]);
        }

        // D13: Penurunan progres fisik wajib tercatat ke project_changes
        if ($progressPct < $oldPct) {
            $recorder = $this->recordProjectChange ?? app(RecordProjectChange::class);
            $recorder->execute(
                project: $project,
                field: 'Progres Fisik',
                itemLabel: 'Penurunan Capaian Fisik',
                oldValue: "{$oldPct}%",
                newValue: "{$progressPct}%",
                reason: $decreaseReason ?? 'Penyesuaian capaian fisik',
            );
        }

        $project->update(['progress_pct' => $progressPct]);
    }
}
