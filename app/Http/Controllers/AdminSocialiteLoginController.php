<?php

namespace App\Http\Controllers;

use DutchCodingCompany\FilamentSocialite\Http\Controllers\SocialiteLoginController as BaseController;
use Laravel\Socialite\Two\InvalidStateException;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\RedirectResponse;

class AdminSocialiteLoginController extends BaseController
{
    public function processCallback(string $provider): RedirectResponse
    {
        try {
            return parent::processCallback($provider);
        } catch (InvalidStateException | ClientException $e) {
            return redirect()->route('filament.auth.login')
                ->with('error', 'OAuth login was canceled or failed.');
        }
    }
}
