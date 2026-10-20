<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A readable name for the device that signs in ("Chrome on Android"), stored as the API token's
 * name, so people can recognise their sessions in the PWA's "Signed-in devices" list.
 * A rough guess from the User-Agent: it only labels sessions, it never decides anything.
 */
class DeviceName
{
    private const BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'SamsungBrowser' => 'Samsung Internet',
        'Firefox/' => 'Firefox',
        'FxiOS' => 'Firefox',
        'CriOS' => 'Chrome',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    private const SYSTEMS = [
        'Android' => 'Android',
        'iPhone' => 'iPhone',
        'iPad' => 'iPad',
        'Windows' => 'Windows',
        'Mac OS X' => 'macOS',
        'CrOS' => 'ChromeOS',
        'Linux' => 'Linux',
    ];

    public static function fromRequest(Request $request): string
    {
        return static::fromUserAgent((string) $request->userAgent());
    }

    public static function fromUserAgent(string $userAgent): string
    {
        $browser = static::first(self::BROWSERS, $userAgent);
        $system = static::first(self::SYSTEMS, $userAgent);

        $name = match (true) {
            $browser && $system => "{$browser} · {$system}",
            (bool) $browser => $browser,
            (bool) $system => $system,
            default => __('Unknown device'),
        };

        return Str::limit($name, 100, '');
    }

    private static function first(array $needles, string $haystack): ?string
    {
        foreach ($needles as $needle => $label) {
            if (str_contains($haystack, $needle)) {
                return $label;
            }
        }

        return null;
    }
}
