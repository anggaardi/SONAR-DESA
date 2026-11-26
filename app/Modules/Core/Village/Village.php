<?php

declare(strict_types=1);

namespace App\Modules\Core\Village;

use Database\Factories\VillageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A desa. Demo sessions are villages too: a clone of the Desa Contoh template (decision A9).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $regency
 * @property bool $is_demo
 * @property int $demo_ai_uploads_count
 * @property Carbon|null $demo_expires_at
 */
#[Fillable(['name', 'slug', 'regency', 'is_demo', 'demo_ai_uploads_count', 'demo_expires_at'])]
#[UseFactory(VillageFactory::class)]
final class Village extends Model
{
    /** @use HasFactory<VillageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
            'demo_ai_uploads_count' => 'integer',
            'demo_expires_at' => 'immutable_datetime',
        ];
    }

    public static function isDemoId(int $villageId): bool
    {
        return (bool) self::query()->whereKey($villageId)->value('is_demo');
    }
}
