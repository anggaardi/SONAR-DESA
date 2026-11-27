<?php

declare(strict_types=1);

namespace App\Modules\Report\Models;

use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\OwnedThroughParent;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Enums\ReportKind;
use App\Modules\Review\Enums\ReportVerdict;
use App\Modules\Review\Models\ReviewCycle;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $cycle_id
 * @property int $user_id
 * @property ReportKind $kind
 * @property string|null $lat
 * @property string|null $lng
 * @property string|null $accuracy_m
 * @property string|null $distance_m
 * @property string|null $device_hash
 * @property string|null $note
 * @property int|null $superseded_by
 * @property string|null $invalid_reason
 * @property ReportVerdict|null $bpd_verdict
 * @property Carbon|null $bpd_verified_at
 * @property string|null $bpd_reply
 * @property Carbon|null $bpd_replied_at
 * @property Carbon|null $opened_at
 */
#[Fillable([
    'project_id',
    'cycle_id',
    'user_id',
    'kind',
    'lat',
    'lng',
    'accuracy_m',
    'distance_m',
    'device_hash',
    'note',
    'superseded_by',
    'invalid_reason',
    'bpd_verdict',
    'bpd_verified_at',
    'bpd_reply',
    'bpd_replied_at',
    'opened_at',
])]
#[UseFactory(ReportFactory::class)]
final class Report extends Model implements OwnedThroughParent
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => ReportKind::class,
            'bpd_verdict' => ReportVerdict::class,
            'bpd_verified_at' => 'immutable_datetime',
            'bpd_replied_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
        ];
    }

    /** A BPD verdict is final: reports marked valid or invalid can no longer be verified or annotated. */
    public function isVerified(): bool
    {
        return $this->bpd_verdict !== null || $this->invalid_reason !== null;
    }

    public function villageId(): int
    {
        return $this->project->village_id;
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ReportAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(ReportAnswer::class);
    }

    /** @return HasMany<ReportPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(ReportPhoto::class)->orderBy('position');
    }

    /** @return BelongsTo<ReviewCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ReviewCycle::class, 'cycle_id');
    }
}
