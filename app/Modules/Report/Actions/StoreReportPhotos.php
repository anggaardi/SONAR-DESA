<?php

declare(strict_types=1);

namespace App\Modules\Report\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Writes Cek Spek photos to the private disk (PRD Arsitektur 8, M4-02): resized to at most 1600 px and re-encoded
 * to WebP so EXIF/GPS is dropped, like StoreProgressPhoto. Files are written before the database transaction;
 * SubmitReport calls delete() when the transaction fails, so no file is left without a row.
 */
final class StoreReportPhotos
{
    private const MAX_EDGE_PX = 1600;

    private const WEBP_QUALITY = 85;

    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver);
    }

    /**
     * @param  list<UploadedFile>  $photos
     * @return list<string> paths on the local (private) disk, in the same order
     */
    public function store(array $photos): array
    {
        $disk = Storage::disk('local');
        $paths = [];

        try {
            foreach ($photos as $photo) {
                $image = $this->imageManager->decode((string) file_get_contents($photo->getRealPath()));
                $image->scaleDown(self::MAX_EDGE_PX, self::MAX_EDGE_PX);

                $path = 'reports/'.Str::uuid()->toString().'.webp';
                $disk->put($path, (string) $image->encodeUsingMediaType('image/webp', self::WEBP_QUALITY));
                $paths[] = $path;
            }
        } catch (Throwable $exception) {
            $this->delete($paths);

            throw $exception;
        }

        return $paths;
    }

    /** @param  list<string>  $paths */
    public function delete(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }
}
