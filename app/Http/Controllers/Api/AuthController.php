<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriberProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ---------------------------------------------------------------------------
    // POST /api/login
    // ---------------------------------------------------------------------------
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)
            ->whereNull('deleted_at')
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->role !== 'subscriber') {
            return response()->json(['message' => 'Access denied'], 403);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account is inactive'], 403);
        }

        $token = $user->generateApiToken();

        return response()->json([
            'token' => $token,
            'user'  => $this->userShape($user),
        ]);
    }

    // ---------------------------------------------------------------------------
    // POST /api/register
    // ---------------------------------------------------------------------------
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => $request->password, // cast to hashed automatically
            'role'      => 'subscriber',
            'is_active' => true,
        ]);

        // Ensure a subscriber profile exists
        SubscriberProfile::firstOrCreate(['user_id' => $user->id]);

        $token = $user->generateApiToken();

        return response()->json([
            'token' => $token,
            'user'  => $this->userShape($user),
        ], 201);
    }

    // ---------------------------------------------------------------------------
    // POST /api/logout
    // ---------------------------------------------------------------------------
    public function logout(Request $request): JsonResponse
    {
        $request->user()->update(['api_token' => null]);

        return response()->json(['ok' => true]);
    }

    // ---------------------------------------------------------------------------
    // GET /api/me
    // ---------------------------------------------------------------------------
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userShape($request->user()),
        ]);
    }

    // ---------------------------------------------------------------------------
    // Shared user shape
    // ---------------------------------------------------------------------------
    private function userShape(User $user): array
    {
        // Derive subscription_status from the latest subscription on the profile
        $subscriptionStatus = 'none';

        $profile = $user->subscriberProfile()->first();

        if ($profile) {
            $latest = $profile->subscriptions()
                ->orderByDesc('created_at')
                ->first();

            $subscriptionStatus = $latest?->status ?? 'none';
        }

        return [
            'id'                  => $user->id,
            'name'                => $user->name,
            'email'               => $user->email,
            'role'                => $user->role,
            'subscription_status' => $subscriptionStatus,
            'image'               => null, // no avatar column yet
        ];
    }
}
