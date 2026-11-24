<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserRoleController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with('roles:id,name,slug')
            ->select('id', 'name', 'username', 'email', 'role', 'is_active')
            ->latest()
            ->paginate(20);

        return response()->json($users);
    }

    public function syncRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            // At least one: an account with no role rows at all resolves
            // through the broader legacy users.role column instead.
            'role_ids' => 'required|array|min:1',
            'role_ids.*' => ['integer', 'distinct', Rule::exists('roles', 'id')->whereNull('deleted_at')],
        ]);

        $superAdminRoleId = Role::where('slug', 'super_admin')->value('id');
        $keepsSuperAdmin = $superAdminRoleId !== null
            && in_array((int) $superAdminRoleId, array_map('intval', $validated['role_ids']), true);

        if ($user->isSuperAdmin() && ! $keepsSuperAdmin) {
            if ($request->user()->is($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot remove your own super admin role.',
                ], 422);
            }

            if ($user->is_active && ! User::superAdmins()->where('is_active', true)->whereKeyNot($user->getKey())->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The last active super admin cannot be demoted.',
                ], 422);
            }
        }

        $changes = $user->roles()->sync($validated['role_ids']);

        // Mirror onto users.role, so neither store can claim a role the other
        // has taken away.
        $user->syncLegacyRole();

        // Access was reduced: tokens issued under the old roles go with it.
        if ($changes['detached'] !== []) {
            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'User roles updated successfully.',
            'roles' => $user->roles()->pluck('slug')->all(),
        ]);
    }
}
