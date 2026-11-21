<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'is_system'])]
class Role extends Model
{
    use Auditable;

    public const OWNER = 'owner';

    public const MANAGER = 'manager';

    /**
     * Roles whose users may log in. Salesman and tailor records are names only.
     *
     * @var list<string>
     */
    public const LOGIN_ROLES = [self::OWNER, self::MANAGER];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isOwner(): bool
    {
        return $this->slug === self::OWNER;
    }

    public function canLogIn(): bool
    {
        return in_array($this->slug, self::LOGIN_ROLES, true);
    }
}
