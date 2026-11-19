<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\BelongsToVillage;
use App\Modules\Project\Models\Project;
use Database\Factories\AuditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $village_id
 * @property int $owner_id
 * @property AuditStatus $status
 * @property string|null $file_path
 * @property string $source
 * @property int|null $read_ms
 * @property int|null $written_total_rp
 * @property bool $total_ack
 * @property string|null $error_code
 */
#[Fillable([
    'village_id',
    'owner_id',
    'status',
    'file_path',
    'source',
    'read_ms',
    'written_total_rp',
    'total_ack',
    'error_code',
])]
#[UseFactory(AuditFactory::class)]
final class Audit extends Model
{
    /** @use HasFactory<AuditFactory> */
    use BelongsToVillage, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => AuditStatus::class,
            'total_ack' => 'boolean',
            'read_ms' => 'integer',
            'written_total_rp' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<AuditItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AuditItem::class)->orderBy('position');
    }

    /** @return HasOne<Project, $this> */
    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }
}
