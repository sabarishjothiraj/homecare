<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display users listing.
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    /**
     * Show create form.
     */
    public function create()
    {
        return view('admin.users.create');
    }

    /**
     * Store a new user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role' => ['required', Rule::in(['admin', 'super_admin'])],
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $user = User::create($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'User created successfully.',
                'redirect' => route('admin.users.show', $user),
            ]);
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'User created successfully.');
    }

    /**
     * Show user details.
     */
    public function show(User $user)
    {
        return view('admin.users.show', compact('user'));
    }

    /**
     * Show edit form.
     */
    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update user details.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:6|confirmed',
            'role' => ['required', Rule::in(['admin', 'super_admin'])],
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'User updated successfully.',
                'redirect' => route('admin.users.show', $user),
            ]);
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    /**
     * Delete user.
     */
    public function destroy(User $user)
    {
        if ((int) auth()->id() === (int) $user->id) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        $activeSuperAdmins = User::where('role', 'super_admin')
            ->where('is_active', true)
            ->count();

        if ($user->role === 'super_admin' && $user->is_active && $activeSuperAdmins <= 1) {
            return redirect()->back()->with('error', 'Cannot delete the last active super admin.');
        }

        $user->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully.',
                'redirect' => route('admin.users.index'),
            ]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Activate user.
     */
    public function activate(User $user)
    {
        $user->update(['is_active' => true]);

        return redirect()->back()->with('success', 'User activated successfully.');
    }

    /**
     * Deactivate user.
     */
    public function deactivate(User $user)
    {
        if ((int) auth()->id() === (int) $user->id) {
            return redirect()->back()->with('error', 'You cannot deactivate your own account.');
        }

        $activeSuperAdmins = User::where('role', 'super_admin')
            ->where('is_active', true)
            ->count();

        if ($user->role === 'super_admin' && $user->is_active && $activeSuperAdmins <= 1) {
            return redirect()->back()->with('error', 'Cannot deactivate the last active super admin.');
        }

        $user->update(['is_active' => false]);

        return redirect()->back()->with('success', 'User deactivated successfully.');
    }
}
