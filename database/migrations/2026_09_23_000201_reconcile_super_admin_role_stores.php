<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Make users.role and the role_user pivot agree on who is a super admin.
 *
 * The admin panel used to write only users.role and the roles API only the
 * pivot, while isSuperAdmin() OR-ed the two, so a demotion through either
 * surface left the user a super admin. isSuperAdmin() now follows the pivot
 * for any account that has one, and both surfaces write both stores.
 *
 * Where the stores disagree today there is no telling which was written last:
 * a panel demotion looks exactly like an API promotion. Such accounts are
 * resolved to the lesser role, unless that would leave no active super admin
 * at all - then the disagreeing active accounts keep super admin, as they had
 * it before. Every change is logged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_user')) {
            return;
        }

        $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');

        if (! $superAdminRoleId) {
            return;
        }

        $pivotSuperAdmins = DB::table('role_user')
            ->where('role_id', $superAdminRoleId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Accounts with no pivot rows resolve through users.role alone, so
        // they cannot disagree with themselves.
        $conflicts = DB::table('users')
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('role_user')
                ->whereColumn('role_user.user_id', 'users.id'))
            ->get(['id', 'email', 'role', 'is_active', 'deleted_at'])
            ->filter(fn ($user) => in_array((int) $user->id, $pivotSuperAdmins, true) !== ($user->role === 'super_admin'));

        if ($conflicts->isEmpty()) {
            return;
        }

        $keepActiveOnes = ! $this->agreedActiveSuperAdminExists($superAdminRoleId);
        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        foreach ($conflicts as $user) {
            if ($keepActiveOnes && $user->is_active && $user->deleted_at === null) {
                DB::table('users')->where('id', $user->id)->update(['role' => 'super_admin']);
                DB::table('role_user')->insertOrIgnore([
                    'role_id' => $superAdminRoleId,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::warning("[RBAC] Kept super admin for user #{$user->id} ({$user->email}): its role stores disagreed and no other active super admin exists.");

                continue;
            }

            DB::table('users')->where('id', $user->id)->update(['role' => 'admin']);
            DB::table('role_user')
                ->where('user_id', $user->id)
                ->where('role_id', $superAdminRoleId)
                ->delete();

            // Keep the account on the pivot, where it now resolves.
            if ($adminRoleId && ! DB::table('role_user')->where('user_id', $user->id)->exists()) {
                DB::table('role_user')->insert([
                    'role_id' => $adminRoleId,
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Log::warning("[RBAC] Removed super admin from user #{$user->id} ({$user->email}): users.role and role_user disagreed. Re-grant it from Users if it was intended.");
        }
    }

    public function down(): void
    {
        // Not reversible: which store was right cannot be recovered, and
        // restoring the disagreement would restore the privilege escalation.
    }

    /**
     * Is there an active super admin on whom both stores already agree?
     */
    private function agreedActiveSuperAdminExists(int $superAdminRoleId): bool
    {
        return DB::table('users')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('role', 'super_admin')
            ->where(function ($query) use ($superAdminRoleId) {
                $query->whereExists(fn ($pivot) => $pivot->select(DB::raw(1))
                    ->from('role_user')
                    ->whereColumn('role_user.user_id', 'users.id')
                    ->where('role_user.role_id', $superAdminRoleId))
                    ->orWhereNotExists(fn ($pivot) => $pivot->select(DB::raw(1))
                        ->from('role_user')
                        ->whereColumn('role_user.user_id', 'users.id'));
            })
            ->exists();
    }
};
