<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function managers(): JsonResponse
    {
        $managers = User::query()
            ->where('role', 'admin')
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();

        return response()->json($managers);
    }

    public function verifyManagerPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'manager_user_id' => ['required', 'exists:users,id'],
            'manager_pin' => ['required', 'string', 'min:4', 'max:20'],
        ]);

        $manager = User::query()->find($validated['manager_user_id']);

        if (! $manager || $manager->role !== 'admin' || empty($manager->manager_pin) || ! Hash::check($validated['manager_pin'], $manager->manager_pin)) {
            return response()->json([
                'message' => 'PIN manager tidak valid.',
                'valid' => false,
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'manager' => [
                'id' => $manager->id,
                'name' => $manager->name,
                'email' => $manager->email,
            ],
        ]);
    }

    public function index(): JsonResponse
    {
        $users = User::query()->latest()->paginate(15);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::default()],
            'role' => ['required', 'in:admin,cashier'],
        ]);

        $user = User::create($validated);

        return response()->json($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', Password::default()],
            'role' => ['required', 'in:admin,cashier'],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }
}
