<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::with('brands:id,name,slug')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                ...$user->only('id', 'name', 'email'),
                'role' => $user->role->value,
                'brands' => $user->brands->map->only('id', 'name')->all(),
            ]);

        return Inertia::render('Admin/Users/Index', ['users' => $users]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'editUser' => null,
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
            'roles' => array_map(fn (UserRole $r) => $r->value, UserRole::cases()),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
        ]);

        $user->brands()->sync($data['brand_ids'] ?? []);

        return redirect()->route('users.index');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'editUser' => [
                ...$user->only('id', 'name', 'email'),
                'role' => $user->role->value,
                'brand_ids' => $user->brands()->pluck('brands.id')->all(),
            ],
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
            'roles' => array_map(fn (UserRole $r) => $r->value, UserRole::cases()),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            ...filled($data['password'] ?? null) ? ['password' => $data['password']] : [],
        ]);

        $user->brands()->sync($data['brand_ids'] ?? []);

        return redirect()->route('users.index');
    }
}
