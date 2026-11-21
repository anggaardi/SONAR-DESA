<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use Illuminate\Database\Seeder;

/**
 * Idempotent: running `make fresh` or `db:seed` twice gives the same rows (F1-1).
 * Desa Contoh is the demo template (M6) and is seeded in every environment, production included,
 * because every demo session clones it. Uses no factories: Faker is a dev dependency.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $template = Village::query()->updateOrCreate(
            ['slug' => config('sonar.demo.template_slug')],
            ['name' => 'Desa Contoh', 'regency' => 'Gianyar', 'is_demo' => false],
        );

        // Template accounts own the sample audits; VillageCloner maps them to per-demo accounts.
        // Only local ones get a password: elsewhere they cannot log in (real accounts come from the team, F16).
        foreach ([Role::Desa, Role::Bpd] as $role) {
            User::query()->updateOrCreate(
                ['email' => "{$role->value}@desa-contoh.test"],
                [
                    'name' => ucfirst($role->value).' Desa Contoh',
                    'role' => $role,
                    'village_id' => $template->id,
                    'password' => app()->isLocal() ? 'password' : null,
                ],
            );
        }

        $this->call(ProjectSeeder::class);
    }
}
