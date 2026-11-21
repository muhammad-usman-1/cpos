<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission as PermissionName;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'phone', 'email', 'password', 'role_id', 'is_active', 'locale', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Stored in one format so the same number is caught across customers, suppliers and staff.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => Customer::normalizePhone($value));
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Pieces handed to this person when they are a tailor.
     *
     * @return HasMany<TailorAssignment, $this>
     */
    public function tailorAssignments(): HasMany
    {
        return $this->hasMany(TailorAssignment::class, 'tailor_id');
    }

    public function isOwner(): bool
    {
        return $this->role?->slug === Role::OWNER;
    }

    public function isManager(): bool
    {
        return $this->role?->slug === Role::MANAGER;
    }

    public function canLogIn(): bool
    {
        return $this->role?->canLogIn() ?? false;
    }

    public function hasPermission(PermissionName|string $permission): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        $name = $permission instanceof PermissionName ? $permission->value : $permission;

        return $this->role?->permissions->contains('name', $name) ?? false;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
