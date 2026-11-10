<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $map = fn (User $user) => [
            ...$user->only('id', 'name', 'email'),
            'role' => $user->role->value,
            'brands' => $user->brands->map->only('id', 'name')->all(),
            'deleted_at' => $user->deleted_at?->toIso8601String(),
            'is_self' => $user->id === $request->user()->id,
        ];

        return Inertia::render('Admin/Users/Index', [
            'users' => User::with('brands:id,name,slug')->orderBy('name')->get()->map($map),
            'deletedUsers' => User::onlyTrashed()->with('brands:id,name,slug')
                ->latest('deleted_at')->get()->map($map),
        ]);
    }

    /**
     * Soft delete: el usuario deja de poder entrar (Auth excluye los
     * eliminados) y su sesión cae en la siguiente petición. Conserva
     * sus marcas por si se restaura. Nunca a uno mismo ni al último admin.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages(['user' => 'No puedes eliminar tu propio usuario.']);
        }

        if ($user->isAdmin() && User::where('role', UserRole::Admin)->count() <= 1) {
            throw ValidationException::withMessages(['user' => 'No puedes eliminar al último administrador.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', "{$user->name} eliminado.");
    }

    public function restore(int $user): RedirectResponse
    {
        $model = User::onlyTrashed()->findOrFail($user);
        $model->restore();

        return redirect()->route('users.index')->with('success', "{$model->name} restaurado.");
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
            // Los usuarios creados por el admin pasan por el asistente.
            'onboarded_at' => null,
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
