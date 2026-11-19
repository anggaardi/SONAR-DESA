<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Audit>
 */
final class AuditFactory extends Factory
{
    protected $model = Audit::class;

    public function definition(): array
    {
        return [
            'village_id' => Village::factory(),
            'owner_id' => User::factory()->state(['role' => Role::Desa]),
            'status' => AuditStatus::Published,
            'file_path' => 'audits/sample-'.fake()->uuid().'.pdf',
            'source' => 'fixture',
            'read_ms' => 1200,
            'written_total_rp' => 105_000_000,
            'total_ack' => true,
            'error_code' => null,
        ];
    }
}
