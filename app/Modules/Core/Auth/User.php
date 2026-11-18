<?php

declare(strict_types=1);

namespace App\Modules\Core\Auth;

use App\Modules\Core\Village\Village;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Desa and BPD accounts (email + password, created by the team) and warga (Google, no village).
 *
 * Deliberately NOT village-scoped: warga have village_id null, and login must find them
 * without a CurrentVillage (C3). Desa/BPD access is checked in policies via canAccessVillage().
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property int|null $village_id
 * @property string|null $google_id
 */
#[Fillable(['name', 'email', 'password', 'role', 'village_id', 'google_id'])]
#[Hidden(['password', 'remember_token', 'google_id'])]
#[UseFactory(UserFactory::class)]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    /** @return BelongsTo<Village, $this> */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canAccessVillage(int $villageId): bool
    {
        return $this->role->belongsToVillage() && $this->village_id === $villageId;
    }
}
