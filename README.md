# Multi-Tenant Payment Wallet

Onboarding assignment — a multi-tenant wallet platform where each tenant (merchant) has its own isolated database, its own players (`User`) and admins (`Admin`), and a wallet/transaction system backed by a real sandbox payment gateway.

**Status:** in progress. Deposit flow (init + gateway callback) is built and verified against the real staging gateway. Withdrawal flow, the concurrency proof, and the automated money-logic test suite are not yet built — see [Known Issues](#known-issues--unfinished-work) below.

---

## Tech Stack

- Laravel 13 (PHP 8.4 locally / 8.3+ required)
- Vue 3 (thin UI, backend-weighted per the assignment)
- PostgreSQL
- `stancl/tenancy` — multi-database mode
- `tymon/jwt-auth` — two separate guards
- Pest
- Laravel Pint

---

## Setup & Run Instructions

### 1. Clone and install dependencies

```bash
git clone <repo-url>
cd multi-tenant-project
composer install
npm install
```

### 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Set these in `.env` (see `.env.example` for the full list):

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=multi-tenant-project
DB_USERNAME=postgres
DB_PASSWORD=<your-local-postgres-password>

CACHE_STORE=file

JWT_SECRET=<generate with: php artisan jwt:secret>
```

**Important — `CACHE_STORE` must be `file`, not `database`.** `stancl/tenancy`'s `CacheTenancyBootstrapper` tags every cache call with the current tenant id, and the database cache driver reads/writes the `cache` table through whatever DB connection is currently active — which tenancy swaps to the tenant connection, but `cache` is a central-only table. This throws a "relation cache does not exist" error on any tenant-scoped request. `file` avoids the whole class of bug (it also can't do cache *tagging*, which briefly caused a different error during development — solved by not caching the gateway's live currency-limit lookup at all, and just calling it live every time, which is arguably more correct per the assignment's "use live values" requirement anyway).

### 3. Database

```bash
php artisan migrate
```

This runs only the **central** migrations (`tenants`, `domains`, `cache`, `jobs` — see [Architecture](#architecture--design-decisions) for why tenant tables aren't here).

### 4. Create a test tenant

```bash
php artisan tenant:create tenant-a.multi-tenant-project.test
```

This one command:
- Creates the tenant + its domain record (central DB).
- Provisions a **new database** for that tenant and runs all tenant migrations against it.
- Creates one initial player (`User`) and one initial admin (`Admin`) inside that tenant's database, with random passwords printed to the console.

Optional flags if you want known credentials instead of random ones:
```bash
php artisan tenant:create tenant-a.multi-tenant-project.test \
  --user-email=player@tenant-a.test --user-password=password \
  --admin-email=admin@tenant-a.test --admin-password=password
```

Repeat with a second domain (e.g. `tenant-b.multi-tenant-project.test`) to get a second, fully isolated tenant for testing cross-tenant isolation manually.

**Local domain note (Laravel Herd):** Herd only serves domains matching a parked/linked project, plus that project's `*.<project-folder-name>.test` wildcard. Since this project's folder is `multi-tenant-project`, tenant domains must follow the pattern `<name>.multi-tenant-project.test` — an arbitrary domain like `tenant-a.test` will hit Herd's own "site not found" page before ever reaching Laravel.

### 5. Frontend

```bash
npm run dev
```

### 6. Log in as a player or an admin

- Player login page: `http://tenant-a.multi-tenant-project.test/api/login`
- Admin login page: `http://tenant-a.multi-tenant-project.test/admin/login`

Both are minimal Vue pages built purely for manual click-through testing of the guard separation and wallet flow — not a graded deliverable in themselves.

### 7. Payment gateway credentials

Per the assignment's explicit requirement, gateway credentials are **never** stored in `.env` or config — they live only in the `gateway_settings` table (encrypted at rest via an `encrypted` Eloquent cast). Seed them directly into the tenant's database via `tinker`:

```bash
php artisan tinker
```
```php
$tenant = \App\Models\Tenant::first();

$tenant->run(function () {
    \App\Models\GatewaySetting::updateOrCreate(
        ['gateway_name' => 'sandbox'],
        [
            'environment' => 'staging',
            'username' => '<merchant-username>',
            'base_url' => '<gateway-base-url>',
            'api_key' => '<api-key>',
            'api_secret' => '<secret-key>',
            'is_active' => true,
        ]
    );
});
```

### 8. Exposing the callback endpoint (ngrok)

The gateway confirms deposits/withdrawals via a server-to-server callback POST, so it needs a publicly reachable URL, not `localhost`:

```bash
ngrok http 80
```

Set `GATEWAY_DEPOSIT_CALLBACK_URL` in `.env` to the resulting `https://<random>.ngrok-free.app/api/deposits/callback`.

> **Not yet done in this repo** — see [Known Issues](#known-issues--unfinished-work). The callback handler itself is built and has been verified by manually POSTing a correctly-signed payload directly to the endpoint, but has not yet been exercised by a real callback fired by the gateway through ngrok.

---

## Architecture & Design Decisions

**Tenancy.** `stancl/tenancy` in multi-database mode. Tenants are identified by domain first, falling back to an `X-Tenant` header for non-browser clients — both identification middlewares are chained with explicit `$onFail` handling so a header-based request doesn't get wrongly rejected just because it also happens to hit a resolvable domain, and vice versa. Central DB holds only `tenants`/`domains` (+ `cache`/`jobs`, kept central by package convention); every wallet/user/admin/transaction table lives per-tenant, split into `database/migrations/` (central) vs `database/migrations/tenant/` (tenant) — the package's most common failure mode is putting a migration in the wrong one.

**Auth.** Two fully separate models (`User`, `Admin`), two fully separate JWT guards (`api`, `admin`), two separate route files (`routes/api.php`, `routes/admin.php`). No shared table, no `is_admin` flag.

**Wallet as its own table**, not a `users.balance` column — this gives concurrency-safe row locking (`lockForUpdate()`) a correctly-scoped lock target (the wallet row) instead of locking the whole `users` row for an unrelated reason. Balance is `decimal(15,2)`, cast as `decimal:2` (kept as a PHP string, never a float) — all arithmetic on it uses `bcadd`/`bcsub`/`bccomp`, never `+`/`-`/`<`/`>`.

**Gateway credentials** live in a `gateway_settings` table (`encrypted` cast on `api_key`/`api_secret`, both `$hidden` so they never leak into a JSON response even by accident) — never `.env`/config.

**Driver + DTO pattern.** `PaymentGatewayInterface` defines the contract (`auth()`, `currencyLimits()`, `depositBanks()`, `createDeposit()`); `SandboxPaymentGateway` is the concrete implementation, bound in `AppServiceProvider::register()`. Every service (`DepositService`, etc.) type-hints the interface, never the concrete class — swapping to a different gateway later is a one-line change in the binding, not a rewrite. Every gateway response is converted into a typed, `readonly` DTO (`GatewayAuthResult`, `DepositOrderResult`, `CurrencyLimit`, `Bank`) immediately after the HTTP call — nothing downstream touches a raw associative array from the gateway.

**Deposit flow assumptions:**
- `order_id` is generated by us (`DP-<ULID>`) *before* the gateway is ever called, and is the anchor the later callback is matched against.
- Live currency min/max limits are fetched fresh from the gateway on every deposit attempt (no caching — see the `CACHE_STORE` note above for why), rather than hard-coded, per the assignment's explicit requirement.
- `phone_number` is collected per-deposit on the request rather than added as a `users` column — a phone number tied to one deposit attempt felt more correct than assuming it as a fixed profile field the gateway's `generate_orders` endpoint happens to need.

**Idempotency.** Every transaction has a nullable `processed_at` timestamp. The callback handler checks it first — if already set, the callback is treated as a no-op (`200 {"status": true}`, no wallet mutation), not an error. This is what makes a duplicate/out-of-order callback from the gateway safe.

**Callback verification.** The gateway's scheme is `token === MD5(secret_key + order_id)`. `GatewayCallbackChecker::isRealCallback()` recomputes the expected token from our stored `api_secret` and compares it against the incoming `token` using `hash_equals()` (constant-time comparison — standard practice for any signature check, avoids timing-based leakage). A callback that fails this check is rejected with a `422` before touching the transaction or wallet at all.

**Concurrency safety (design, applies to withdrawal once built).** Balance checks and mutations happen inside `DB::transaction()` against a row fetched with `lockForUpdate()`. Postgres holds a row-level lock on the wallet from the moment `lockForUpdate()` runs until the transaction commits — a second concurrent request's own `lockForUpdate()` on the same row blocks until the first finishes, then reads the already-updated balance rather than a stale one. (This pattern is already proven out on the deposit-callback crediting path; it is designed to be reused as-is for withdrawal's hold/deduct step.)

**Status as a backed enum**, not raw strings — `TransactionStatus` (`pending`/`processing`/`success`/`failed`/`cancelled`), `TransactionType` (`deposit`/`withdrawal`). Nowhere in the codebase is a transaction status compared or set as a bare string.

**Thin controllers.** Every controller here validates via a Form Request, delegates to a service, and returns a response — no business logic in a controller method.

---

## Known Issues / Unfinished Work

Being upfront about what's not done rather than papering over it:

- **Withdrawal flow (assignment section 3.5) is not implemented yet** — no `WithdrawalService`, no withdrawal DTOs/driver method, no withdrawal routes. This is the single biggest gap remaining.
- **The concurrency double-spend proof (the assignment explicitly says this will be tested) has not been run** — it depends on withdrawal existing first.
- **Automated Pest tests for money logic don't exist yet.** What's currently covered by an automated Pest suite: tenant isolation (`TenancyIdentificationTest`) and all 4 guard-separation cases (`AuthGuardSeparationTest`). Deposit + callback have been verified **manually** (real staging gateway calls via the UI, and a hand-crafted, correctly-signed callback payload via curl/Postman) but not yet turned into an automated test using `Http::fake()`.
- **The bank-list gateway endpoint (`depositBanks()`) is built on the driver but has no controller/route yet** — the deposit UI currently has a manual "type in a bank ID" field instead of a real dropdown populated from the gateway.
- **ngrok/live-callback wiring is documented above but not yet exercised** — the callback handler's correctness has been verified by POSTing a manually-signed payload directly to the endpoint, not by a real callback arriving from the gateway itself.
- **Laravel Pint has not yet been run for a final clean pass.**

---

## Testing

```bash
vendor/bin/pest
```

Currently green: tenant isolation, all 4 auth-guard cases. Deposit/withdrawal/concurrency tests are the next tests to be written (see above).
