# Dabberhaly — دبّرهالي

Egyptian e-commerce store for household essentials.

**Stack:** Laravel 10 · Inertia.js 2 · Vue 3 · Vuetify 3 · MySQL · Sanctum API ·
Moyasar payments (SAR) + cash on delivery · Arabic/English with RTL.

**Brand:** Vanilla Latte (الفانيليا لاتيه).

## Docs

- **Production deployment:** [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) —
  env vars, install, seeding, queue worker, mail provider, scheduler,
  Moyasar webhook, backups, health check, deploy checklist.
- **Local development:** [`docs/project-documentation/12-installation-development.md`](docs/project-documentation/12-installation-development.md)
- **Full project documentation:** [`docs/project-documentation/`](docs/project-documentation/)

## Quick start (local)

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve
```

Seeders run demo data (products, ads, test customer) only when
`APP_ENV=local`. In production `php artisan db:seed` creates core data
only — see `docs/DEPLOYMENT.md` §4.

## Workflow

Changes go on the `stage` branch; `main` is only merged into after testing.
