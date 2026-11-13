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
- Filament Jobs Monitor: https://github.com/croustibat/filament-jobs-monitor (4.x: PHP 8.2)

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

## Shared hosting (cPanel)

**Without terminal access**: follow [DEPLOY.md](DEPLOY.md) (everything is prepared on your PC; the
server gets files, a database import and cron jobs). The steps below are for hosts **with** SSH or
cPanel "Terminal".

Needs **HTTPS** on both domains (service worker and push only work over HTTPS). Example: API +
panel on `api.example.pt`, PWA on `app.example.pt`.

**1. PHP** (cPanel → MultiPHP Manager / Select PHP Version): 8.2 or later, with `intl`, `zip`,
`mbstring`, `openssl`, `curl`, `pdo_mysql`, `fileinfo`, `dom`, `xmlreader`, `gd` (and `gmp` if
offered: faster web push).

**2. Database** (cPanel → MySQL Databases): create a database and a user with all privileges.

**3. API files.** Build locally, then upload everything except `node_modules`, `.env`, `tests`:

```bash
composer install --no-dev --optimize-autoloader   # vendor/ (if the host has no composer)
npm ci && npm run build                           # public/build (the host has no Node)
```

Upload to a folder **outside** `public_html` (e.g. `~/scheduler-api`) and point the subdomain's
document root at `~/scheduler-api/public` (cPanel → Domains). Include
`storage/app/GeoLite2-City.mmdb` (sign-in locations).

**4. `.env`** on the server, from `.env.example`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.pt
PWA_URL=https://app.example.pt
DB_HOST=localhost                 # plus DB_DATABASE / DB_USERNAME / DB_PASSWORD from step 2
MAIL_HOST=mail.example.pt         # a cPanel email account (port 465 + MAIL_ENCRYPTION=ssl)
MAIL_FROM_ADDRESS=no-reply@example.pt
QUEUE_WORK_VIA_SCHEDULER=true     # no Supervisor here: the cron also sends emails / pushes
SUPER_ADMIN_EMAIL=you@example.pt
GOOGLE_REDIRECT_URL=https://api.example.pt/api/auth/google/callback   # and Facebook; also in their consoles
```

New VAPID keys: `php artisan webpush:vapid` (writes them into `.env`).

**5. Install** (Terminal, in `~/scheduler-api`):

```bash
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan db:seed --force          # permissions + super_admin role
php artisan app:create-admin         # your account: active, approved, super admin
php artisan config:cache && php artisan route:cache && php artisan event:cache
php artisan filament:optimize
```

Then create the roles in the panel (Shield → Roles, see "Suggested roles") and mark the primary
schedule type. If `storage:link` is not allowed by the host, profile photos will not show: ask
for symlinks or copy `storage/app/public` into `public/storage`.

**6. Cron** (cPanel → Cron Jobs, every minute):

```
* * * * * cd ~/scheduler-api && php artisan schedule:run >> /dev/null 2>&1
```

Use the full PHP path if `php` is not found (e.g. `/usr/local/bin/php` or
`/opt/cpanel/ea-php83/root/usr/bin/php`). This one line runs the scheduled tasks **and** the
queue (emails and pushes leave within a minute).

**7. PWA**: in `pwa-scheduler`, set `VITE_API_URL=https://api.example.pt/api` in `.env`, run
`npm run build`, and upload the **contents** of `dist/` (including the hidden `.htaccess`) to the
PWA subdomain's document root.

**Updating later**: upload the changed files, then `php artisan migrate --force`,
`php artisan db:seed --class=PermissionsSeeder --force` (new permissions) and the three cache
commands again. After changing `.env`, run `php artisan config:cache` (the cached copy is what
counts). The queue picks up the new code by itself (it restarts every minute).

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

### Permissions and roles

Roles are created in the admin panel (Shield → Roles); the code only checks permissions, never a
role's name (except `super_admin`). `config/filament-shield.php` (`resources.manage`) lists exactly
the permissions each resource has, and only ones something checks. After pulling changes, run
`php artisan db:seed --class=PermissionsSeeder --force`: it creates new permissions, **deletes the
ones no longer declared** (moving renamed ones onto the roles that had them first) and gives
`super_admin` all of them.

Only **approved, active** accounts use the app: before approval an account cannot open the agenda
(API and panel), and switching "active" off blocks it on its next request. Every approved person
sees the pending / approved schedules, with names — on purpose, so people see where they are
needed and take part.

#### Schedules

| Permission | Allows |
|---|---|
| `view_any_schedule`, `view_schedule` | open the shared agenda / a schedule (the calendar, and the dashboard's Today list and counts) |
| `view_inactive_schedule` | also see other people's rejected, cancelled, completed and not-shown schedules (without it: only pending / approved, plus your own and the ones you take part in) |
| `create_schedule` | book for yourself |
| `create_any_schedule` | book for someone else (without it the owner field is locked to you, everywhere) |
| `update_schedule` | edit (and cancel, before it starts) your own schedules, add / remove their participants, mark one of them "não compareceu" once it started |
| `update_any_schedule` | the same on anyone's, and hand a schedule over to another owner |
| `delete_schedule` / `delete_any_schedule` | delete your own / anyone's (admin panel). Bulk delete needs `delete_any_schedule` and skips the selected ones you could not delete one by one (e.g. past ones without `update_past_schedule`) |
| `restore_schedule`, `restore_any_schedule`, `force_delete_schedule`, `force_delete_any_schedule` | bring back / delete forever deleted schedules (admin panel) |
| `update_past_schedule` | anything on schedules before today (without it, from today on only) |
| `participate_schedule` | add **yourself** to someone else's pending / approved schedule, from today on; never a whole-day one. You get the schedule's status (joining an approved one keeps it approved; on the primary type the "may go alone" rule still applies) |
| `cancel_participation_schedule` | **cancel your attendance** (pending or approved) on a schedule you take part in, **before it starts**. You stay on it as cancelled (see "Cancelled participants" below) and may join again before it starts. Always give it together with `participate_schedule` |
| `approve_schedule` | approve / reject pending schedules (their participants follow). Approvers may approve their own |
| `update_status_schedule` | everything `approve_schedule` does, plus cancel at any time (also after the start) and mark "não compareceu". Never "completed": the nightly `schedules:complete` sets it |
| `independent_schedule` | **may go alone** on the primary type (see below) |
| `all_day_schedule` | may book **whole-day** schedules, with no hours, on types that allow them (see below) |

People added by the owner or an admin need no permission of their own. A participant's status is
always the schedule's (approve / reject / cancel it and its participants follow), except
"não compareceu", which is per person.

The admin panel follows the same rules as the API. The owner rule is checked whenever a schedule
is saved, so no screen can skip it. Clicking a schedule on the calendar opens the edit popup
only for those who may edit it; anyone else who may see it gets its page. Schedules you may not see
don't open (`ScheduleAdminAccessTest`).

#### Schedule types, users and administration

| Permission | Allows |
|---|---|
| `view_any_schedule_type` / `view_schedule_type` | the types list / a type's page (its calendar; its History tab also needs `view_any_audit`) |
| `create_schedule_type`, `update_schedule_type` | create / edit types (the "primary" type is set in the database only, never in the form or the API) |
| `delete_schedule_type` | delete types, one or in bulk: never a type that still has schedules (the others selected are deleted) |
| `view_any_user` / `view_owned_user` | the users list / the list with only your own row (anyone else's page: 404) |
| `update_any_user` / `update_owned_user` | edit others (see **never above yourself** below) / only yourself: name, email, avatar, language, notifications. The password only by its owner |
| `create_user`, `delete_user`, `restore_user`, `restore_any_user` | create, delete (never yourself, nor someone with schedules; in bulk each person is checked as if alone), bring back users |
| `approve_user` | approve new accounts: the Approve action (gives a role, never `super_admin` unless you are one, and tells the person), the dashboard counter and the users list filter |
| `assign_role_user` | give roles to **others**: never your own, `super_admin` only by a super admin, and only roles whose permissions you have too. Seeing the roles (`view_any_role`) is not enough |
| `impersonate_user` | sign in as someone else who is active (never yourself, never above yourself) |
| `view_any_role`, `view_role`, `create_role`, `update_role`, `delete_role` | manage roles (Shield's screen, the app's copy in `App\Filament\Resources\Roles`): never your own roles nor the `super_admin` one, only roles whose permissions you have, and a role only gets permissions you have yourself (the others are dropped, with a warning). A super admin may do all of it, but nobody deletes the `super_admin` role |
| `view_any_audit`, `view_audit` | the change history (audits) and the login history |
| `restore_audit` | put a schedule / type back as an audit recorded it |
| `view_any_notification_log`, `view_notification_log` | the notifications sent |
| `send_notification_log` | send announcements |
| `view_any_token`, `view_token`, `create_token`, `update_token`, `delete_token` | the API plugin's tokens page (the PWA's own devices don't need it): your own tokens only, a super admin's all of them (`App\Policies\TokenPolicy`, registered over the plugin's own). A token signs in as its owner with all of their permissions: the abilities ticked there are not checked by the API |

**Never above yourself:** the same idea runs through the Roles screen (`RoleManagement`) and the users:
editing someone else, switching them off, deleting, restoring,
impersonating them or giving them roles is only for someone who has every permission they have.
A super admin's account is only touched by a super admin. Without this an admin could change a
super admin's email, and social login finds accounts by email (`UserManagement`,
`UserAdminAccessTest`).

Every delete in bulk needs the same permission as deleting one (`delete_schedule_type`, `delete_user`,
`delete_role`, `delete_token`; schedules: `delete_any_schedule`, each row checked as if alone). Every screen's policy answers every
action Filament asks about, because Filament allows an action whose policy method is missing
(`AccessHolesTest` checks this for every admin resource).

On the user edit page, the account switches are kept apart from the rest:

| Switch | Who can change it |
|---|---|
| Active, Building Key | `update_any_user`, and never on your own record (not even an admin) |
| Approved | the same, plus `approve_user`. On the create page only with `approve_user`, otherwise the account is created waiting for approval |
| Roles | `assign_role_user` (see above) |

For everyone else they are greyed out and ignored when saving, whatever the browser sends.
Saving an approved user keeps the date it was first approved.

Volunteers and Externos don't need any user permission: the panel's **Profile** page (avatar
menu) changes their language and notifications, and the PWA's profile also their password.

A schedule's internal note is only seen by staff (`approve_schedule`, `update_status_schedule`
or `update_any_schedule`), in the panel, the API and the PWA.

#### Notifications

Nobody is told about what they did themselves, and only **active, approved** accounts are told
anything (in the app; by email / push only when they have those on, except account messages and
announcements with "email everyone").

| Who is told | What | Decided by |
|---|---|---|
| the owner and participants | approved, rejected, cancelled, changed, booked for you, added / removed, marked "não compareceu" | being on the schedule |
| approvers | a schedule waiting for approval, someone joined / left, someone marked "não compareceu" | `approve_schedule` or `update_status_schedule` |
| who turned on "all notifications" | what happens on the agenda | `view_any_schedule` (approvers are not told twice) |
| coverage alerts ("nobody yet for …") | a day nobody is approved for | `create_schedule` (they are asked to book), plus the rule's roles in `config/coverage.php` |
| announcements | a message from an admin to everyone, some roles or some people | sent by `send_notification_log` |

Each person only reads and marks as read their own notifications (API and panel bell). The log of
everything sent (`/admin/notifications`) needs `view_any_notification_log`.

#### Audits (change history)

| What | Needs |
|---|---|
| the Audits page and an audit's details | `view_any_audit` / `view_audit` |
| the "History" tab of a schedule or a type (read only) | `view_any_audit` |
| the login history (`/admin/authentication-logs`, a user's "logins" tab) | `view_any_audit` |
| put a schedule / type / user back as an audit recorded it ("Restore", on changes only) | `restore_audit` |

Recorded, with who did it and when: schedules, participants, schedule types, users (never the
password or its token), roles (created, renamed) and — through `App\Listeners\RecordRoleChanges`
(needs `permission.events_enabled`) — **a user's roles** ("roles: Voluntário Novo → Voluntário
Experiente", one entry per change, also when given on approval) and **a role's permissions**
("permissions: before → after", the Roles screen included; "before" is the list last recorded).
Changes made from the console (seeders, `schedules:complete`) are not audited, except role and
permission changes.

#### In the API

Every route is closed by default (`tests/Feature/ApiRouteGuardsTest.php` fails on a new route that
forgets it):

| Routes | Needs |
|---|---|
| sign in, register, forgot / reset password, Google / Facebook | nobody (rate limited) |
| `api/user` (profile, photo, devices), refresh, sign out | signed in and active, also while waiting for approval |
| notifications, push subscription | signed in, active and approved |
| `api/admin/schedules…`, `api/admin/schedule-types…` | signed in, active, approved **and** the permission below |

| Route | Permission | Then also checked on the schedule |
|---|---|---|
| `GET schedules`, `calendar-options` | `view_any_schedule` | only what they may see (see `view_inactive_schedule`) |
| `GET schedules/{id}` | `view_schedule` | the same |
| `POST schedules` | `create_schedule` | for someone else only with `create_any_schedule`; whole day, past dates, primary rule |
| `GET form-options` | `create_schedule` or `update_schedule` | |
| `PUT/PATCH schedules/{id}` | `update_schedule` or `update_status_schedule` | content: own (or `update_any_schedule`), not past; status: as `mayChangeStatus` |
| `POST …/approve`, `…/reject`, `GET pending` | `approve_schedule` or `update_status_schedule` | pending only, unless `update_status_schedule`; primary / whole-day rules |
| `POST …/not-shown` | `update_status_schedule` | approved or completed, once started |
| `POST …/participants/{userId}/not-shown` | `update_schedule` or `update_status_schedule` | the owner (or `update_status_schedule`), once started |
| `POST / DELETE …/participation` | `participate_schedule` / `cancel_participation_schedule` | as `canJoin` / `canLeave` |
| schedule types | `view_any_` / `view_` / `create_` / `update_` / `delete_schedule_type` | a type with schedules is not deleted |

Deleting schedules is not in the API (admin panel only). The API docs (`/docs/api`) open only
locally (`APP_ENV=local`).

#### In the PWA

The PWA gets the signed-in person's permissions from `GET /api/user` and uses them only to hide
what would not work; the API checks everything again.

| Page | Needs (otherwise: back to Home) |
|---|---|
| Calendar (menu and page) | `view_any_schedule` |
| Schedule details | `view_schedule` |
| New schedule | `create_schedule` (an Externo never lands on the booking form) |
| Edit schedule | `update_schedule`, and the schedule's own `can.update` (else: its details) |
| Approvals (menu, badge, page) | `approve_schedule` or `update_status_schedule` |
| Schedule types / new / edit | `view_any_schedule_type` / + `create_schedule_type` / `view_schedule_type` + `update_schedule_type` |
| Home, notifications, profile, settings | any approved account |

- Accounts not approved yet only see the "awaiting approval" page; a deactivated one is refused
  by the API on its next request.
- What can be done **with each schedule** comes from the API, by the same rules it enforces:
  `can.update`, `can.cancel`, `can.approve`, `can.not_shown`, `can.join`, `can.leave`, and
  `can_mark_not_shown` on each participant. So an Externo only gets "join" / "leave", and nobody
  gets "join" on a whole-day schedule.
- **Cancelled participants.** Leaving ("Cancelar presença"), or being taken off by the owner or an
  admin (participants list of the PWA or admin form), never deletes the person: the row is
  soft-deleted into `schedule_users.cancelled_at` with status `cancelled`.
  - They are never counted: not in `schedule_users` of the API, the calendar "+N", the
    widgets, the primary rule, the notifications or `schedules:complete`. An approved
    schedule that relied on them goes back to pending, as before.
  - The API lists them apart, in `cancelled_users`. The PWA shows them greyed out on the
    schedule; for the person who cancelled it stays in their list with a "Cancelado" badge.
  - Joining again ("Voltar a participar", before it starts) restores the same row with the
    schedule's status at that moment; the primary rule applies.
  - Approving / rejecting the schedule never brings them back.
  - Admin panel: the schedule's View / Edit pages have a "Participantes cancelados" tab;
    whoever edits other people's schedules (`update_any_schedule`) may **restore** one there
    (like joining again: the schedule's status now, the primary rule checked, the person is
    told) or **delete one for good** (audited, nobody is told again).
- The booking form's options come from `GET /api/admin/schedules/form-options` (`can.book_for_others`: choose the owner of a new schedule, `can.hand_over`: change it when editing,
  `can.past_dates`, `can.all_day`, `can.internal_note`).
- The internal note is removed by the API for non-staff: it never reaches their phone.
- The profile only changes name, email, language, notifications, password and photo: never
  "Active", "Approved" or "Building Key".

#### Roles

The suggested roles, created in Shield → Roles. Tick the permissions in each column; "optional"
depends on how much you trust that person.

| | Super Admin | Admin | Approver | Voluntário Experiente | Voluntário Novo | Externo |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| `view_any_schedule`, `view_schedule` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `participate_schedule`, `cancel_participation_schedule` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `create_schedule`, `update_schedule`, `delete_schedule` | ✓ | ✓ | ✓ | ✓ | ✓ | |
| `independent_schedule` | ✓ | ✓ | ✓ | ✓ | | |
| `all_day_schedule` | ✓ | ✓ | ✓ | ✓ | | |
| `approve_schedule` | ✓ | ✓ | ✓ | | | |
| `update_status_schedule` | ✓ | ✓ | optional | | | |
| `view_inactive_schedule` | ✓ | ✓ | ✓ | | | |
| `create_any_schedule`, `update_any_schedule`, `delete_any_schedule`, `update_past_schedule` | ✓ | ✓ | optional | | | |
| `restore_schedule`, `restore_any_schedule`, `force_delete_schedule`, `force_delete_any_schedule` | ✓ | optional | | | | |
| schedule types (all five) | ✓ | ✓ | | | | |
| `view_any_user`, `create_user`, `update_any_user`, `approve_user`, `assign_role_user` | ✓ | ✓ | | | | |
| `delete_user`, `restore_user`, `restore_any_user` | ✓ | ✓ | | | | |
| `impersonate_user` | ✓ | optional | | | | |
| `view_owned_user`, `update_owned_user` (not needed: the Profile page covers it) | ✓ | ✓ | optional | | | |
| roles (`view_any_role`, `view_role`, `create_role`, `update_role`, `delete_role`) | ✓ | ✓ | | | | |
| `view_any_audit`, `view_audit`, `restore_audit` | ✓ | ✓ | | | | |
| `view_any_notification_log`, `view_notification_log`, `send_notification_log` | ✓ | ✓ | | | | |
| tokens (all five) | ✓ | | | | | |

- **Super Admin**: everything; the seeder keeps it complete. The only one who may edit the Admin
  role, the super admin role, or another super admin's account.
- **Admin**: runs the association: people, roles, types, approvals and corrections.
- **Approver**: decides pending schedules; goes alone and books whole days like an Experiente.
- **Voluntário Experiente**: books, joins, may go alone and book whole days.
- **Voluntário Novo**: books and joins; on the primary type only approved with someone who may
  go alone there; no whole-day schedules.
- **Externo**: only **joins** other people's schedules and leaves them; never books. On the
  primary type treated like a Novo (cannot go alone).

What an **Admin** still cannot do, whatever is ticked (**never above yourself**, see above):

- edit, switch off, delete, restore, impersonate or give roles to a super admin, or to anyone with
  a permission the Admin lacks (another Admin with the same role is fine);
- change their own roles, delete or impersonate themselves;
- edit or delete the **Admin** role (their own) or the `super_admin` role, or any role with a
  permission they lack; a role they edit only gets permissions they have;
- give a role with a permission they lack (it is not offered);
- set their own Active / Approved / Building Key.

So when the Admin role needs a new permission, a super admin adds it.

Other notes:

- **Tokens** are for a super admin only: a token signs in as its owner with all of their
  permissions (the abilities ticked on the Tokens page are not checked), and the PWA makes its own.
- **Restore / delete forever**: deleted schedules are kept (soft deleted) and can be brought back
  with `restore_schedule`; `force_delete_*` removes them for good. Users are never deleted for
  good from the panel, and somebody with schedules is not deleted at all.
- **Audits**: give `view_any_audit` and `view_audit` together; `restore_audit` puts a schedule,
  type or user back as an audit recorded it, still under the normal rules.
- A role's name is free (only `super_admin` is special): the app checks permissions, never names.

`tests/Feature/RoleMatrixTest.php` creates these roles (without the optional boxes) and opens every admin page as each of them; it fails if the panel and this table disagree.

**Setting it up:** run `PermissionsSeeder`, sign in as the super admin, create the five roles
with the permissions above (the super admin creates Admin, since nobody else may give it more
than they have), then give people their roles when approving them.

### The primary type rule

On the schedule type marked **primary** (going to the shelter), someone without
`independent_schedule` can book, but is only **approved** when someone with it is already
approved and **there when they arrive** (as owner or approved participant of a primary schedule):
starting at the same time or earlier, until their end time, or for one hour when they gave no end.

| Experiente | Novo | Approved? |
|---|---|---|
| 9h (no end) | 10h | yes (within the hour) |
| 9h (no end) | 10h30 | no |
| 9h–13h | 11h | yes |
| 10h | 9h | no (the Novo would be alone from 9h to 10h) |

People on the same schedule count together, so a Novo booking with an Experiente participant is
approved in one go. Other types are not affected.

### Whole-day schedules

"I can go Sunday, but I can't give hours" is a **whole-day** schedule:

- only on types with "all day" allowed, and only for people with `all_day_schedule` (the owner
  too: a Novo never gets one, not even booked for them);
- once one is approved, **nothing else of that type** can be approved on that day, and it cannot
  be approved while something else of that type is approved there (any type);
- on the primary type it covers nobody: a Novo needs hours, so Novos are never approved on one;
- nobody joins one: its owner or an admin adds the people.

Approvers see why in `approval_problem` / the panel warning.

When that person stops counting (cancels, is rejected, leaves, is deleted, moved or handed over),
approved schedules at those hours that nobody else who may go alone covers go **back to pending**:
their people are told ("O seu agendamento voltou a aguardar aprovação") and approvers get them to
decide again.

The rules live in `App\Services\PrimaryScheduleRule` and `App\Services\AllDayRule` and run from the models' `saving` hooks,
so the admin panel, the API and the PWA all obey it. Approvers see why something cannot be
approved yet (`approval_problem` in the API, a warning in the panel and the PWA).

### Shift slots

A schedule type can work in **slots** (shifts): turn on "Members pick a slot" in the type form
(admin panel or PWA) and list the slots. Each slot has a day, a start and an end (an end of 00:00 means
midnight), an optional maximum of people and a comment shown to everyone who picks it.

- People don't choose hours for these types: they pick a slot (PWA form, admin create page and
  calendar modal). The **first** person books the slot's schedule and owns it; the **next ones
  join** it as participants. Approval, the primary rule and notifications work as usual.
- A slot with a maximum takes that many people, its owner included. A full slot can't be taken
  or joined.
- If the owner leaves while others are on it, the schedule passes to whoever joined first and
  the owner shows as cancelled. Alone, the owner cancels it as usual, and the slot can be taken again.
- A slot's schedule always keeps the slot's times (`ScheduleRulesValidator`). A slot with a
  schedule going on can't be removed and its times are locked. Its comment and maximum can
  still change.
- API: `GET schedules/slots?schedule_type_id=` lists the slots that haven't ended (`people`,
  `capacity`, `full`, `mine`, `schedule_id`, `problem`). `POST schedules/slots/{slot}` takes one.
  Schedules have `slot` (`comment`, `capacity`, `people`), and types have `uses_slots`.
- Type API (`schedule-types`): the detail and the answer to a save have `slots` (`day`, `from`,
  `to`, `capacity`, `comment`, `people`, `taken`). A create or update may send `uses_slots` and
  the whole `slots` list: a row with an `id` changes that slot, one without is new, a slot left
  out is removed. Without `slots` the slots stay as they are (`App\Services\SlotDefinitions`).

The rules live in `App\Services\ScheduleSlots` (tables `schedule_type_slots` and
`schedules.schedule_type_slot_id`).

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
they are only sent while a worker runs (on shared hosting without Supervisor, set
`QUEUE_WORK_VIA_SCHEDULER=true` instead: the scheduler cron then runs it every minute):

```bash
php artisan queue:work
```

Keep it running in production (Supervisor on Linux, NSSM or Task Scheduler on Windows).
`composer dev` already starts one in development. Failed jobs: `php artisan queue:failed`.

**Jobs monitor** (Administração → Tarefas em fila, croustibat/filament-jobs-monitor): every job
that ran (emails, pushes, coverage alerts…) with its time and result, the jobs still waiting
(Pending, only with the `database` queue) and the failed ones grouped by error, with retry.
Opening it needs `view_any_queue_monitor`; deleting its records, `delete_queue_monitor`. Anyone who
can open it can also retry jobs and clear the history. The plugin's Pending and Failures pages
check no permission of their own, so the app replaces them (`app/Filament/Resources/QueueMonitors`).
It only sees jobs: if the scheduler never runs, it stays empty (check `php artisan schedule:list`).

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
| `notifications:digest --with-daily` / `notifications:digest` | daily at 08:00 / 18:00 | the email summaries (see "Email summaries"); the 08:00 run also covers who chose one a day |
| `filament-jobs-monitor:prune` | daily at 03:30 | deletes the jobs monitor's records older than 7 days |

Check them with `php artisan schedule:list`. Without the scheduler everything still works;
expired tokens are just not cleaned up.

## Notifications

Every app notification (approvals, changes, joins, coverage alerts, announcements, account
approved…) is stored in the `notifications` table, the person's history in the bell and the PWA,
and the admin "Notifications" log. Email and push are copies of it, per the user's settings:

| Setting | What it covers |
|---|---|
| `email_notification`, `push_notification` | things about the person (their schedules, approvals, their account) |
| `all_email_notification`, `all_push_notification` | "all notifications": also **other people's** schedules — booked, approved, changed, cancelled / rejected, people joining or leaving (`App\Notifications\ScheduleActivity`, sent by `App\Services\ScheduleActivityFeed`) |

All four are on for new accounts. "All notifications" go to active, approved people who can open
the agenda (`view_any_schedule`), except whoever did it and the people already told personally.
Not stored: the password-reset email, email verification, and the "new device" email of the
authentication log.

### Email summaries

News that can wait does not get an email of its own: it is kept for a summary (`users.email_digest`,
chosen in the PWA settings and the admin profile; values and labels in `App\Enums\DigestFrequency`,
stored as a plain string so a new value needs no migration):

| `email_digest` | Emails that can wait |
|---|---|
| `twice` (default) | one summary at 08:00 and one at 18:00 |
| `daily` | one summary at 08:00 |
| `instant` | each one right away, as before |

- **Can wait** (`digestible()` returns true): people joining or cancelling (`ScheduleJoinedOrLeft`),
  being added (`ScheduleParticipantAdded`), changes (`ScheduleUpdated`), agenda activity
  (`ScheduleActivity`), requests waiting (`SchedulePendingApproval`, `ParticipationRequested`),
  no-shows recorded (`NotShownRecorded`), coverage alerts, and announcements not marked important.
- **Always right away**: account approved, password reset, decisions about the person's schedule
  (approved / rejected / cancelled, back to pending), booked or handed over to them, taken off a
  schedule, marked as not shown, important announcements.
- The summary (`App\Notifications\EmailDigest`, sent by `notifications:digest`) only has what the
  person has **not read** in the app yet, grouped (to decide, your schedules, on the agenda,
  messages), each line linking to the PWA. Nothing new: no email.
- In the notifications log: `mail_status` = "In the next summary" while waiting, then "Sent";
  read in the app before the summary (or email turned off): empty.
- Push and the in-app list are not affected.

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