<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function permissions()
    {
        return Permission::query()->whereHas('roles.users', function ($query) {
            $query->where('users.id', $this->id);
        });
    }

    public function hasRole(string $role): bool
    {
        $slug = strtolower(trim($role));

        if (!Schema::hasTable('roles') || !Schema::hasTable('role_user')) {
            return $this->role === $slug;
        }

        if ($this->relationLoaded('roles')) {
            if ($this->roles->isEmpty()) {
                return $this->role === $slug;
            }

            return $this->roles->contains(fn ($r) => strtolower($r->slug) === $slug);
        }

        if ($this->roles()->exists()) {
            return $this->roles()->where('slug', $slug)->exists();
        }

        // Account predates the roles pivot — fall back to users.role.
        return $this->role === $slug;
    }

    public function hasPermission(string $permission): bool
    {
        $slug = strtolower(trim($permission));

        if (!Schema::hasTable('permissions') || !Schema::hasTable('role_permissions')) {
            return $this->isSuperAdmin();
        }

        // Checks the legacy users.role column as well as the pivot, so a super
        // admin is never locked out of a permission-guarded route.
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Accounts with no pivot rows yet resolve against the role named on
        // the legacy users.role column, so granting permissions to a role
        // takes effect before every user has been migrated onto the pivot.
        if ($this->roles()->doesntExist()) {
            return Role::where('slug', $this->role)
                ->whereHas('permissions', function ($query) use ($slug) {
                    $query->where('slug', $slug);
                })
                ->exists();
        }

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($slug) {
                $query->where('slug', $slug);
            })
            ->exists();
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin' || $this->hasRole('super_admin');
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true)
            || $this->hasRole('admin')
            || $this->hasRole('super_admin');
    }

    /**
     * Blogs authored by this user
     */
    public function blogs()
    {
        return $this->hasMany(Blog::class, 'author_id');
    }

    /**
     * Notifications for this user
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}

