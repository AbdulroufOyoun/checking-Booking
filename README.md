# Hotel System API (checking-Booking)

Laravel **12** REST API for the Hotel PMS: reservations, folio, finance, reports, night audit, and RBAC.

| | |
|---|---|
| **Stack** | PHP 8.2+, Laravel 12, Passport 12, Spatie Permission 6 |
| **Frontend** | [BookingSystemFront](https://github.com/AbdulroufOyoun/BookingSystemFront) (Angular 21) |
| **Active branch** | `upgrade/laravel-12` |

---

## Prerequisites

| Tool | Version |
|------|---------|
| PHP | 8.2+ with extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` |
| Composer | 2.x |
| MySQL | 8.x (XAMPP or standalone) |

Optional: Node.js 20+ (only if you also run the Angular SPA locally).

---

## First-time setup (local)

### 1. Clone and install dependencies

```bash
git clone https://github.com/AbdulroufOyoun/checking-Booking.git
cd checking-Booking
git checkout upgrade/laravel-12
composer install
```

### 2. Environment file

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` for local development:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8001

DB_DATABASE=hotel_demo
DB_USERNAME=root
DB_PASSWORD=

FRONTEND_URL=http://127.0.0.1:4300
```

Create the MySQL database before migrating (e.g. `hotel_demo` in phpMyAdmin).

### 3. Passport keys (required once)

```bash
php artisan passport:keys
```

Without OAuth keys, login returns an error. Keys are stored in `storage/oauth-*.key` (not committed).

### 4. Database and demo data

**Full demo** (~650 reservations, recommended for finance/reports testing):

```bash
php artisan migrate:fresh --seed
php artisan demo:verify
```

**Light demo** (faster, skips bulk reservations):

```bash
php artisan demo:setup --fresh --light
```

`migrate --seed` also runs daily-charge backfill and journal backfill automatically.

### 5. Start the API

Use port **8001** — the Angular app expects this port (8000 may conflict with other Laravel apps):

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

API base: `http://127.0.0.1:8001/api/`

Health check: `GET http://127.0.0.1:8001/api/users/setup-check`

### 6. Demo login

| Field | Value |
|-------|--------|
| Job number | `001` |
| Password | `admin123` |

Use the Angular SPA to log in (job number, not email).

After pulling permission changes, re-seed and log in again:

```bash
php artisan db:seed --class=PermissionSeeder
```

---

## Connect the frontend

1. Clone [BookingSystemFront](https://github.com/AbdulroufOyoun/BookingSystemFront) and check out `upgrade/angular-21`.
2. Ensure `src/environments/environment.ts` points to `http://127.0.0.1:8001/api/`.
3. Run `npm install && npm start` → open `http://127.0.0.1:4300/#/auth`.

---

## Recommended demo dates

| Screen | Date / period |
|--------|----------------|
| Room board | `2026-08-01` |
| Financials / accrual reports | August 2026 |
| Cash box report | June 2026 |

---

## Tests and verification

```bash
php artisan test --compact
php artisan demo:verify
php artisan reports:verify
```

Finance-focused:

```bash
php artisan test --compact --filter=Finance
php artisan qa:finance-full
```

API smoke script:

```bash
php scripts/client_api_test.php
```

---

## Production deployment (summary)

On the server after upload:

```bash
composer install --no-dev --optimize-autoloader
php artisan passport:keys --force
php artisan migrate --force
php artisan hotel:ensure-admin
php artisan config:cache
php artisan route:cache
```

Or run the helper script: `bash scripts/server-fix.sh`

Set `APP_DEBUG=false`, strong `APP_KEY`, and change the admin password:

```bash
php artisan hotel:ensure-admin --password='YourSecurePassword'
```

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| Login fails / missing OAuth keys | `php artisan passport:keys` |
| CORS blocked from SPA | Set `FRONTEND_URL` in `.env` to match the SPA origin (include port) |
| Login 404 from Angular | API not running on port **8001** |
| Empty GL / P&L reports | `php artisan accounting:backfill-journal` |
| Finance audit FAIL | `php artisan migrate:fresh --seed` then `php artisan demo:verify` |
| PDO extension missing | Enable `pdo` and `pdo_mysql` in PHP (XAMPP: `php.ini`) |

---

## API contract

- Responses: `Success`, `SuccessData`, `Failed`, `Pagination` (MyHelper pattern).
- Auth: Laravel Passport Bearer token.
- RBAC: Spatie permissions on routes.

---

## Related docs

- [DEMO.md](DEMO.md) — backend demo reference
- [AGENTS.md](AGENTS.md) — development commands (if present in monorepo root)

---

## License

MIT (Laravel framework components). Application code: see repository owner.
