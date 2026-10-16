<?php

namespace Tests\Unit;

use App\Http\Middleware\ApplyUserLocale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class ApplyUserLocaleTest extends TestCase
{
    private function pass(?User $user, ?string $sessionLocale, string $current): array
    {
        App::setLocale($current);

        $request = Request::create('/admin');
        $session = new Store('test', new ArraySessionHandler(120));
        if ($sessionLocale) {
            $session->put('locale', $sessionLocale);
        }
        $request->setLaravelSession($session);
        $request->setUserResolver(fn() => $user);

        // The language switcher's SetLocale would already have applied the session value
        (new ApplyUserLocale())->handle($request, fn() => response('ok'));

        return [App::getLocale(), $session->get('locale')];
    }

    public function test_signed_in_user_locale_wins_over_session_and_default(): void
    {
        $user = new User(['locale' => 'en']);
        $user->locale = 'en';

        [$app, $session] = $this->pass($user, 'pt', 'pt');

        $this->assertSame('en', $app);
        $this->assertSame('en', $session, 'the switcher state is kept in sync');
    }

    public function test_guest_keeps_whatever_the_switcher_chose(): void
    {
        [$app, $session] = $this->pass(null, 'en', 'en');

        $this->assertSame('en', $app);
        $this->assertSame('en', $session);
    }

    public function test_unknown_user_locale_is_ignored(): void
    {
        $user = new User();
        $user->locale = 'fr';

        [$app] = $this->pass($user, null, 'pt');

        $this->assertSame('pt', $app);
    }
}
