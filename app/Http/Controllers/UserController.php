<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** User management for the Super Admin. */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::with(['roles', 'manager:id,name'])
            ->withCount('onboardedDealers')
            ->when($request->query('role'), fn ($q, $role) => $q->role($role))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User(['is_active' => true]), 'managers' => $this->managers()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $user = User::create($data);
            $user->syncRoles([$data['role']]);
        });

        return redirect()->route('users.index')->with('status', "{$data['name']} can now log in.");
    }

    public function edit(User $user): View
    {
        return view('users.form', ['user' => $user, 'managers' => $this->managers($user)]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && ($data['role'] !== User::SUPER_ADMIN || ! $data['is_active'])) {
            return back()->withInput()->withErrors(['role' => 'You cannot remove your own Super Admin access.']);
        }

        if ($user->isManager() && $data['role'] !== User::MANAGER && $user->teamMembers()->exists()) {
            return back()->withInput()->withErrors(['role' => 'Move this manager\'s employees to another manager first.']);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        DB::transaction(function () use ($user, $data) {
            $user->update($data);
            $user->syncRoles([$data['role']]);
        });

        return redirect()->route('users.index')->with('status', "{$user->name} updated.");
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'regex:/^[6-9][0-9]{9}$/'],
            'role' => ['required', Rule::in(array_keys(User::ROLE_LABELS))],
            'manager_id' => [
                Rule::requiredIf($request->input('role') === User::EMPLOYEE),
                'nullable',
                Rule::exists('users', 'id'),
                fn ($attr, $value, $fail) => $value && ! User::role(User::MANAGER)->whereKey($value)->exists() ? $fail('Choose a manager.') : null,
            ],
            'territory' => ['nullable', 'string', 'max:80'],
            'is_active' => ['boolean'],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
        ], [
            'manager_id.required' => 'Choose the manager this employee reports to.',
            'phone.regex' => 'Enter a valid 10-digit mobile number.',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        if ($data['role'] !== User::EMPLOYEE) {
            $data['manager_id'] = null;
        }

        return $data;
    }

    private function managers(?User $except = null)
    {
        return User::role(User::MANAGER)->when($except, fn ($q) => $q->whereKeyNot($except->id))->orderBy('name')->get(['id', 'name']);
    }
}
