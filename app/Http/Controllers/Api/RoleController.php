<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
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
            'is_active' => 'nullable|boolean',
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
            'slug' => 'sometimes|required|string|max:255|alpha_dash|unique:roles,slug,' . $role->id,
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $role->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'data' => $role,
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully.',
        ]);
    }

    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permission_ids' => 'required|array|min:1',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $role->permissions()->sync($validated['permission_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Role permissions updated successfully.',
            'permission_slugs' => Permission::whereIn('id', $validated['permission_ids'])->pluck('slug')->all(),
        ]);
    }
}
