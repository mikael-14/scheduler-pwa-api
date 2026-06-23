<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Update the authenticated user's information.
     */
    public function update(Request $request, User $user) {
        
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'min:6', 'confirmed'],
            'locale' => ['required', 'string', 'max:2'],
            'avatar_url' => ['nullable', 'string', 'max:255'],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        if (isset($data['avatar_url'])) {
            $data['avatar_url'] = $data['avatar_url'] ?: null;
        }

        $user->update($data);

        return response()->json(['message' => 'User updated successfully.', 'user' => $user]);
    }
    public function patch(Request $request, User $user) {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $user->id],
            'password' => ['sometimes', 'nullable', 'min:6', 'confirmed'],
            'locale' => ['sometimes', 'string', 'max:2'],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        if (isset($data['avatar_url'])) {
            $data['avatar_url'] = $data['avatar_url'] ?: null;
        }

        $user->update($data);

        return response()->json(['message' => 'User updated successfully.', 'user' => $user]);
    }
}