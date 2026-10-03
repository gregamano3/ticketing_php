<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['department', 'roles', 'permissions'])
            ->withCount(['assignedTickets as open_assigned_count' => fn ($q) => $q->open()])
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'ilike', "%{$term}%")->orWhere('email', 'ilike', "%{$term}%")))
            ->when($request->query('role'), fn ($q, $role) => $q->role($role))
            ->when($request->query('department'), fn ($q, $d) => $q->where('department_id', $d))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'departments' => Department::orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User(['is_active' => true]), 'departments' => Department::orderBy('name')->get()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create([...$request->safe()->except(['role', 'password', 'is_active', 'can_triage']), 'password' => $request->validated('password'), 'is_active' => $request->boolean('is_active')]);
        $user->syncRoles([$request->validated('role')]);
        $this->syncTriageAccess($request, $user);

        return redirect()->route('admin.users.index')->with('success', "User {$user->name} created.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user, 'departments' => Department::orderBy('name')->get()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->except(['role', 'password', 'is_active', 'can_triage']);
        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        // Never let an admin lock themselves out.
        $isSelf = $user->is($request->user());
        $data['is_active'] = $isSelf ? true : $request->boolean('is_active');

        $user->update($data);
        if (! $isSelf) {
            $user->syncRoles([$request->validated('role')]);
        }
        $this->syncTriageAccess($request, $user);

        return redirect()->route('admin.users.index')->with('success', "User {$user->name} updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'You cannot deactivate yourself.');

        // Users own tickets and history, so they are deactivated rather than deleted.
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? "{$user->name} reactivated." : "{$user->name} deactivated.");
    }

    /** Triage is a first-line duty granted to individual agents (admins always have it). */
    private function syncTriageAccess(UserRequest $request, User $user): void
    {
        $request->boolean('can_triage') && $user->hasRole('agent')
            ? $user->givePermissionTo('tickets.triage')
            : $user->revokePermissionTo('tickets.triage');
    }
}
