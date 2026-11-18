<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || $user->status !== User::STATUS_ACTIVE || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match an active account.'],
            ]);
        }

        return $this->respond([
            'token' => $user->issueToken(),
            'user' => $user,
        ], 'Signed in.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->respond($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('apiToken')?->delete();

        return $this->respond(null, 'Signed out.');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->password = $data['password'];
        $user->save();

        $current = $request->attributes->get('apiToken');
        $user->tokens()
            ->when($current instanceof ApiToken, fn ($query) => $query->whereKeyNot($current->id))
            ->delete();

        return $this->respond($user->fresh(), 'Password updated.');
    }
}
