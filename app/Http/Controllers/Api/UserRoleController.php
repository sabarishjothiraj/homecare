<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'role_ids' => 'required|array|min:1',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        $user->roles()->sync($validated['role_ids']);

        return response()->json([
            'success' => true,
            'message' => 'User roles updated successfully.',
            'roles' => $user->roles()->pluck('slug')->all(),
        ]);
    }
}
