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

### Usage

- Access role/permission management in the Filament admin panel
- Assign roles to users via the Users resource
- Define resource policies in `app/Policies/` to control access
- Use `@can('permission-name')` in blade templates to conditionally render content

For detailed documentation, visit: https://github.com/bezhansalleh/filament-shield

## Useful commands

- `composer install` — install PHP dependencies
- `npm install` — install JavaScript dependencies
- `npm run dev` — start Vite development server
- `npm run build` — build production assets
- `php artisan migrate` — run database migrations
- `php artisan test` — run PHP tests
- `php artisan config:clear` — clear cached configuration

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