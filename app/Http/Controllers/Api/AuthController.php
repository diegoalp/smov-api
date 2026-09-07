<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequests\LoginUserRequest;
use App\Http\Requests\UserRequests\RegisterUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterUserRequest $request): JsonResponse
    {
        $attributes = $request->validated();

        $user = User::create([
            'name'=>$attributes['name'],'lastname'=>$attributes['lastname'] ?? null,
            'birthdate'=>$attributes['birthdate'] ?? null,'email'=>$attributes['email'],'password'=>$attributes['password'],
            'type'=>\App\Enums\UserType::Admin,'instance_id'=>null,
        ]);

        return response()->json([
            'user' => $user,
            ...$this->issueToken($user, $attributes['device_name'] ?? 'api'),
        ], 201);
    }

    public function login(LoginUserRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais informadas são inválidas.'],
            ]);
        }

        return response()->json([
            'user' => $user,
            ...$this->issueToken($user, $credentials['device_name'] ?? 'api'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Token revogado com sucesso.']);
    }

    /**
     * @return array{token: string, token_type: string, expires_in: int, expires_at: string}
     */
    private function issueToken(User $user, string $deviceName): array
    {
        $expirationMinutes = (int) config('sanctum.expiration');
        $expiresAt = now()->addMinutes($expirationMinutes);
        $token = $user->createToken($deviceName, ['*'], $expiresAt);

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $expirationMinutes * 60,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }
}
