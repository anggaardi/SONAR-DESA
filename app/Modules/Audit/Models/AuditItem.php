<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Core\Village\OwnedThroughParent;
use App\Modules\Standard\Models\PriceStandard;
use Database\Factories\AuditItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $audit_id
 * @property int $position
 * @property string $raw_name
 * @property string|null $volume
 * @property string|null $unit
 * @property int|null $unit_price_rp
 * @property int|null $amount_rp
 * @property int|null $standard_id
 * @property int|null $standard_price_rp
 * @property array<string, mixed>|null $doubt
 * @property PriceLabel $label
 * @property int|null $over_pct
 * @property int|null $diff_rp
 * @property bool $corrected
 */
#[Fillable([
    'audit_id',
    'position',
    'raw_name',
    'volume',
    'unit',
    'unit_price_rp',
    'amount_rp',
    'standard_id',
    'standard_price_rp',
    'doubt',
    'label',
    'over_pct',
    'diff_rp',
    'corrected',
])]
#[UseFactory(AuditItemFactory::class)]
final class AuditItem extends Model implements OwnedThroughParent
{
    /** @use HasFactory<AuditItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'unit_price_rp' => 'integer',
            'amount_rp' => 'integer',
            'standard_price_rp' => 'integer',
            'diff_rp' => 'integer',
            'over_pct' => 'integer',
            'corrected' => 'boolean',
            'doubt' => 'array',
            'label' => PriceLabel::class,
        ];
    }

    public function villageId(): int
    {
        return $this->audit->village_id;
    }

    /** @return BelongsTo<Audit, $this> */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /** @return BelongsTo<PriceStandard, $this> */
    public function standard(): BelongsTo
    {
        return $this->belongsTo(PriceStandard::class, 'standard_id');
    }
}
