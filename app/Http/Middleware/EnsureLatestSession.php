<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureLatestSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && $token->exists && $token->getKey() !== (int) $request->user()->tokens()->max('id')) {
            $token->delete();

            return ApiResponse::error('Signed in on another device.', 401);
        }

        return $next($request);
    }
}
