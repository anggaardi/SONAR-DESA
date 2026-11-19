<?php

declare(strict_types=1);

namespace App\Modules\Project\Models;

use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\BelongsToVillage;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Project\Enums\ProjectType;
use App\Modules\Project\Enums\PublicStatus;
use App\Modules\Report\Models\Report;
use App\Modules\Review\Models\ReviewCycle;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $village_id
 * @property int $audit_id
 * @property string $slug
 * @property string $name
 * @property ProjectType $type
 * @property string|null $lat
 * @property string|null $lng
 * @property string $fund_source
 * @property int $year
 * @property int $progress_pct
 * @property int|null $current_cycle_id
 * @property PublicStatus $public_status
 * @property Carbon|null $published_at
 */
#[Fillable([
    'village_id',
    'audit_id',
    'slug',
    'name',
    'type',
    'lat',
    'lng',
    'fund_source',
    'year',
    'progress_pct',
    'current_cycle_id',
    'public_status',
    'published_at',
])]
#[UseFactory(ProjectFactory::class)]
final class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToVillage, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'public_status' => PublicStatus::class,
            'year' => 'integer',
            'progress_pct' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    /** @return BelongsTo<Audit, $this> */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /** @return HasMany<ProjectMeasurement, $this> */
    public function measurements(): HasMany
    {
        return $this->hasMany(ProjectMeasurement::class);
    }

    /** @return HasMany<ProgressPhoto, $this> */
    public function progressPhotos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class)->orderByDesc('taken_at');
    }

    /** @return HasMany<ProjectChange, $this> */
    public function projectChanges(): HasMany
    {
        return $this->hasMany(ProjectChange::class)->orderByDesc('changed_at');
    }

    /** @return HasMany<Report, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /** @return BelongsTo<ReviewCycle, $this> */
    public function currentCycle(): BelongsTo
    {
        return $this->belongsTo(ReviewCycle::class, 'current_cycle_id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $user = auth()->user();
        if ($user instanceof User && ! $user->hasRole(Role::Desa)) {
            abort(403, 'Akses ditolak');
        }

        if (! app(CurrentVillage::class)->has()) {
            return null;
        }

        return parent::resolveRouteBinding($value, $field);
    }
}
