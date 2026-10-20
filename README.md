<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

# Scheduler API

A Laravel-based scheduler API project with a Filament admin interface. Built with Laravel 12, Filament 5, Tailwind CSS, Vite, and Docker support.

## Key technologies

- PHP 8.2+
- Laravel 12
- Filament 5
- Tailwind CSS
- Vite
- MySQL 8.0
- Docker / Docker Compose

## Main packages and plugins

- Laravel: https://laravel.com/
- Filament: https://filamentphp.com/
- Filament Shield: https://github.com/bezhansalleh/filament-shield
- Filament Language Switcher: https://github.com/craft-forge/filament-language-switcher
- Filament Socialite: https://github.com/dutchcodingcompany/filament-socialite
- Filament FullCalendar: https://github.com/saade/filament-fullcalendar
- Filament Impersonate: https://github.com/xlite-dev/filament-impersonate
- Laravel Sanctum: https://laravel.com/docs/sanctum
- Spatie Laravel Permission: https://spatie.be/docs/laravel-permission
- Laravel Auditing: https://github.com/owen-it/laravel-auditing
- MaxMind GeoIP2: https://github.com/maxmind/GeoIP2-php
- Torann GeoIP: https://github.com/Torann/laravel-geoip
- Zvizvi User Fields: https://github.com/zvizvi/user-fields

## Prerequisites

- PHP 8.2 or later
- Composer
- Node.js and npm
- Docker & Docker Compose (optional but recommended)

## Local setup (native PHP)

1. Clone the repository

```bash
git clone <repository-url> scheduler-api
cd scheduler-api
```

2. Install PHP dependencies

```bash
composer install
```

3. Install frontend dependencies

```bash
npm install
```

4. Copy environment file and generate application key

```bash
cp .env.example .env
php artisan key:generate
```

5. Configure database settings in `.env`

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

6. Run database migrations

```bash
php artisan migrate
```

## Docker setup

This project includes `docker-compose.yaml` for a local development stack.

1. Create or update `.env` with Docker database credentials

```env
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret
DB_PASSWORD_ROOT=root_secret
```

2. Start Docker services

```bash
docker compose up -d
```

3. SSH to the container 
```bash
docker exec -it -u 0 api-app bash
```

4. Install dependencies

```bash
composer install
npm install
```

5. Run migrations

```bash
php artisan migrate
```

6. Access the services

- App: http://localhost:9000
- phpMyAdmin: http://localhost:9081

## Filament Shield - Roles & Permissions

Filament Shield integrates role-based access control (RBAC) with Filament using Spatie Laravel Permission.

### Setup

1. Generate permissions and roles for your models:

```bash
php artisan shield:install
```

This command will:
- Create a `permissions` table and `roles` table
- Auto-generate permissions for all Filament resources
- Generate default roles (e.g., Super Admin)

2. Create a super admin user (if needed):

```bash
php artisan shield:create-super-admin
```

3. Generate new polices in case of new resource created 
```bash 
php artisan shield:generate --all
```
### Usage

- Access role/permission management in the Filament admin panel
- Assign roles to users via the Users resources
- Define resource policies in `app/Policies/` to control access
- Use `@can('permission-name')` in blade templates to conditionally render content
- API Docs https://localhost:9443/docs/api#/ 

For detailed documentation, visit: https://github.com/bezhansalleh/filament-shield

## API sessions (PWA tokens)

The PWA signs in with a Laravel Sanctum token (email/password, Google or Facebook). The API
stores a hashed copy in `personal_access_tokens`; the PWA keeps the plain token in the
browser's `localStorage` and sends it on every request as `Authorization: Bearer <token>`.
Each device has its own token, so signing out on one does not affect the others.

### How long a session lasts

- Every token expires **30 days after it was issued** (`SANCTUM_EXPIRATION` in minutes,
  default `60 * 24 * 30`, see `config/sanctum.php`).
- While the app is used, the PWA renews its token about **once a day** with
  `POST /api/auth/refresh` (at start-up and when the user comes back to the app). So a session
  ends after **30 days without opening the app**, not 30 days after signing in.
- On a refresh the old token keeps working for **5 minutes** (requests already on their way,
  other open tabs), then stops. A refresh never extends a token, and an expired token cannot be
  renewed.
- Pending accounts can renew (their session lasts while they wait for approval); inactive
  accounts cannot.

### When a session ends

| Event | Result |
|---|---|
| 30 days without using the app | API answers 401, the PWA shows the login page ("session ended") |
| User signs out | that device's token is deleted |
| Admin deactivates or deletes the user | all their tokens are deleted; each device goes to login on its next request |

Network errors, timeouts and server errors never end a session: the PWA shows a message and
keeps the user signed in.

## Background processes

Two processes must run next to the web server.

### Queue worker

Emails and web push notifications are queued (the in-app notification is written at once), so
they are only sent while a worker runs:

```bash
php artisan queue:work
```

Keep it running in production (Supervisor on Linux, NSSM or Task Scheduler on Windows).
`composer dev` already starts one in development. Failed jobs: `php artisan queue:failed`.

### Scheduler

Scheduled tasks (`routes/console.php`) only run when the scheduler is called every minute:

- Linux / Docker (cron):

```cron
* * * * * cd /path/to/scheduler-api && php artisan schedule:run >> /dev/null 2>&1
```

- Windows: a Task Scheduler task running `php artisan schedule:run` every minute, starting in
  the project folder.
- Development: `php artisan schedule:work` (keeps running in the terminal).

Current tasks:

| Task | When | Why |
|---|---|---|
| `sanctum:prune-expired --hours=24` | daily at 00:00 | deletes tokens expired for more than a day (old 30-day tokens and the ones replaced by renewals), so `personal_access_tokens` does not grow forever |
| `coverage:check` | daily at `COVERAGE_ALERTS_AT` (09:00) | warns everyone about days with nobody approved for a schedule type, 7 days and 1 day before (see "Coverage alerts") |

Check them with `php artisan schedule:list`. Without the scheduler everything still works;
expired tokens are just not cleaned up.

## Coverage alerts

When a day has nobody **approved** for a schedule type, everyone (active, approved accounts) is
warned a week before and the day before, in the app, by email and by push (each person's settings).
The alert opens the PWA's new schedule form on that type and day. Each alert is sent once
(`coverage_alerts` table).

Rules live in `config/coverage.php`, one per schedule type (by name). Every setting has a default
and can be changed per rule:

```php
'rules' => [
    'walks' => ['type' => 'Passeios'],                       // defaults: every day, 1 approved, 7 and 1 days before, everyone
    'cleaning' => ['type' => 'Limpeza', 'weekdays' => [6], 'min_approved' => 2,
                   'notify_days_before' => [7, 3, 1], 'audience' => ['roles' => ['volunteer']]],
],
```

Days outside the type's start / end, or on weekdays not listed, are not checked.

- `.env`: `COVERAGE_ALERTS=false` switches everything off; `COVERAGE_ALERTS_AT=09:00` sets the time.
- Try rules without sending anything: `php artisan coverage:check --dry-run`
- As if it were another day: `php artisan coverage:check --date=2026-11-01 --dry-run`
- One rule, other days ahead: `php artisan coverage:check --rule=walks --days=3,1`

## Useful commands

- `composer install` — install PHP dependencies
- `npm install` — install JavaScript dependencies
- `npm run dev` — start Vite development server
- `npm run build` — build production assets
- `php artisan migrate` — run database migrations
- `php artisan test` — run PHP tests
- `php artisan config:clear` — clear cached configuration
- `php artisan queue:work` — send queued emails and push notifications
- `php artisan schedule:work` — run the scheduled tasks in development
- `php artisan schedule:list` — list the scheduled tasks
- `php artisan sanctum:prune-expired --hours=24` — delete expired API tokens by hand

## Testing

Run the Laravel test suite:

```bash
php artisan test
```

## Notes

- Admin resources and pages are organized under `app/Filament`.
- Role and permission management is implemented with Spatie Laravel Permission.
- API authentication uses Laravel Sanctum.
- Scheduling views use Filament FullCalendar.
- Xdebug is mounted in Docker at `docker/php/xdebug.ini` and can be enabled via `XDEBUG_MODE`.

## License

This project is licensed under the MIT License.