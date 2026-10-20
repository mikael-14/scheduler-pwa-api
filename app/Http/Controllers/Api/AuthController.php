<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Sanctum\PersonalAccessToken;
use App\Support\DeviceName;

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
            event(new Failed('sanctum', $user, $credentials));

            throw ValidationException::withMessages([
                'email' => [__('The provided credentials are incorrect.')],
            ]);
        }

        // Checked after the password so this cannot be used to probe which emails exist
        if (! $user->status) {
            return response()->json([
                'message' => __('Your account is inactive.'),
                'code' => 'account_inactive',
            ], 403);
        }

        event(new Login('sanctum', $user, false));

        $avatar = $user->getFilamentAvatarUrl();
        if ($avatar) {
            $user->avatar_url = asset($avatar);
        } else {
            $user->avatar_url = null;
        }
        $token = $user->createToken(DeviceName::fromRequest($request))->plainTextToken;
        $permissionsArray = $user->permissionNamesForApps();
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
            'password' => ['required', 'min:8', 'confirmed'],
            // The PWA sends the language the visitor was using; otherwise the app default (pt)
            'locale' => ['nullable', 'string', \Illuminate\Validation\Rule::in(collect(config('app-locales.available'))->pluck('code')->all())],
        ]);

        // New accounts start with approved_at = null: they get a token (to read their own
        // state) but every other API route stays closed until an admin approves them.
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'locale' => $data['locale'] ?? app()->getLocale(),
        ]);

        event(new Login('sanctum', $user, false));

        $token = $user->createToken(DeviceName::fromRequest($request))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ], 201);
    }


    private const SOCIAL_PROVIDERS = ['facebook', 'google'];

    /** How long the person has to finish signing in at the provider. */
    private const SOCIAL_STATE_MINUTES = 10;

    /** How long a replaced token keeps working after a refresh. */
    private const REFRESH_GRACE_MINUTES = 5;

    /**
     * A bare Socialite driver for the API flow: stateless (no session), with the callback
     * pointing at this API. It bypasses the Filament plugin, which serves the admin panel.
     */
    private function socialDriver(string $provider)
    {
        $config = config("services.{$provider}");
        $config['redirect'] = url("/api/auth/{$provider}/callback");

        return Socialite::buildProvider(
            match ($provider) {
                'google' => GoogleProvider::class,
                'facebook' => FacebookProvider::class,
            },
            $config
        )->stateless();
    }

    private function pwaUrl(): string
    {
        return rtrim((string) config('app.pwa_url', 'http://localhost:3000'), '/');
    }

    /**
     * Step 1 of the social login: the PWA asks where to send the person.
     *
     * The flow has no session, so the OAuth `state` is a random value remembered in the cache for a few
     * minutes (with the language the PWA is using). The callback only accepts a state it issued, once:
     * without it anyone could push a victim's browser through the callback with the attacker's code
     * and sign them in as the attacker.
     */
    public function redirectToProvider(Request $request, string $provider)
    {
        if (! in_array($provider, self::SOCIAL_PROVIDERS, true)) {
            return response()->json(['error' => __('Unsupported provider')], 400);
        }

        $state = Str::random(40);
        Cache::put("social_state:{$state}", ['locale' => app()->getLocale()], now()->addMinutes(self::SOCIAL_STATE_MINUTES));

        $url = $this->socialDriver($provider)->with(['state' => $state])->redirect()->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    /**
     * Step 2: the provider sends the person back here. They are sent on to the PWA with a Sanctum
     * token in the URL *fragment*, which browsers never send to servers (no logs, no Referer).
     */
    public function handleProviderCallback(Request $request, string $provider)
    {
        $pwaUrl = $this->pwaUrl();

        try {
            if (! in_array($provider, self::SOCIAL_PROVIDERS, true)) {
                throw new \InvalidArgumentException('Unsupported provider');
            }

            $state = (string) $request->query('state');
            $issued = $state !== '' ? Cache::pull("social_state:{$state}") : null;

            if (! is_array($issued)) {
                throw new \RuntimeException('Missing, expired or reused OAuth state.');
            }

            $socialiteUser = $this->socialDriver($provider)->user();

            // The browser coming back from the provider carries its own Accept-Language;
            // a new account gets the language the person was using in the PWA instead.
            app()->setLocale($issued['locale']);

            $user = User::findOrCreateFromSocialite($socialiteUser, $provider);

            if (! $user->status) {
                return redirect()->away("{$pwaUrl}/login?error=account_inactive");
            }

            event(new Login('sanctum', $user, false));

            $token = $user->createToken(DeviceName::fromRequest($request))->plainTextToken;

            return redirect()->away("{$pwaUrl}/auth/callback#token={$token}");
        } catch (\Throwable $e) {
            Log::error('PWA Social Login Error: ' . $e->getMessage());

            return redirect()->away("{$pwaUrl}/login?error=auth_failed");
        }
    }

    /**
     * Swap the current token for a fresh one, so a session lasts as long as the app is used
     * (each token still expires after sanctum.expiration). The PWA calls this about once a day.
     * The old token keeps working for a few minutes: requests already on their way, or another
     * open tab that has not picked up the new token yet, are not thrown out.
     */
    public function refresh(Request $request)
    {
        $current = $request->user()->currentAccessToken();

        if (! $current instanceof PersonalAccessToken) {
            return response()->json(['message' => __('Only API tokens can be renewed.')], 400);
        }

        $token = $request->user()->createToken($current->name)->plainTextToken;

        // never extends a token: only shortens it to the grace period
        $graceEnd = now()->addMinutes(self::REFRESH_GRACE_MINUTES);
        if (! $current->expires_at || $current->expires_at->greaterThan($graceEnd)) {
            $current->forceFill(['expires_at' => $graceEnd])->save();
        }

        return response()->json([
            'token' => $token,
            'expires_in' => (int) config('sanctum.expiration') * 60,
        ]);
    }

    /**
     * Logout (revoke token)
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        // the authentication log closes this device's entry (logout time), as for the admin panel
        event(new Logout('sanctum', $user));

        return response()->json(['message' => __('Logged out')]);
    }

}
