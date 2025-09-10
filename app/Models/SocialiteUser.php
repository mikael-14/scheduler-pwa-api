<?php

namespace App\Models;

use DutchCodingCompany\FilamentSocialite\Models\Contracts\FilamentSocialiteUser as FilamentSocialiteUserContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;

class SocialiteUser extends Model implements FilamentSocialiteUserContract
{
    protected $fillable = [
        'provider',
        'provider_id',
        'user_id',
    ];

    public function getUser(): Authenticatable
    {
        return $this->user; // relation below
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function findForProvider(string $provider, SocialiteUserContract $oauthUser): ?self
    {
        return self::where('provider', $provider)
            ->where('provider_id', $oauthUser->getId())
            ->first();
    }

    public static function createForProvider(
        string $provider,
        SocialiteUserContract $oauthUser,
        Authenticatable $user
    ): self {
        return self::create([
            'provider' => $provider,
            'provider_id' => $oauthUser->getId(),
            'user_id' => $user->getAuthIdentifier(),
        ]);
    }
}
