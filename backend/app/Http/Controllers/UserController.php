<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'role' => ['nullable', Rule::in([User::ROLE_ADMIN, User::ROLE_STAFF])],
            'status' => ['nullable', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = User::query()->orderBy('name');

        if ($request->filled('role')) {
            $query->where('role', $request->string('role')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $user = User::create($this->validated($request));

        return $this->respond($user, 'User created.', 201);
    }

    public function show(User $user): JsonResponse
    {
        return $this->respond($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $this->validated($request, $user);
        $this->guardAccount($request, $user, $data['role'], $data['status']);

        $passwordChanged = array_key_exists('password', $data);
        $user->fill($data);
        $user->save();

        if ($user->status !== User::STATUS_ACTIVE) {
            $user->tokens()->delete();
        } elseif ($passwordChanged) {
            $current = $request->user()->is($user) ? $request->attributes->get('apiToken') : null;
            $user->tokens()
                ->when($current instanceof ApiToken, fn ($query) => $query->whereKeyNot($current->id))
                ->delete();
        }

        return $this->respond($user->fresh(), 'User updated.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->guardAccount($request, $user, $user->role, User::STATUS_INACTIVE);

        $user->status = User::STATUS_INACTIVE;
        $user->save();
        $user->tokens()->delete();

        return $this->respond($user, 'User deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_STAFF])],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        unset($data['password_confirmation']);

        return $data;
    }

    private function guardAccount(Request $request, User $user, string $role, string $status): void
    {
        if ($request->user()->is($user) && $status !== User::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'status' => ['You cannot deactivate your own account.'],
            ]);
        }

        $staysAdmin = $user->role === User::ROLE_ADMIN
            && $user->status === User::STATUS_ACTIVE
            && $role === User::ROLE_ADMIN
            && $status === User::STATUS_ACTIVE;

        if ($user->role === User::ROLE_ADMIN && $user->status === User::STATUS_ACTIVE && ! $staysAdmin) {
            $otherAdmin = User::query()
                ->where('role', User::ROLE_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->whereKeyNot($user->id)
                ->exists();

            if (! $otherAdmin) {
                throw ValidationException::withMessages([
                    'role' => ['The system must keep at least one active administrator.'],
                ]);
            }
        }
    }
}
