# Dabberhaly — Deployment Guide

Egyptian e-commerce store (Laravel 10 + Inertia.js 2 + Vue 3 + Vuetify 3).
Payments: Moyasar (SAR) + cash on delivery. Arabic/English with RTL.

> **Local development** is documented in
> `docs/project-documentation/12-installation-development.md`.
> This file is the **production** checklist — do not copy dev settings
> (`.env` with `APP_DEBUG=true`, `QUEUE_CONNECTION=sync`, mailpit) to a
> live server.

## 1. Server requirements

- PHP 8.3 with extensions: `cli, common, mysql, mbstring, xml, curl, zip,
  bcmath, intl, gd`
- MySQL 8.0
- Composer 2.x, Node.js 20 + npm
- Supervisor (queue worker) and cron (scheduler)
- HTTPS with a valid certificate (session cookies are `Secure` in production)

## 2. Environment variables

```bash
cp .env.example .env
php artisan key:generate
```

`.env.example` already ships production-safe defaults. Fill in:

| Variable | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | the real store URL, e.g. `https://dabberhaly.com` |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | the production database + credentials |
| `QUEUE_CONNECTION` | `database` (never `sync` in production) |
| `SESSION_SECURE_COOKIE` | `true` |
| `LOG_LEVEL` | `error` |
| `MAIL_*` | a real SMTP provider — see §6 |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | optional; used by `AdminSeeder` on first seed (see §4) |

**Moyasar and SMS keys are NOT `.env` variables.** No code reads
`MOYASAR_*` or `SMS_*` from the environment — the real keys live in the
`settings` table and are managed from the admin panel
(`/admin/front_settings`). The `SettingsSeeder` only copies them from the
environment at seed time if they happen to be set.

## 3. Install

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
# Seed BEFORE config:cache — the seeders read ADMIN_EMAIL /
# ADMIN_PASSWORD and MOYASAR_*/SMS_* via env(), which returns null
# once the config is cached (see §4).
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **Telescope note:** `laravel/telescope` is a dev-only package. Its
> provider is registered only when the package is actually installed, so
> `composer install --no-dev` boots fine. Never install Telescope in
> production; its gate (`App\Providers\TelescopeServiceProvider`) has an
> empty allow-list, so nobody can open `/telescope` even if it were enabled.

## 4. Seed — core data only (runs in §3, before `config:cache`)

```bash
php artisan db:seed --force
```

`DatabaseSeeder` runs **core seeders only** in production:

- `AdminSeeder` — super-admin account. Email from `ADMIN_EMAIL`
  (default `admin@dabberhaly.com`); password from `ADMIN_PASSWORD`.
  **If `ADMIN_PASSWORD` is not set, the default password is `12345678`.**
  Change it from the admin panel after first login.
- `SettingsSeeder` — settings skeleton (store name دبّرهالي; phone/email/
  address left **empty** until filled in `/admin/front_settings`).
- `CurrencySeeder` — EGP only (default, rate 1). Moyasar still charges in
  SAR — that is separate from the store currency and is hardcoded in the
  Moyasar payment flow, not read from this table.
- `CountryCitySeeder` — Egypt only (27 governorates); re-seeding removes
  any non-Egyptian country.
- `ShippingTypesAndPriceSeeder` — shipping row **id 1**. Checkout does
  `ShippingTypesAndPrice::find(1)` directly, so this row must exist or
  every checkout returns 500.
- `StaticPagesSeeder`, `StoreFeaturesSeeder` — pages + store features.

Demo seeders (`ProductSeeder`, `AdvertisementSeeder`, `DesignSeeder`,
`CompanySeeder`, `UserSeeder`) run **only when `APP_ENV=local`** — a
production seed never creates demo products, ads or a test customer.

After seeding, fill in from the admin panel before opening the store:
store phone/email/address, Moyasar publishable + secret keys, SMS
credentials, VAT (if any), free-shipping threshold, and the admin's
contact/social links (footer + contact page read them from settings).

## 5. Queue worker (required — emails depend on it)

Order confirmation emails, newsletters, admin notifications and password
reset codes are **queued jobs**. Without a running worker they pile up in
the `jobs` table and are never sent — including the password-reset email,
so a reset stops working entirely. (The reset code itself is created
synchronously when the reset is requested; only sending it is queued.)

Install the Supervisor program (edit the project path + user first):

```bash
sudo cp deploy/supervisor/dabberhaly-queue.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start dabberhaly-queue
```

It runs `php artisan queue:work database --sleep=3 --tries=3
--timeout=120` as a daemon. `--timeout=120` must stay below the queue
`retry_after=180` in `config/queue.php`. Worker log:
`storage/logs/queue-worker.log`.

After every deploy, restart the worker so it picks up new code:

```bash
php artisan queue:restart
```

## 6. Mail provider (the user must choose one)

`.env.example` documents a generic SMTP setup; **no provider is chosen
for you**. Pick one — Mailgun, Postmark, Amazon SES, or any SMTP
service — create an account, verify the sending domain, and fill in
`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`,
`MAIL_ENCRYPTION` and `MAIL_FROM_ADDRESS` in `.env`.

`mailpit` is a local development tool only and must never be used in
production. Until a real provider is configured, order emails,
newsletters and password resets fail after their retries and land in
`failed_jobs`.

Verify mail works before launch:

```bash
php artisan tinker --execute="Mail::raw('Dabberhaly mail test', fn (\$m) => \$m->to('you@example.com')->subject('test'));"
```

## 7. Scheduler (cron)

One cron entry, every minute:

```cron
* * * * * cd /var/www/dabberhaly && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs (`app/Console/Kernel.php`, all daily):

- `telescope:prune` — prune Telescope entries (only scheduled when
  Telescope is installed — dev only; skipped on a `--no-dev` server)
- `queue:prune-failed --hours=72` — drop failed jobs older than 3 days
- `app:prune-expired-data` — delete expired verification codes
  (`users_verificationCodes`) and carts untouched for 30 days
  (`--days=N` to change the window)

## 8. Moyasar webhook

Register this exact URL in the Moyasar dashboard (Settings → Webhooks):

```
POST {APP_URL}/api/v1/webhooks/moyasar
```

Without a webhook, a customer who pays successfully and then closes the
tab leaves the order stuck at `payment_status=unpaid`, because today only
the redirect callback marks orders paid. After registering it, place a test
card payment, close the tab before the redirect, and confirm the order
still becomes `paid`.

## 9. Backups

There is no backup package in the repo yet — run manual cron backups
until one is added (follow-up option: `spatie/laravel-backup`):

```cron
# Database — daily at 02:00, keep 14 days
0 2 * * * mysqldump -u dabberhaly -p'<password>' dabberhaly | gzip > /var/backups/dabberhaly/db-$(date +\%F).sql.gz && find /var/backups/dabberhaly -name 'db-*.sql.gz' -mtime +14 -delete
# Uploaded files — daily at 02:30, keep 14 days
30 2 * * * tar -czf /var/backups/dabberhaly/storage-$(date +\%F).tar.gz -C /var/www/dabberhaly/storage/app public && find /var/backups/dabberhaly -name 'storage-*.tar.gz' -mtime +14 -delete
```

Store the backups off the server (or they disappear with it). Test a
restore at least once before launch — an untested backup is not a backup.

## 10. Health check & logs

- Health endpoint: `GET /up` returns `{"status": "ok"}` with HTTP 200.
  Point the uptime monitor / load balancer at it. It does no database
  query — it only proves the app boots and routes.
- Logs: default channel is `daily` with 14 days retention
  (`config/logging.php`, `LOG_LEVEL=error` in production).
  Watch `storage/logs/laravel-*.log` and `storage/logs/queue-worker.log`.

## 11. Deploy checklist (every release)

1. `git pull` (branch `stage`, after testing — never deploy `main`
   unreviewed)
2. `composer install --no-dev --optimize-autoloader`
3. `npm install && npm run build`
4. `php artisan migrate --force`
5. First deploy only — `php artisan db:seed --force` (creates the admin
   account, the settings row and shipping row id 1; without it a fresh
   server cannot take any order) **before** `config:cache`, and
   `php artisan storage:link` (otherwise all product images 404)
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
7. `php artisan queue:restart`
8. `curl -s -o /dev/null -w "%{http_code}\n" https://your-domain/up` → expect `200`
9. Place a test order (COD) and confirm the confirmation email arrives.
