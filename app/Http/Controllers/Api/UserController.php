<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function user(Request $request)
    {
        $user = $request->user();
        $avatar = $user->getFilamentAvatarUrl();
        if ($avatar) {
            $user->avatar_url = asset($avatar);
        } else {
            $user->avatar_url = null;
        }
        $permissionsArray = $user->getPermissionNames()->toArray();
        return response()->json([
            'user' => $user,
            'permissions' => $permissionsArray
        ]);
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
     * Avatar is managed in the admin panel / social login, not through this endpoint.
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

        return response()->json(['message' => __('User updated successfully.'), 'user' => $user->fresh()]);
    }
}
