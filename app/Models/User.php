<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
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
     * @param SocialiteUser $socialiteUser
     * @param string $provider
     * @return self
     */
    public static function findOrCreateFromSocialite(SocialiteUser $socialiteUser, string $provider): self
    {
        // 1. Check if the user already exists via email
        $user = self::where('email', $socialiteUser->getEmail())->first();
        if ($user) {
            // Optional: You could update the avatar or provider ID here if you wanted to
            return $user;
        }

        // 2. Handle the Avatar Download (replicating your Filament logic)
        $avatarPath = null;
        if ($avatarUrl = $socialiteUser->getAvatar()) {
            try {
                // Fetch the image from Google/Facebook
                $avatarContents = file_get_contents($avatarUrl);

                // Generate a unique filename and save to the public disk
                $filename = 'avatars/' . Str::random(40) . '.jpg';
                Storage::disk('public')->put($filename, $avatarContents);

                $avatarPath = $filename;
            } catch (\Exception $e) {
                // If downloading fails, log it but don't stop the registration
                Log::error("Failed to download social avatar: " . $e->getMessage());
            }
        }

        // 3. Create the new user
        return self::create([
            'name' => $socialiteUser->getName() ?? $socialiteUser->getNickname() ?? 'User',
            'email' => $socialiteUser->getEmail(),
            'avatar' => $avatarPath, // Adjust this column name if yours is different (e.g., 'avatar_url')
            'locale' => app()->getLocale(),

            // Generate a random password since they logged in via OAuth, 
            // or leave as null if your database column is `nullable()`.
            'password' => null,
        ]);
    }
}
