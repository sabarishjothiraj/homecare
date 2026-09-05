<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
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

        foreach ($roles as $roleData) {
            Role::updateOrCreate(
                ['slug' => $roleData['slug']],
                [
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                    'is_active' => true,
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
            Permission::updateOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => Str::of($permissionSlug)->replace(['.', '_'], ' ')->title()->toString(),
                    'module' => Str::before($permissionSlug, '.'),
                    'is_active' => true,
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
     * Give every existing account the role matching its legacy `role` column.
     *
     * Without this the pivot stays empty, hasPermission() is false for
     * everyone, and any route guarded by the permission middleware 403s even
     * for the super admin.
     */
    protected function attachRolesToExistingUsers(): void
    {
        $rolesBySlug = Role::pluck('id', 'slug');

        User::query()->whereNotNull('role')->each(function (User $user) use ($rolesBySlug) {
            $roleId = $rolesBySlug[$user->role] ?? null;

            if ($roleId) {
                $user->roles()->syncWithoutDetaching([$roleId]);
            }
        });
    }
}
