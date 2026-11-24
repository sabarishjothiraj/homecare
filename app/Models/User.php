<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    /**
     * Memoised RBAC lookups. Per-instance for the user's own roles and
     * permissions; per-process for whether the pivot tables exist at all.
     */
    protected ?array $roleSlugCache = null;

    protected ?array $permissionSlugCache = null;

    protected static ?bool $rbacTablesExist = null;

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

    /**
     * Does this installation have the RBAC pivot tables yet?
     *
     * Memoised per process: Schema::hasTable() queries information_schema, and
     * this used to run twice on every single permission check. An admin page
     * whose sidebar gates fifteen links paid for thirty of them per request.
     * The schema cannot change mid-request, so asking once is enough.
     */
    protected static function rbacTablesExist(): bool
    {
        return static::$rbacTablesExist ??= Schema::hasTable('roles')
            && Schema::hasTable('role_user')
            && Schema::hasTable('permissions')
            && Schema::hasTable('role_permissions');
    }

    /**
     * The roles this user effectively holds, read once and kept on the instance.
     *
     * Once an account has any row in the pivot, the pivot is the only source,
     * and an inactive or deleted role counts as no role at all. Only accounts
     * that have never been assigned a role resolve through the legacy
     * users.role column. Falling back whenever the *active* set was empty is
     * how deleting a user's only restricted role used to hand them the full
     * 'admin' role instead.
     *
     * @return array<int, string>
     */
    protected function roleSlugs(): array
    {
        if ($this->roleSlugCache !== null) {
            return $this->roleSlugCache;
        }

        $legacy = array_filter([strtolower((string) $this->role)]);

        if (! static::rbacTablesExist()) {
            return $this->roleSlugCache = $legacy;
        }

        // Read the pivot directly: roles() applies the soft-delete scope, which
        // would make a deleted role look like no assignment at all.
        $assignments = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $this->getKey())
            ->get(['roles.slug', 'roles.is_active', 'roles.deleted_at']);

        if ($assignments->isEmpty()) {
            return $this->roleSlugCache = $legacy;
        }

        return $this->roleSlugCache = $assignments
            ->filter(fn ($role) => $role->is_active && $role->deleted_at === null)
            ->map(fn ($role) => strtolower($role->slug))
            ->values()
            ->all();
    }

    /**
     * Permission slugs this user holds, resolved in a single query.
     *
     * @return array<int, string>
     */
    protected function permissionSlugs(): array
    {
        if ($this->permissionSlugCache !== null) {
            return $this->permissionSlugCache;
        }

        $roleSlugs = $this->roleSlugs();

        if ($roleSlugs === []) {
            return $this->permissionSlugCache = [];
        }

        // Deactivated permissions and roles grant nothing. The role filter
        // repeats roleSlugs()' for the legacy fallback, which names a role
        // without checking it.
        $slugs = Permission::query()
            ->where('permissions.is_active', true)
            ->whereHas('roles', fn ($query) => $query
                ->whereIn('roles.slug', $roleSlugs)
                ->where('roles.is_active', true))
            ->pluck('slug')
            ->all();

        return $this->permissionSlugCache = array_map('strtolower', $slugs);
    }

    /**
     * Drop the cached role/permission lookups.
     *
     * Call after changing a user's roles on an instance that will be reused
     * within the same request.
     */
    public function forgetRbacCache(): static
    {
        $this->roleSlugCache = null;
        $this->permissionSlugCache = null;

        return $this;
    }

    public function hasRole(string $role): bool
    {
        return in_array(strtolower(trim($role)), $this->roleSlugs(), true);
    }

    public function hasPermission(string $permission): bool
    {
        $slug = strtolower(trim($permission));

        // A super admin is never locked out of a permission-guarded route.
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! static::rbacTablesExist()) {
            return false;
        }

        return in_array($slug, $this->permissionSlugs(), true);
    }

    /**
     * Check if user is super admin
     *
     * Same precedence as every other role: the pivot when the account has
     * one, users.role only when it never has. OR-ing the two meant a demotion
     * written to either store alone left the user a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Users who are super admins by the same rule as isSuperAdmin().
     */
    public function scopeSuperAdmins(Builder $query): Builder
    {
        if (! static::rbacTablesExist()) {
            return $query->where('users.role', 'super_admin');
        }

        return $query->where(function (Builder $query) {
            $query->whereHas('roles', fn (Builder $roles) => $roles
                ->where('roles.slug', 'super_admin')
                ->where('roles.is_active', true))
                ->orWhere(function (Builder $query) {
                    $query->where('users.role', 'super_admin')
                        ->whereNotExists(fn ($pivot) => $pivot
                            ->select(DB::raw(1))
                            ->from('role_user')
                            ->whereColumn('role_user.user_id', 'users.id'));
                });
        });
    }

    /**
     * Apply the admin panel's Admin / Super Admin choice to both role stores.
     *
     * The pivot decides (see roleSlugs()); users.role mirrors it for accounts
     * and code that still read the column. Only super admin membership is
     * touched, so a user restricted to a custom role keeps that restriction.
     */
    public function assignPanelRole(string $role): void
    {
        $superAdmin = $role === 'super_admin';

        $this->forceFill(['role' => $superAdmin ? 'super_admin' : 'admin'])->save();

        if (static::rbacTablesExist()) {
            $roleIds = Role::whereIn('slug', ['super_admin', 'admin'])->pluck('id', 'slug');

            if ($superAdmin && isset($roleIds['super_admin'])) {
                $this->roles()->syncWithoutDetaching([$roleIds['super_admin']]);
            }

            if (! $superAdmin) {
                if (isset($roleIds['super_admin'])) {
                    $this->roles()->detach($roleIds['super_admin']);
                }

                // Left with no role at all, the account would resolve through
                // the column anyway; record it on the pivot so it stays there.
                if (isset($roleIds['admin']) && ! DB::table('role_user')->where('user_id', $this->getKey())->exists()) {
                    $this->roles()->attach($roleIds['admin']);
                }
            }
        }

        $this->forgetRbacCache();
    }

    /**
     * Copy the pivot's super admin membership onto users.role.
     *
     * For writers that change the pivot directly (the roles API), so the
     * column never claims a role the pivot has taken away.
     */
    public function syncLegacyRole(): void
    {
        $this->forgetRbacCache();

        $this->forceFill(['role' => $this->isSuperAdmin() ? 'super_admin' : 'admin'])->save();
    }

    /**
     * Find the account a login form names, if the password matches.
     *
     * An unknown identifier still costs one bcrypt, so the response time does
     * not reveal which emails or usernames have an account.
     */
    public static function findByCredentials(string $field, string $identifier, string $password): ?self
    {
        $user = static::where($field, $identifier)->first();

        if (! $user) {
            Hash::make($password);

            return null;
        }

        return Hash::check($password, $user->password) ? $user : null;
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
