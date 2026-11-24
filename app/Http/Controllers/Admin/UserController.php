<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AdminMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        // Filter by the same rule isSuperAdmin() applies, not the legacy column.
        if ($request->role === 'super_admin') {
            $query->superAdmins();
        } elseif ($request->role === 'admin') {
            $query->whereNotIn('users.id', User::superAdmins()->select('users.id'));
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

        // users.manage can be granted to a restricted role; it must not be a
        // way to mint accounts above that role.
        if ($validated['role'] === 'super_admin' && ! $request->user()->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role' => ['Only a super admin can create a super admin.'],
            ]);
        }

        $validated['is_active'] = $request->has('is_active');

        $user = User::create($validated);
        $user->assignPanelRole($validated['role']);

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
    public function edit(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);

        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update user details.
     */
    public function update(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);

        $actor = $request->user();
        $isSelf = $actor->is($user);
        $wasSuperAdmin = $user->isSuperAdmin();

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
            // The form does not offer these on your own account.
            'role' => [Rule::requiredIf(! $isSelf), Rule::in(['admin', 'super_admin'])],
            'is_active' => 'boolean',
        ]);

        // Your own role and status are not editable here, so nobody can
        // promote themselves or lock themselves out through this form.
        $role = $isSelf ? ($wasSuperAdmin ? 'super_admin' : 'admin') : $validated['role'];
        $isActive = $isSelf ? (bool) $user->is_active : $request->has('is_active');

        if ($role === 'super_admin' && ! $wasSuperAdmin && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role' => ['Only a super admin can grant super admin.'],
            ]);
        }

        if ($wasSuperAdmin && ($role !== 'super_admin' || ! $isActive) && $this->isLastActiveSuperAdmin($user)) {
            throw ValidationException::withMessages([
                $role !== 'super_admin' ? 'role' : 'is_active' => ['The last active super admin cannot be demoted or deactivated.'],
            ]);
        }

        $passwordChanged = filled($validated['password'] ?? null);
        $roleChanged = ($role === 'super_admin') !== $wasSuperAdmin;

        $user->fill(Arr::only($validated, ['name', 'username', 'email']));
        $user->is_active = $isActive;

        if ($passwordChanged) {
            $user->password = $validated['password'];
            // Remember-me cookies are checked against this token, not the
            // password, so rotating it ends every one issued before the reset.
            $user->setRememberToken(Str::random(60));
        }

        $user->save();

        if ($roleChanged) {
            $user->assignPanelRole($role);
        }

        // API tokens outlive a reset or demotion unless revoked.
        if ($passwordChanged || $roleChanged || ! $isActive) {
            $user->tokens()->delete();
        }

        // Other sessions of this account end on their next request, when
        // AdminMiddleware sees the new hash. Keep the one making the change.
        if ($passwordChanged && $isSelf) {
            AdminMiddleware::rememberPasswordHash($request, $user);
        }

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
    public function destroy(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);

        if ((int) auth()->id() === (int) $user->id) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        if ($this->isLastActiveSuperAdmin($user)) {
            return redirect()->back()->with('error', 'Cannot delete the last active super admin.');
        }

        $user->tokens()->delete();
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
    public function activate(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);

        $user->update(['is_active' => true]);

        return redirect()->back()->with('success', 'User activated successfully.');
    }

    /**
     * Deactivate user.
     */
    public function deactivate(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);

        if ((int) auth()->id() === (int) $user->id) {
            return redirect()->back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($this->isLastActiveSuperAdmin($user)) {
            return redirect()->back()->with('error', 'Cannot deactivate the last active super admin.');
        }

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return redirect()->back()->with('success', 'User deactivated successfully.');
    }

    /**
     * Only a super admin may change a super admin's account.
     *
     * users.manage can be granted to a restricted role, and must not reach
     * above it: resetting the owner's password or email would be a takeover.
     */
    private function authorizeTarget(Request $request, User $user): void
    {
        if ($user->isSuperAdmin() && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Only a super admin can change a super admin account.');
        }
    }

    /**
     * Is this the only active account that can still administer the platform?
     */
    private function isLastActiveSuperAdmin(User $user): bool
    {
        return $user->is_active
            && $user->isSuperAdmin()
            && ! User::superAdmins()
                ->where('is_active', true)
                ->whereKeyNot($user->getKey())
                ->exists();
    }
}
