<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use DutchCodingCompany\FilamentSocialite\Models\Contracts\FilamentSocialiteUser as FilamentSocialiteUserContract;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Rappasoft\LaravelAuthenticationLog\Traits\AuthenticationLoggable;
use Hexters\HexaLite\HexaLiteRolePermission;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, AuthenticationLoggable, HexaLiteRolePermission;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

     /**
     * Relationship with SocialiteUser
     */
    public function socialiteUsers()
    {
        return $this->hasMany(SocialiteUser::class);
    }



    /**
     * Optional: find existing user by email or provider
     */
    public static function findOrCreateFromSocialite(SocialiteUserContract $oauthUser, string $provider): self
    {
        $socialiteUser = SocialiteUser::findForProvider($provider, $oauthUser);

        if ($socialiteUser) {
            return $socialiteUser->getUser();
        }

        $email = $oauthUser->getEmail();
        $name = $oauthUser->getName() ?? $oauthUser->getNickname();

        $user = self::whereRaw('LOWER(email) = LOWER(?)', [$email])->first();

        if (!$user) {
            $user = self::create([
                'email' => $email,
                'name' => $name,
                'password' => null,
            ]);
        }

        if (!$user->socialiteUsers()->where('provider', $provider)->exists()) {
            $user->socialiteUsers()->create([
                'provider' => $provider,
                'provider_id' => $oauthUser->getId(),
            ]);
        }

        return $user;
    }
}
