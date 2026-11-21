<?php

declare(strict_types=1);

namespace App\Modules\Report\Models;

use App\Modules\Core\Village\OwnedThroughParent;
use Database\Factories\ReportPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A camera photo of a report (C6). Stored on the private disk as re-encoded WebP; only BPD ever sees it (C11).
 *
 * @property int $id
 * @property int $report_id
 * @property string $path
 * @property int $position
 */
#[Fillable(['report_id', 'path', 'position'])]
#[UseFactory(ReportPhotoFactory::class)]
final class ReportPhoto extends Model implements OwnedThroughParent
{
    /** @use HasFactory<ReportPhotoFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function villageId(): int
    {
        return $this->report->villageId();
    }

    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
