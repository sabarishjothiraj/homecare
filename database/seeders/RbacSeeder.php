<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RbacSeeder extends Seeder
{
    /**
     * Permissions granted to the Admin role.
     *
     * Everything operational. Platform-level concerns — user accounts, roles,
     * settings and OTP config — stay with Super Admin.
     */
    protected array $adminPermissions = [
        'dashboard.view',
        'employees.view',
        'employees.manage',
        'patients.view',
        'patients.manage',
        'contracts.view',
        'contracts.manage',
        'payments.view',
        'payments.manage',
        'payouts.view',
        'payouts.manage',
        'reports.view',
        'cms.manage',
        'queries.manage',
        'notifications.view',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => 'Full platform access'],
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Restricted administrative access'],
        ];

        // is_active is left to the column default on create and never reset
        // on a re-run: a role the owner deactivated stays deactivated.
        // withTrashed() so a deleted role is not re-inserted over its slug.
        foreach ($roles as $roleData) {
            Role::withTrashed()->updateOrCreate(
                ['slug' => $roleData['slug']],
                [
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                ]
            );
        }

        // Platform administration — Super Admin only.
        $permissions = [
            'roles.create',
            'roles.read',
            'roles.update',
            'roles.delete',
            'permissions.assign',
            'users.manage',
            'otp.config.update',
            'settings.manage',
            'castes.manage',
            'employee_fields.manage',
        ];

        // Day-to-day admin panel modules.
        $permissions = array_merge($permissions, $this->adminPermissions);

        foreach ($permissions as $permissionSlug) {
            Permission::withTrashed()->updateOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => Str::of($permissionSlug)->replace(['.', '_'], ' ')->title()->toString(),
                    'module' => Str::before($permissionSlug, '.'),
                ]
            );
        }

        $superAdminRole = Role::where('slug', 'super_admin')->first();

        if ($superAdminRole) {
            $permissionIds = Permission::pluck('id')->all();
            $superAdminRole->permissions()->sync($permissionIds);
        }

        $adminRole = Role::where('slug', 'admin')->first();

        if ($adminRole) {
            $adminPermissionIds = Permission::whereIn('slug', $this->adminPermissions)->pluck('id')->all();
            $adminRole->permissions()->sync($adminPermissionIds);
        }

        $this->attachRolesToExistingUsers();
    }

    /**
     * Put accounts that have no role yet on the pivot, using their legacy
     * `role` column.
     *
     * Accounts that already have a role row are left alone: their roles were
     * assigned deliberately, and adding the column's role back would undo a
     * restriction (a user limited to a custom role would regain 'admin').
     */
    protected function attachRolesToExistingUsers(): void
    {
        $rolesBySlug = Role::pluck('id', 'slug');

        User::query()
            ->whereNotNull('role')
            ->whereNotExists(fn ($pivot) => $pivot->select(DB::raw(1))
                ->from('role_user')
                ->whereColumn('role_user.user_id', 'users.id'))
            ->each(function (User $user) use ($rolesBySlug) {
                $roleId = $rolesBySlug[$user->role] ?? null;

                if ($roleId) {
                    $user->roles()->syncWithoutDetaching([$roleId]);
                }
            });
    }
}
