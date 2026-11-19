<?php

declare(strict_types=1);

namespace App\Modules\Standard\Models;

use Database\Factories\PriceStandardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $regency
 * @property string $code
 * @property string $name
 * @property string $unit
 * @property int $price_rp
 * @property int $year
 * @property string $source_ref
 */
#[Fillable(['regency', 'code', 'name', 'unit', 'price_rp', 'year', 'source_ref'])]
#[UseFactory(PriceStandardFactory::class)]
final class PriceStandard extends Model
{
    /** @use HasFactory<PriceStandardFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_rp' => 'integer',
            'year' => 'integer',
        ];
    }
}
