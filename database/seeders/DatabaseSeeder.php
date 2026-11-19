<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use Illuminate\Database\Seeder;

/**
 * Idempotent: running `make fresh` or `db:seed` twice gives the same rows (F1-1).
 * Desa Contoh is the demo template (M6). Full demo data arrives in M6-01.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $template = Village::query()->updateOrCreate(
            ['slug' => 'desa-contoh-template'],
            ['name' => 'Desa Contoh', 'regency' => 'Gianyar', 'is_demo' => false],
        );

        // Local-only accounts. Production accounts are created by the team (F16), never seeded.
        if (app()->isLocal()) {
            foreach ([Role::Desa, Role::Bpd] as $role) {
                User::query()->updateOrCreate(
                    ['email' => "{$role->value}@desa-contoh.test"],
                    [
                        'name' => ucfirst($role->value).' Desa Contoh',
                        'role' => $role,
                        'village_id' => $template->id,
                        'password' => 'password',
                    ],
                );
            }

            $this->call(ProjectSeeder::class);
        }
    }
}
