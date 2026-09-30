<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success('OK', ['user' => new UserResource($request->user())]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $throttleKey = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return ApiResponse::error("Too many login attempts. Try again in {$seconds} seconds.", 429, ['retry_after' => $seconds]);
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 900);

            return ApiResponse::error('Invalid credentials.', 401);
        }

        RateLimiter::clear($throttleKey);

        $newToken = $user->createToken('api', expiresAt: now()->addMinutes(config('sanctum.expiration')));

        return ApiResponse::success('Logged in.', [
            'token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $newToken->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success('Logged out.');
    }
}
