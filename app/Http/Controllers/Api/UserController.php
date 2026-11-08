<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success('OK', [
            'users' => UserResource::collection(User::orderBy('name')->get()),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return ApiResponse::success('User created.', ['user' => new UserResource($user)], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update(array_filter($request->validated(), fn ($v) => $v !== null));

        return ApiResponse::success('User updated.', ['user' => new UserResource($user)]);
    }
}
