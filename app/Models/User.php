<?php

namespace App\Models;

use App\Models\SocialiteUser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Filament\Panel\Concerns\HasAvatars;
use Rappasoft\LaravelAuthenticationLog\Traits\AuthenticationLoggable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Contracts\Auditable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
// IMPORT THE SOCIALITE USER CONTRACT TO FIX THE TYPE-HINTING
use Laravel\Socialite\Contracts\User as ProviderUser;

class User extends Authenticatable implements FilamentUser, HasAvatar, Auditable, HasLocalePreference
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, AuthenticationLoggable, HasRoles, SoftDeletes, HasAvatars, \OwenIt\Auditing\Auditable, HasApiTokens, HasPushSubscriptions;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'locale',
        'approved_at',
        'avatar_url',
        'building_key',
        'push_notification',
        'email_notification',
    ];

    /**
     * Every permission the user has, directly or through their roles, for apps to decide what
     * to show (the API checks each permission again). Not getPermissionNames(): that one only
     * lists permissions given directly to the user, and here they come from roles.
     *
     * @return list<string>
     */
    public function permissionNamesForApps(): array
    {
        return $this->getAllPermissions()->pluck('name')->unique()->sort()->values()->all();
    }

    /**
     * Never copied into the audit log: the audit only says the password changed, not its hash.
     *
     * @var list<string>
     */
    protected $auditExclude = [
        'password',
        'remember_token',
    ];

    /** New accounts get both notification kinds (same as the column defaults). */
    protected $attributes = [
        'push_notification' => true,
        'email_notification' => true,
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
            'approved_at' => 'datetime',
            'password' => 'hashed',
            'building_key' => 'boolean',
            'status' => 'boolean',
            'push_notification' => 'boolean',
            'email_notification' => 'boolean',
        ];
    }

    /** Notifications and mail are composed in the recipient's language. */
    public function preferredLocale(): string
    {
        return $this->locale ?: config('app.locale');
    }

    public function socialiteUsers()
    {
        return $this->hasMany(SocialiteUser::class, 'user_id', 'id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function scheduleUsers()
    {
        return $this->hasMany(ScheduleUser::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Allow active users and pending users 
        // Pending users will be redirected via middleware
        // add this method to acept only verified email addresses
        # $this->hasVerifiedEmail(); 
        return (bool) $this->status;
    }
    // https://github.com/xlite-dev/filament-impersonate
    /**
     * By default, only Filament admins can impersonate other users. 
     * You can control this by adding a 'canImpersonate' method to your FilamentUser class
     */
    public function canImpersonate(): bool
    {
        return $this->can('impersonate_user') ? true : false;
    }
    /**
     * You can also control which targets can be impersonated.
     * Just add a 'canBeImpersonated' method to the user class with whatever logic you need
     */
    public function canBeImpersonated(): bool
    {
        // Let's prevent impersonating other users that are super admins
        return !$this->hasRole('super_admin') && (bool) $this->status;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('super_admin') ? true : false;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        // Return the full URL for the image or null if empty
        return $this->avatar_url ? Storage::url($this->avatar_url) : null;
    }

    protected static function booted()
    {
        // A new account (e.g. panel self-registration) starts in the language the visitor was using,
        // not in the column default
        static::creating(function (User $user) {
            if (empty($user->locale)) {
                $user->locale = app()->getLocale();
            }
        });

        // A deactivated or removed account must lose API access immediately, not at token expiry
        static::updated(function (User $user) {
            if ($user->wasChanged('status') && ! $user->status) {
                $user->tokens()->delete();
            }
        });
        static::deleted(fn(User $user) => $user->tokens()->delete());

        static::deleting(function ($user) {
            if ($user->schedules()->exists()) {
                return false; // Prevent deletion if the user has schedules
            }
            $user->socialiteUsers()->forceDelete();
        });
    }

    /**
     * Whether the provider vouches that the email belongs to the person logging in.
     * Google says so explicitly; Facebook only releases confirmed addresses.
     */
    public static function providerVerifiesEmail(ProviderUser $socialiteUser, string $provider): bool
    {
        if ($provider === 'facebook') {
            return true;
        }

        $raw = $socialiteUser instanceof \Laravel\Socialite\AbstractUser ? $socialiteUser->getRaw() : [];

        return filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Copy the provider's avatar to the public disk (used by the panel and the API social logins).
     * Best effort: a slow or broken avatar URL must not hang or fail the login.
     */
    public static function storeSocialAvatar(?string $avatarUrl): ?string
    {
        if (empty($avatarUrl)) {
            return null;
        }

        try {
            $avatarContents = \Illuminate\Support\Facades\Http::timeout(5)->get($avatarUrl)->throw()->body();
            $filename = 'avatars/' . Str::random(40) . '.jpg';
            Storage::disk('public')->put($filename, $avatarContents);

            return $filename;
        } catch (\Exception $e) {
            Log::error("Failed to download social avatar: " . $e->getMessage());

            return null;
        }
    }

    /**
     * Find or create a user from a Socialite login.
     *
     * @param ProviderUser $socialiteUser
     * @param string $provider
     * @return self
     */
    public static function findOrCreateFromSocialite(ProviderUser $socialiteUser, string $provider): self
    {
        // 1. Check if this exact social account provider link already exists
        $socialRelation = SocialiteUser::where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($socialRelation) {
            return self::findOrFail($socialRelation->user_id);
        }

        // 2. If no direct social link exists, check if the email address exists in our system
        $email = $socialiteUser->getEmail();
        if (empty($email)) {
            throw new \DomainException('The provider did not return an email address.');
        }

        $user = self::where('email', $email)->first();

        // Never attach a social identity to an existing account on the strength of an unverified email
        if ($user && ! self::providerVerifiesEmail($socialiteUser, $provider)) {
            throw new \DomainException("Email from {$provider} is not verified; refusing to link to an existing account.");
        }

        if (!$user) {
            // Handle the Avatar Download (replicating your Filament logic)
            $avatarPath = self::storeSocialAvatar($socialiteUser->getAvatar());

            // 3. Create the new user record
            $user = self::create([
                'name' => $socialiteUser->getName() ?? $socialiteUser->getNickname() ?? 'User',
                'email' => $socialiteUser->getEmail(),
                'avatar_url' => $avatarPath, // FIXED: Corrected column name to match $fillable
                'locale' => app()->getLocale(),
                'status' => 1, // Ensure default status allows API access depending on your application rules
                'password' => null,
            ]);
        }

        // 4. Link the social provider account to the user (handles both brand-new users 
        // and existing traditional users logging in via social for the first time)
        SocialiteUser::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $socialiteUser->getId(),
        ]);

        return $user;
    }
}
