<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Modules\Core\Village\BelongsToVillage;
use Illuminate\Database\Eloquent\Model;

/**
 * Test stand-in for a (V) table with village_id. Table is created inside the test.
 *
 * @property int $id
 * @property int $village_id
 * @property string $name
 */
final class ScopedThing extends Model
{
    use BelongsToVillage;

    public $timestamps = false;

    protected $fillable = ['name'];
}
