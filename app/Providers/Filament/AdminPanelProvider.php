<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Filament\Support\Colors;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\User;
use Tapp\FilamentAuthenticationLog\FilamentAuthenticationLogPlugin;
use Hexters\HexaLite\HexaLite;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->registration()
            ->passwordReset()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ])->plugins([
                FilamentSocialitePlugin::make()
                    // (required) Add providers corresponding with providers in `config/services.php`.
                    ->providers([
                        Provider::make('facebook')
                            ->label('Facebook')
                            //->icon('fab-gitlab')
                            ->color(Color::hex('#1e12a0ff'))
                            ->outlined(false)
                            ->stateless(false),
                        Provider::make('google')
                            ->label('Google')
                            //->icon('fab-gitlab')
                            ->color(Color::hex('#208a0fff'))
                            ->outlined(false)
                            ->stateless(false)

                    ])
                    // (optional) Override the panel slug to be used in the oauth routes. Defaults to the panel's configured path.
                    ->slug('admin')
                    // (optional) Enable/disable registration of new (socialite-) users.
                    ->registration(true)
                    // (optional) Enable/disable registration of new (socialite-) users using a callback.
                    ->registration(function (string $provider, SocialiteUserContract $oauthUser, ?Authenticatable $user) {
                        return User::findOrCreateFromSocialite($oauthUser, $provider);
                    })
                    // (optional) Change the associated model class.
                    ->userModelClass(\App\Models\User::class)
                    // (optional) Change the associated socialite class (see below).
                    ->socialiteUserModelClass(\App\Models\SocialiteUser::class),
                FilamentAuthenticationLogPlugin::make(),
                HexaLite::make(),

            ]);
    }
}
