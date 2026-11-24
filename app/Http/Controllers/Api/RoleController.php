<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Roles the application refers to by slug.
     */
    private const BUILT_IN = ['super_admin', 'admin'];

    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->latest()->paginate(20);

        return response()->json($roles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|alpha_dash|unique:roles,slug',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $role = Role::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully.',
            'data' => $role,
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');

        return response()->json($role);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|alpha_dash|unique:roles,slug,'.$role->id,
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        // The built-in slugs are what users.role and the code name; renaming
        // one silently detaches every account that resolves through it.
        if (isset($validated['slug']) && $validated['slug'] !== $role->slug && in_array($role->slug, self::BUILT_IN, true)) {
            return response()->json([
                'success' => false,
                'message' => 'The slug of a built-in role cannot be changed.',
            ], 422);
        }

        if (array_key_exists('is_active', $validated) && ! $validated['is_active'] && $role->slug === 'super_admin') {
            return $this->superAdminRoleIsPermanent();
        }

        $role->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'data' => $role,
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->slug === 'super_admin') {
            return $this->superAdminRoleIsPermanent();
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully.',
        ]);
    }

    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            // May be empty, so a role's access can be removed without
            // deleting the role.
            'permission_ids' => 'present|array',
            'permission_ids.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')->whereNull('deleted_at')],
        ]);

        $role->permissions()->sync($validated['permission_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Role permissions updated successfully.',
            'permission_slugs' => Permission::whereIn('id', $validated['permission_ids'])->pluck('slug')->all(),
        ]);
    }

    /**
     * Every super admin holds the super_admin role, and the role API itself
     * requires it: deactivating or deleting it would lock everyone out.
     */
    private function superAdminRoleIsPermanent(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'The super admin role cannot be deactivated or deleted.',
        ], 422);
    }
}
