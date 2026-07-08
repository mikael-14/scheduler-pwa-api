<?php

namespace App\Models;

use App\Models\SocialiteUser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

class User extends Authenticatable implements FilamentUser, HasAvatar, Auditable
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
        'building_key'
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
        ];
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
        return ($this->status === 1);
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
        return !$this->hasRole('super_admin') && $this->status === 1 ? true : false;
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
        static::deleting(function ($user) {
            if ($user->schedules()->exists()) {
                return false; // Prevent deletion if the user has schedules
            }
            $user->socialiteUsers()->forceDelete();
        });
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
        $user = self::where('email', $socialiteUser->getEmail())->first();

        if (!$user) {
            // Handle the Avatar Download (replicating your Filament logic)
            $avatarPath = null;
            if ($avatarUrl = $socialiteUser->getAvatar()) {
                try {
                    $avatarContents = file_get_contents($avatarUrl);
                    $filename = 'avatars/' . Str::random(40) . '.jpg';
                    Storage::disk('public')->put($filename, $avatarContents);
                    $avatarPath = $filename;
                } catch (\Exception $e) {
                    Log::error("Failed to download social avatar: " . $e->getMessage());
                }
            }

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
