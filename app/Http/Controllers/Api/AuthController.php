<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\FacebookProvider;

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
                'email' => [__('The provided credentials are incorrect.')],
            ]);
        }
        $avatar = $user->getFilamentAvatarUrl();
        if ($avatar) {
            $user->avatar_url = asset($avatar);
        } else {
            $user->avatar_url = null;
        }
        $token = $user->createToken('pwa-login')->plainTextToken;
        $permissionsArray = $user->getPermissionNames()->toArray();
        return response()->json([
            'token' => $token,
            'user' => $user,
            'permissions' => $permissionsArray
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


    public function redirectToProvider(Request $request, string $provider)
    {
        $allowedProviders = ['facebook', 'google'];

        if (! in_array($provider, $allowedProviders)) {
            return response()->json(['error' => __('Unsupported provider')], 400);
        }

        // 1. Grab the raw config from your services.php
        $config = config("services.{$provider}");

        // 2. Inject your PWA callback URL directly into this config instance
        $config['redirect'] = url("/api/auth/{$provider}/callback");

        // 3. Build a pure, unadulterated Socialite Provider (bypassing Filament completely)
        $providerClass = match ($provider) {
            'google' => GoogleProvider::class,
            'facebook' => FacebookProvider::class,
            default => throw new \InvalidArgumentException('Unsupported provider')
        };
        $driver = Socialite::buildProvider($providerClass, $config);

        // 4. Since this is the raw driver, stateless() works perfectly!
        $redirectUrl = $driver->stateless()->redirect()->getTargetUrl();

        return response()->json(['url' => $redirectUrl]);
    }

    public function handleProviderCallback(Request $request, string $provider)
    {
        try {
            // 1. Rebuild the exact same raw config for the return trip
            $config = config("services.{$provider}");
            $config['redirect'] = url("/api/auth/{$provider}/callback");

            // 2. Build the pure provider again
            $providerClass = match ($provider) {
                'google' => GoogleProvider::class,
                'facebook' => FacebookProvider::class,
                default => throw new \InvalidArgumentException('Unsupported provider')
            };
            $driver = Socialite::buildProvider($providerClass, $config);

            // 3. Fetch the user statelessly!
            $socialiteUser = $driver->stateless()->user();

            // 4. Create your user & Sanctum token
            $user = User::findOrCreateFromSocialite($socialiteUser, $provider);
            $token = $user->createToken('pwa-login')->plainTextToken;

            // 5. Send them back to the PWA
            $pwaUrl = config('app.pwa_url', 'http://localhost:3000');
            return redirect()->away("{$pwaUrl}/auth/callback?token={$token}");
        } catch (\Throwable $e) {
            Log::error("PWA Social Login Error: " . $e->getMessage());

            $pwaUrl = config('app.pwa_url', 'http://localhost:3000');
            return redirect()->away("{$pwaUrl}/login?error=auth_failed");
        }
    }

    /**
     * Logout (revoke token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => __('Logged out')]);
    }

}
