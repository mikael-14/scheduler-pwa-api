<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Email + Password Login
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('pwa-login')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Email + Password Register
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:6', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('pwa-login')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    /**
     * Redirect to provider (facebook/google)
     */
    public function redirectToProvider(string $provider)
    {
        $allowedProviders = ['facebook', 'google'];

        if (! in_array($provider, $allowedProviders)) {
            return response()->json(['error' => 'Unsupported provider'], 400);
        }

        $redirectUrl = Socialite::driver($provider)
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $redirectUrl]);
    }

    /**
     * Handle provider callback (facebook/google)
     */
    public function handleProviderCallback(Request $request, string $provider)
    {
        try {
            $socialiteUser = Socialite::driver($provider)->user();

            $user = User::findOrCreateFromSocialite($socialiteUser, $provider);

            $token = $user->createToken('pwa-login')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $user,
            ]);
        } catch (\Throwable $e) {
            Log::error("{$provider} login error: " . $e->getMessage());
            return response()->json(['error' => 'Unable to authenticate with ' . $provider], 400);
        }
    }

    /**
     * Logout (revoke token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
