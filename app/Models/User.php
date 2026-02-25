<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use DutchCodingCompany\FilamentSocialite\Models\Contracts\FilamentSocialiteUser as FilamentSocialiteUserContract;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Rappasoft\LaravelAuthenticationLog\Traits\AuthenticationLoggable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, AuthenticationLoggable, HasRoles, SoftDeletes;

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
        ];
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
        return $this->hasRole('super_admin') ? true : false;
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
}
