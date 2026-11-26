<?php

declare(strict_types=1);

namespace App\Modules\Standard\Models;

use Database\Factories\PriceStandardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * Loads price standards for a regency and target year, falling back only to the latest
     * available year within the same regency so cross-regency catalogs never mix.
     *
     * @return Collection<int, self>
     */
    public static function catalogForRegencyAndYear(string $regency, int $year): Collection
    {
        $standards = self::query()
            ->where('regency', $regency)
            ->where('year', $year)
            ->select(['id', 'code', 'name', 'unit', 'price_rp', 'year'])
            ->get();

        if ($standards->isNotEmpty()) {
            return $standards;
        }

        $latestYear = self::query()
            ->where('regency', $regency)
            ->max('year');

        if ($latestYear === null) {
            return $standards;
        }

        return self::query()
            ->where('regency', $regency)
            ->where('year', (int) $latestYear)
            ->select(['id', 'code', 'name', 'unit', 'price_rp', 'year'])
            ->get();
    }
}
