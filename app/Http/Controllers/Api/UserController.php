<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AvatarImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class UserController extends Controller
{
    /** Largest profile photo accepted, in KB (it is stored much smaller, see AvatarImage). */
    private const AVATAR_MAX_KB = 10240;

    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->present($user),
            'permissions' => $user->permissionNamesForApps(),
        ]);
    }

    /**
     * The user as the PWA sees it: the photo as a full URL, whether there is a password to
     * confirm (accounts made with Google / Facebook have none) and the linked sign-in providers.
     */
    private function present(User $user): array
    {
        $avatar = $user->getFilamentAvatarUrl();

        return array_merge($user->toArray(), [
            'avatar_url' => $avatar ? asset($avatar) : null,
            'has_password' => $user->password !== null,
            'providers' => $user->socialiteUsers()->pluck('provider')->unique()->values()->all(),
        ]);
    }

    /**
     * Upload a new profile photo (jpg, png or webp, up to 10 MB), stored shrunk to avatar size.
     * Replaces the previous one.
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . self::AVATAR_MAX_KB],
        ]);

        $user = $request->user();
        $previous = $user->avatar_url;

        $image = AvatarImage::fromFile($request->file('avatar')->getRealPath());
        $path = 'avatars/' . Str::random(40) . '.' . $image['extension'];
        Storage::disk('public')->put($path, $image['contents']);

        $user->update(['avatar_url' => $path]);
        $this->deleteStoredAvatar($previous);

        return response()->json(['message' => __('Profile photo updated.'), 'user' => $this->present($user->fresh())]);
    }

    /** Remove the profile photo (the initials are shown instead). */
    public function deleteAvatar(Request $request)
    {
        $user = $request->user();
        $previous = $user->avatar_url;

        $user->update(['avatar_url' => null]);
        $this->deleteStoredAvatar($previous);

        return response()->json(['message' => __('Profile photo removed.'), 'user' => $this->present($user->fresh())]);
    }

    /** Only files the app stored itself (avatars/...) are ever deleted. */
    private function deleteStoredAvatar(?string $path): void
    {
        if ($path && str_starts_with($path, 'avatars/')) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Devices signed in to this account (one API token each), most recently used first.
     */
    public function sessions(Request $request)
    {
        $user = $request->user();
        $currentId = $user->currentAccessToken()->id ?? null;

        $tokens = $user->tokens()
            // tokens replaced by a renewal live a few minutes longer: they are not other devices
            ->where(fn($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()->addMinutes(10)))
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $tokens->map(fn(PersonalAccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'created_at' => $token->created_at,
                'last_used_at' => $token->last_used_at,
                'current' => $token->id === $currentId,
            ])->values(),
        ]);
    }

    /** Sign out one other device. The current one signs out with POST /auth/logout. */
    public function revokeSession(Request $request, int $id)
    {
        $user = $request->user();

        if ($id === ($user->currentAccessToken()->id ?? null)) {
            return response()->json(['message' => __('Use sign out for this device.')], 422);
        }

        $deleted = $user->tokens()->whereKey($id)->delete();

        return $deleted
            ? response()->json(['message' => __('Device signed out.')])
            : response()->json(['message' => __('Device not found.')], 404);
    }

    /** Sign out every device except this one. */
    public function revokeOtherSessions(Request $request)
    {
        $user = $request->user();
        $currentId = $user->currentAccessToken()->id ?? null;

        $count = $user->tokens()->when($currentId, fn($query) => $query->whereKeyNot($currentId))->delete();

        return response()->json(['message' => __(':count device(s) signed out.', ['count' => $count])]);
    }

    /**
     * Replace the authenticated user's profile (all of name, email, locale are required).
     */
    public function update(Request $request)
    {
        return $this->save($request, partial: false);
    }

    /**
     * Update some fields of the authenticated user's profile.
     */
    public function patch(Request $request)
    {
        return $this->save($request, partial: true);
    }

    /**
     * Always acts on the authenticated user, never on an id from the request.
     * The profile photo has its own endpoints (POST / DELETE user/avatar).
     */
    private function save(Request $request, bool $partial)
    {
        $user = $request->user();
        $required = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'locale' => [$required, 'string', Rule::in(collect(config('app-locales.available'))->pluck('code')->all())],
            // Notification preferences: always optional, so existing clients keep working
            'email_notification' => ['sometimes', 'boolean'],
            'push_notification' => ['sometimes', 'boolean'],
            // "All notifications": other people's schedules too
            'all_email_notification' => ['sometimes', 'boolean'],
            'all_push_notification' => ['sometimes', 'boolean'],
            // Changing the password proves knowledge of the current one (social-only accounts have none)
            'current_password' => [Rule::requiredIf(fn() => $request->filled('password') && $user->password !== null), 'nullable', 'string'],
            'password' => ['sometimes', 'nullable', 'min:8', 'confirmed'],
        ]);

        if (! empty($data['password'])) {
            if ($user->password !== null && ! Hash::check($data['current_password'] ?? '', $user->password)) {
                return response()->json([
                    'message' => __('The current password is incorrect.'),
                    'errors' => ['current_password' => [__('The current password is incorrect.')]],
                ], 422);
            }
        } else {
            unset($data['password']);
        }
        unset($data['current_password']);

        $user->update($data);

        // Other devices must sign in again after a password change
        if (! empty($data['password'])) {
            $currentTokenId = $user->currentAccessToken()->id ?? null;
            $user->tokens()
                ->when($currentTokenId, fn($query) => $query->where('id', '!=', $currentTokenId))
                ->delete();
        }

        return response()->json(['message' => __('User updated successfully.'), 'user' => $this->present($user->fresh())]);
    }
}
