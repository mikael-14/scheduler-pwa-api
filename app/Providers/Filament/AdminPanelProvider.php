<?php

namespace App\Providers\Filament;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Rupadana\ApiService\ApiServicePlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use Filament\View\PanelsRenderHook;
use CraftForge\FilamentLanguageSwitcher\FilamentLanguageSwitcherPlugin;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tapp\FilamentAuthenticationLog\FilamentAuthenticationLogPlugin;
use App\Http\Middleware\ApplyUserLocale;
use App\Http\Middleware\CheckPendingApproval;
use App\Filament\Pages\PendingApproval;
use Filament\Enums\DatabaseNotificationsPosition;
use Filament\Facades\Filament;
use Illuminate\Http\Exceptions\HttpResponseException;
use Tapp\FilamentAuditing\FilamentAuditingPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->registration()
            ->passwordReset()
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                PendingApproval::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
               // AccountWidget::class,
                //FilamentInfoWidget::class,
            ])
            ->profile(\App\Filament\Pages\Auth\EditProfile::class)
            // every open tab polls once per interval (one small request); raise it if the server gets busy
            ->databaseNotifications(position: DatabaseNotificationsPosition::Topbar)
            ->databaseNotificationsPolling(interval: '30s')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                CheckPendingApproval::class,
            ])
            ->authMiddleware([
                // Filament's Authenticate + signs out users deactivated mid-session
                \App\Http\Middleware\AuthenticatePanel::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
                FilamentAuditingPlugin::make(),
                ApiServicePlugin::make(),
                FilamentLanguageSwitcherPlugin::make()
                    ->locales(config('app-locales.available'))
                    ->renderHook(PanelsRenderHook::USER_MENU_BEFORE)
                    ->showOnAuthPages()
                    // Visitors keep their choice on the login page across sessions (one year)
                    ->rememberLocale(365)
                    ->showFlags(false),
                FilamentSocialitePlugin::make()
                    ->providers([
                        Provider::make('facebook')
                            ->label('Facebook')
                            ->color(Color::hex('#1e12a0ff'))
                            ->outlined(false)
                            ->stateless(false),
                        Provider::make('google')
                            ->label('Google')
                            ->color(Color::hex('#208a0fff'))
                            ->outlined(false)
                            ->stateless(false),
                    ])
                    ->slug('admin')
                    ->registration(true)
                    ->userModelClass(User::class)
                    ->resolveUserUsing(
                        function (
                            string $provider,
                            SocialiteUserContract $oauthUser
                        ): ?Authenticatable {
                            // Without an email there is nothing to match or create (Facebook may withhold it)
                            if (blank($oauthUser->getEmail())) {
                                $this->refuseSocialLogin(__('The provider did not share your email address.'));
                            }

                            $user = User::withTrashed()->where('email', $oauthUser->getEmail())->first();

                            // A removed account keeps its email, so creating a new one would fail on the unique index
                            if ($user?->trashed()) {
                                $this->refuseSocialLogin(__('This account has been removed. Contact an administrator.'));
                            }

                            // Never log into an existing account on the strength of an unverified email
                            if ($user && ! User::providerVerifiesEmail($oauthUser, $provider)) {
                                $this->refuseSocialLogin(__('The email address from this provider is not verified.'));
                            }

                            return $user;
                        }
                    )
                    ->createUserUsing(
                        function (
                            string $provider,
                            SocialiteUserContract $oauthUser
                        ): Authenticatable {
                            return User::create([
                                'name' => $oauthUser->getName()
                                    ?? $oauthUser->getNickname()
                                    ?? 'User',
                                'email' => $oauthUser->getEmail(),
                                'avatar_url' => User::storeSocialAvatar($oauthUser->getAvatar()),
                                'locale' => app()->getLocale(),
                                'password' => null,
                            ]);
                        }
                    ),
                FilamentAuthenticationLogPlugin::make(),
                FilamentFullCalendarPlugin::make(),
            ])
            // Must come after the plugins so it runs after the language switcher's SetLocale:
            // a signed-in user's `locale` column wins, on page loads and on Livewire updates.
            ->middleware([ApplyUserLocale::class], isPersistent: true);
    }
    /**
     * Back to the login page with the reason shown under the social buttons (the plugin's own
     * error slot), instead of a bare error page.
     */
    private function refuseSocialLogin(string $message): never
    {
        session()->flash('filament-socialite-login-error', $message);

        throw new HttpResponseException(redirect(Filament::getLoginUrl()));
    }
}
