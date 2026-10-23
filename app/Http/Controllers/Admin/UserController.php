<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Exceptions\UserManagementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeUserRoleRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** User management for the owner: edit, role, activate, deactivate, delete. */
class UserController extends Controller
{
    public function __construct(private readonly UserManagementService $users) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $users = User::query()
            ->whereNull('deleted_at')
            ->when($filters['q'] ?? null, fn ($query, string $q) => $query->where(
                fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")
            ))
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'filters' => $filters, 'roles' => UserRole::cases()]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);
        $this->ensureVisible($user);

        return view('admin.users.edit', ['user' => $user, 'roles' => UserRole::cases()]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->ensureVisible($user);
        $this->users->update($user, $request->validated());

        return redirect()->route('admin.users.edit', $user)->with('status', 'User details saved.');
    }

    public function role(ChangeUserRoleRequest $request, User $user): RedirectResponse
    {
        $this->ensureVisible($user);
        $role = UserRole::from($request->validated('role'));

        return $this->attempt($user, fn () => $this->users->changeRole($user, $role, $request->user()), "Role changed to {$role->label()}.");
    }

    public function activate(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $this->ensureVisible($user);

        return $this->attempt($user, fn () => $this->users->activate($user), "{$user->name} is active again.");
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $this->ensureVisible($user);

        return $this->attempt($user, fn () => $this->users->deactivate($user, $request->user()), "{$user->name} was deactivated and cannot log in.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);
        $this->ensureVisible($user);

        try {
            $this->users->delete($user, $request->user());
        } catch (UserManagementException $e) {
            return redirect()->route('admin.users.edit', $user)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.users.index')->with('status', "{$user->name} was deleted.");
    }

    /** Run a change and go back to the edit page with a message. */
    private function attempt(User $user, callable $change, string $success): RedirectResponse
    {
        try {
            $change();
        } catch (UserManagementException $e) {
            return redirect()->route('admin.users.edit', $user)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.users.edit', $user)->with('status', $success);
    }

    private function ensureVisible(User $user): void
    {
        abort_if($user->deleted_at !== null, 404);
    }
}
