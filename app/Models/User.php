<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $slug): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(fn ($role) => $role->slug === $slug);
        }

        return $this->roles()->where('slug', $slug)->exists();
    }

    public function hasAnyRole(array $slugs): bool
    {
        $slugs = collect($slugs)
            ->filter(fn ($slug) => is_string($slug) && $slug !== '')
            ->values()
            ->all();

        if (empty($slugs)) {
            return false;
        }

        if ($this->relationLoaded('roles')) {
            $current = $this->roles->pluck('slug')->all();
            return !empty(array_intersect($slugs, $current));
        }

        return $this->roles()->whereIn('slug', $slugs)->exists();
    }

    public function roleSlugs(): Collection
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->pluck('slug');
        }

        return $this->roles()->pluck('slug');
    }

    public function isAdminUser(): bool
    {
        return $this->is_admin || $this->hasRole('admin');
    }

    public function canAccessPanel(): bool
    {
        if ($this->isAdminUser()) {
            return true;
        }

        return $this->hasAnyRole([
            'director',
            'formulador',
            'estructurador',
            'consulta',
            'formulador_maestro',
        ]);
    }

    public function canManageUsersModule(): bool
    {
        return $this->isAdminUser() || $this->hasRole('director');
    }

    public function canManageParametrizacion(): bool
    {
        return $this->isAdminUser()
            || $this->hasAnyRole(['director', 'formulador_maestro']);
    }

    public function isReadOnlyUser(): bool
    {
        return $this->hasRole('consulta');
    }

    public function canMutateProjects(): bool
    {
        return $this->canAccessPanel() && !$this->isReadOnlyUser();
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isAdminUser()) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $slug))
            ->exists();
    }
}
