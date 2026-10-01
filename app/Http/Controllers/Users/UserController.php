<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ChangePasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $query = User::query()->with('role')->orderBy('id');

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('username', 'ilike', "%{$search}%")
                  ->orWhere('display_name', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', (int) $request->query('role_id'));
        }

        if ($request->filled('status')) {
            $status = $request->query('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('subject_id')) {
            $subjectId = (int) $request->query('subject_id');
            $query->whereJsonContains('eligible_subject_ids', $subjectId);
        }

        $users = $query->paginate(20)->withQueryString();
        $roles = Role::query()->orderBy('id')->get();
        $subjects = \App\Models\Subject::query()->orderBy('name')->get();

        return view('users.index', compact('users', 'roles', 'subjects'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->userService->createUser($request->validated(), (int) Auth::id());

        return redirect()->route('users.index')
            ->with('success', 'User account created successfully.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->updateUser($user, $request->validated(), (int) Auth::id());

        return redirect()->route('users.index')
            ->with('success', "User '{$user->username}' updated successfully.");
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('activate', $user);

        $this->userService->activateUser($user, (int) Auth::id());

        return redirect()->route('users.index')
            ->with('success', "User '{$user->username}' activated successfully.");
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        $this->userService->deactivateUser($user, (int) Auth::id());

        return redirect()->route('users.index')
            ->with('success', "User '{$user->username}' deactivated successfully.");
    }

    public function changePassword(ChangePasswordRequest $request, User $user): RedirectResponse
    {
        $this->userService->changePassword($user, $request->validated('password'), (int) Auth::id());

        return redirect()->route('users.index')
            ->with('success', "Password for '{$user->username}' has been updated successfully.");
    }
}
