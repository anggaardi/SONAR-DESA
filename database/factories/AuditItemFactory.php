<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Models\Audit;
use App\Modules\Audit\Models\AuditItem;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditItem>
 */
final class AuditItemFactory extends Factory
{
    protected $model = AuditItem::class;

    public function definition(): array
    {
        return [
            'audit_id' => Audit::factory(),
            'position' => 1,
            'raw_name' => 'Semen 50 kg',
            'volume' => '380.000',
            'unit' => 'sak',
            'unit_price_rp' => 85_000,
            'amount_rp' => 32_300_000,
            'standard_id' => PriceStandard::factory(),
            'doubt' => null,
            'label' => PriceLabel::AboveStandard,
            'over_pct' => 31,
            'diff_rp' => 20_000,
            'corrected' => false,
        ];
    }
}
