# Installing on shared hosting (cPanel, no terminal)

Everything that needs a command runs **on your PC**. The server only gets files (File Manager),
a database import (phpMyAdmin) and cron jobs.

The examples use `api.example.pt` for the API and admin panel, and `app.example.pt` for the PWA.
Both need **HTTPS**: install and push notifications only work over HTTPS.

---

## Part 1 – On your PC

The Docker containers must be running (`docker compose up -d` in `scheduler-api`).

### 1.1 Fill a clean database

> ⚠️ `migrate:fresh` **deletes everything** in your local database (schedules, users,
> notifications). Export it first in phpMyAdmin if you want a copy.

```bash
docker exec -it api-app php artisan migrate:fresh --force
docker exec -it api-app php artisan db:seed --force
docker exec -it api-app php artisan app:create-admin
```

- `db:seed` creates every permission and the `super_admin` role.
- `app:create-admin` asks for your name, email and password. That is your admin account on the
  server: active, approved and super admin.

Then sign in at http://localhost:9000/admin and prepare what the server should start with:

- **Shield → Roles**: create the roles (Admin, Voluntário Experiente, Voluntário Novo, Externo)
  with their permissions. The README's "Suggested roles" table lists them.
- **Schedule types**: create them, mark the **primary** one, and allow "all day" where it applies.

Don't create volunteers or schedules here: people register on the real site.

### 1.2 Export the database

1. Open phpMyAdmin at http://localhost:9081.
2. Select the database, open **Export**, keep **Quick** and **SQL**, then click **Export**.
3. Save the file, e.g. `scheduler.sql`.

### 1.3 Generate the keys for the server

```bash
php artisan key:generate --show
docker exec api-app php artisan webpush:vapid --show
```

Keep the three values for the server's `.env`: `APP_KEY`, `VAPID_PUBLIC_KEY` and
`VAPID_PRIVATE_KEY`. Run the VAPID command in Docker, because Windows PHP can't create these keys.

### 1.4 Prepare the API files

Your project folder has development packages, so make a clean copy:

1. Create an empty folder, e.g. `D:\release\scheduler-api`.
2. Copy the contents of `scheduler-api` into it, **except**:
   - `.env`
   - `node_modules`
   - `vendor`
   - `tests`
   - `docker`
   - `docker-compose.yaml`
   - `public/storage` (a link to `storage/app/public`)
3. Build the admin panel's assets **in your project folder** (not the copy), then copy
   `public/build` into the copy:
   ```bash
   npm run build
   ```
4. In the copy, install the production packages only:
   ```bash
   cd D:\release\scheduler-api
   composer install --no-dev --optimize-autoloader
   ```
5. In the copy, empty these folders, keeping any `.gitignore` inside them:
   - `storage/logs`
   - `storage/framework/sessions`
   - `storage/framework/views`
   - `storage/framework/cache/data`
   - `storage/app/public` (photos from your tests)
   - in `bootstrap/cache`, delete every file **except** `packages.php` and `services.php`
6. Zip the **contents** of the copy into `scheduler-api.zip`: select everything inside the folder,
   then right-click → Send to → Compressed folder. `storage/app/GeoLite2-City.mmdb` must be
   included (sign-in locations).

### 1.5 Build the PWA

In `pwa-scheduler`, create a file named `.env.production`:

```env
VITE_API_URL=https://api.example.pt/api
VITE_SERVER_TIMEZONE=Europe/Lisbon
```

The build uses this file, and development keeps using `.env`. Then run:

```bash
npm run build
```

Zip the **contents** of `pwa-scheduler/dist` into `pwa.zip`. It must include the hidden
`.htaccess`: in Explorer, View → Show → Hidden items.

---

## Part 2 – On the server (cPanel)

### 2.1 PHP

In **MultiPHP Manager** (or **Select PHP Version**), choose **8.2 or later**. Turn on these
extensions:

- `intl`, `zip`, `mbstring`, `openssl`, `curl`
- `pdo_mysql`, `fileinfo`, `dom`, `xmlreader`
- `bcmath` or `gmp`

### 2.2 Database

1. **MySQL Databases**: create a database and a user, and add the user to the database with
   **All Privileges**. Note the full names, which include your account prefix
   (e.g. `myacc_scheduler`).
2. **phpMyAdmin**: select the new database, then **Import** `scheduler.sql`.

If the import says `Unknown collation: 'utf8mb4_0900_ai_ci'` (MariaDB servers), open the `.sql`
file in a text editor and replace every `utf8mb4_0900_ai_ci` with `utf8mb4_unicode_ci`. Then
import it again.

### 2.3 API files

1. **File Manager**: in your home folder, **outside** `public_html`, create `scheduler-api`.
   Upload `scheduler-api.zip` there and **Extract** it.
2. **Domains** (or **Subdomains**): create `api.example.pt` with document root
   `scheduler-api/public`.

### 2.4 The `.env`

In `scheduler-api`, copy `.env.example` to `.env`. File Manager hides files that start with a
dot; turn on Settings → Show Hidden Files. Edit `.env` and change:

```env
APP_NAME=Scheduler
APP_ENV=production
APP_KEY=base64:...                  # from 1.3
APP_DEBUG=false
APP_URL=https://api.example.pt
PWA_URL=https://app.example.pt

LOG_LEVEL=warning

DB_HOST=localhost
DB_DATABASE=myacc_scheduler         # from 2.2
DB_USERNAME=myacc_scheduler
DB_PASSWORD=...
DB_PASSWORD_ROOT=                   # not used on the server

QUEUE_WORK_VIA_SCHEDULER=true       # the cron also sends emails and pushes

MAIL_MAILER=smtp                    # a cPanel email account (Email Accounts → Connect Devices)
MAIL_HOST=mail.example.pt
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=no-reply@example.pt
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@example.pt
MAIL_FROM_NAME="Scheduler"

VAPID_PUBLIC_KEY=...                # from 1.3
VAPID_PRIVATE_KEY=...

GOOGLE_CLIENT_ID=...                # only if you use Google / Facebook sign-in
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URL=https://api.example.pt/api/auth/google/callback
FACEBOOK_CLIENT_ID=...
FACEBOOK_CLIENT_SECRET=...
FACEBOOK_REDIRECT_URL=https://api.example.pt/api/auth/facebook/callback
```

Add the same callback URLs in the Google and Facebook developer consoles.

### 2.5 Cron jobs

In **Cron Jobs**, add this job set to run **every minute** (`* * * * *`):

```
cd ~/scheduler-api && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

It runs the daily tasks **and** sends the queued emails and push notifications (within a
minute). Cron jobs must call PHP by its full path, `/usr/local/bin/php` (a bare `php` in a cron
job is not the site's PHP version and does nothing). If that path does not exist on another
host, cPanel's PHP page shows the right one, e.g. `/opt/cpanel/ea-php83/root/usr/bin/php`.

`QUEUE_WORK_VIA_SCHEDULER=true` (2.4) is what makes this cron send the emails, password reset
included. If `php artisan config:cache` was ever run, run `/usr/local/bin/php artisan config:clear`
once (as a one-time cron job, like below) after changing `.env`.

**Profile photos** need a link that can only be made by a command. Add a second cron job, once:

```
cd ~/scheduler-api && /usr/local/bin/php artisan storage:link
```

Wait a minute, check in File Manager that `scheduler-api/public/storage` exists, then **delete
this cron job**.

Photos up to 10 MB are accepted (the API shrinks them to avatar size), but PHP often refuses
uploads over 2 MB before the API sees them. In **MultiPHP INI Editor**, pick the API's domain and
set `upload_max_filesize` and `post_max_size` to `12M` (and `memory_limit` to at least `256M`).

### 2.6 PWA

1. Create `app.example.pt` (Domains / Subdomains), e.g. with document root `app.example.pt`.
2. Upload `pwa.zip` into that folder and **Extract** it. `index.html` and `.htaccess` must sit
   directly in the folder, not in a `dist` subfolder.

### 2.7 HTTPS

Run **SSL/TLS Status → Run AutoSSL** for both domains.

---

## Part 3 – Check

- [ ] `https://api.example.pt/admin`: sign in with the account from 1.1.
- [ ] `https://app.example.pt`: sign in, open the agenda, and reload the page there (it must
      not show 404).
- [ ] Create a test schedule: the approvers' email arrives within a minute (the cron works).
- [ ] On a phone: install the app (Settings → Install) and turn on notifications.
- [ ] Upload a profile photo and check that it shows.

If something fails, `scheduler-api/storage/logs/laravel-*.log` (File Manager) says why.

---

## Updating later

1. On your PC, build the new version like in **1.4** and **1.5**. Upload only the changed folders
   (usually `app`, `config`, `database`, `lang`, `resources`, `routes`, `public/build`, and
   `vendor` if packages changed), or the whole zip **without** overwriting `.env` and `storage`.
2. **New database changes** (new files in `database/migrations`) need a one-time cron job, like
   the photo link:
   ```
   cd ~/scheduler-api && /usr/local/bin/php artisan migrate --force && /usr/local/bin/php artisan db:seed --class=PermissionsSeeder --force
   ```
   Wait a minute, check the log, then delete the cron job. New permissions then have to be given
   to the roles in Shield → Roles.
3. The PWA: replace the folder's contents with the new `pwa.zip`. Open apps pick up the new
   version on their next start.

Never copy your PC's database over the server's after the start: the server has the real
schedules and accounts.
