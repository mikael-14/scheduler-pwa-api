<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use DutchCodingCompany\FilamentSocialite\Models\Contracts\FilamentSocialiteUser as FilamentSocialiteUserContract;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
     * Create a user from Socialite and attach SocialiteUser record
     */
    public static function createFromSocialite(SocialiteUserContract $oauthUser, string $provider): self
    {
        $user = self::create([
            'name' => $oauthUser->getName() ?? $oauthUser->getNickname(),
            'email' => $oauthUser->getEmail(),
            'password' => null,
        ]);

        $user->socialiteUsers()->create([
            'provider' => $provider,
            'provider_id' => $oauthUser->getId(),
        ]);

        return $user;
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

        $user = self::where('email', $oauthUser->getEmail())->first();
        if ($user) {
            SocialiteUser::createForProvider($provider, $oauthUser, $user);
            return $user;
        }

        return self::createFromSocialite($oauthUser, $provider);
    }
}
