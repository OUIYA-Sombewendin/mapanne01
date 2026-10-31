<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Authentification par jeton Sanctum, destinée à la future application Flutter.
 */
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate(['device_name' => ['required', 'string', 'max:100']]);
        $user = RegisterController::register($request);

        return response()->json([
            'token' => $user->createToken($request->input('device_name'))->plainTextToken,
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = LoginController::findByLogin($data['login']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => 'Identifiants incorrects.']);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['login' => 'Compte suspendu.']);
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return response()->json([
            'token' => $user->createToken($data['device_name'])->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    private function userPayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'role' => $user->role->value,
            'provider_status' => $user->provider?->status->value,
        ];
    }
}
