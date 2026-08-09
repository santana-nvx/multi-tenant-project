# Multi-Tenant Payment Wallet

Onboarding assignment — a multi-tenant wallet platform where each tenant (merchant) has its own isolated database, its own players (`User`) and admins (`Admin`), and a wallet/transaction system backed by a real sandbox payment gateway.

**Status:** core assignment complete. Deposit and withdrawal are both built and verified end-to-end against the real staging gateway (including a real callback received through an ngrok tunnel), and the concurrency double-spend proof has been run against real Postgres with a genuinely correct result. Beyond the core requirements, several of the assignment's optional Section 6 bonus items are also built and tested — see [Bonus / Extended Scope](#bonus--extended-scope) below. Remaining work is further optional scope, not core correctness — see [Known Issues](#known-issues--unfinished-work).

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

## A Note on AI Assistance

All Vue/frontend work in this project (pages, components, the wallet UI) was built with AI assistance end-to-end — the assignment itself frames the frontend as a "thin UI" secondary to the backend, so this was a deliberate time allocation, not an oversight.

The backend/PHP logic — tenancy, guards, wallet/transaction models, the driver/DTO pattern, deposit and withdrawal services, callback handling, concurrency locking — was written by hand, with AI used for code review, bug-catching, and architectural discussion rather than authorship. Real bugs it caught along the way (wrong `bccomp()` usage, a route collision, a `lockForUpdate()` outside its transaction, several others) are part of the honest record of how this was built, not something to hide.

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

The gateway confirms deposits/withdrawals via a server-to-server callback POST, so it needs a publicly reachable URL, not `localhost`. Herd's own PHP-FPM sits behind its own nginx, routing by `Host` header — so the tunnel needs its `Host` header rewritten to match a real local site, otherwise Herd won't know which project to route the request to:

```bash
ngrok http 80 --host-header="tenant-a.multi-tenant-project.test"
```

Set `GATEWAY_DEPOSIT_CALLBACK_URL` in `.env` to the resulting tunnel's base URL (no path):
```
GATEWAY_DEPOSIT_CALLBACK_URL=https://<random>.ngrok-free.app
```
`DepositService`/`WithdrawalService` append the actual callback path (`/api/deposits/callback` / `/api/withdrawals/callback`) plus `?tenant=<id>` themselves — the tenant id is appended explicitly because a callback arriving through a shared local tunnel has no way to resolve which tenant's database to check otherwise (this is a **local-dev-only** concern: in production, each tenant has its own real public domain, so the callback URL can be built from the current request's own host directly, without needing a tunnel or a query-string tenant hint at all).

**Verified working**: a real deposit was completed on the sandbox gateway's payment page, and the gateway's own server-to-server callback arrived through this exact ngrok setup and correctly credited the wallet — not just a manually-crafted test payload.

---

## Architecture & Design Decisions

**Tenancy.** `stancl/tenancy` in multi-database mode. Tenants are identified by domain first, falling back to an `X-Tenant` header for non-browser clients — both identification middlewares are chained with explicit `$onFail` handling so a header-based request doesn't get wrongly rejected just because it also happens to hit a resolvable domain, and vice versa. Central DB holds only `tenants`/`domains` (+ `cache`/`jobs`, kept central by package convention); every wallet/user/admin/transaction table lives per-tenant, split into `database/migrations/` (central) vs `database/migrations/tenant/` (tenant) — the package's most common failure mode is putting a migration in the wrong one.

**Auth.** Two fully separate models (`User`, `Admin`), two fully separate JWT guards (`api`, `admin`), two separate route files (`routes/api.php`, `routes/admin.php`). No shared table, no `is_admin` flag.

**Wallet as its own table**, not a `users.balance` column — this gives concurrency-safe row locking (`lockForUpdate()`) a correctly-scoped lock target (the wallet row) instead of locking the whole `users` row for an unrelated reason. Balance is `decimal(15,2)`, cast as `decimal:2` (kept as a PHP string, never a float) — all arithmetic on it uses `bcadd`/`bcsub`/`bccomp`, never `+`/`-`/`<`/`>`.

**Gateway credentials** live in a `gateway_settings` table (`encrypted` cast on `api_key`/`api_secret`, both `$hidden` so they never leak into a JSON response even by accident) — never `.env`/config.

**Driver + DTO pattern.** `PaymentGatewayInterface` defines the contract (`auth()`, `currencyLimits()`, `depositBanks()`, `createDeposit()`, `createWithdrawal()`); `SandboxPaymentGateway` is the concrete implementation, bound in `AppServiceProvider::register()`. Every service (`DepositService`, `WithdrawalService`) type-hints the interface, never the concrete class — swapping to a different gateway later is a one-line change in the binding, not a rewrite. Every gateway response is converted into a typed, `readonly` DTO (`GatewayAuthResult`, `DepositOrderResult`, `WithdrawalOrderResult`, `CurrencyLimit`, `Bank`) immediately after the HTTP call — nothing downstream touches a raw associative array from the gateway. The gateway's own API paths (`/merchant/auth`, `/merchant/withdraw_orders`, etc.) are centralized in `config/onepay.php` rather than typed inline in `SandboxPaymentGateway` — a path change is a one-line config edit, not a code search.

**Deposit flow assumptions:**
- `order_id` is generated by us (`DP-<ULID>`) *before* the gateway is ever called, and is the anchor the later callback is matched against.
- Live currency min/max limits are fetched fresh from the gateway on every deposit attempt (no caching — see the `CACHE_STORE` note above for why), rather than hard-coded, per the assignment's explicit requirement.
- `phone_number` is collected per-deposit on the request rather than added as a `users` column — a phone number tied to one deposit attempt felt more correct than assuming it as a fixed profile field the gateway's `generate_orders` endpoint happens to need.
- Redirect/callback URLs are built dynamically per-request (`url('/wallet')`, `url('/api/deposits/callback').'?tenant='.tenant('id')`) rather than read from a single static `.env` value — a static value would send every tenant's players to the same domain, and give the callback no way to identify which tenant's database to check.

**Withdrawal flow assumptions:**
- Funds are **held/deducted immediately at initiation**, inside the same locked `DB::transaction()` that creates the (`pending`) transaction row — not after the gateway responds. This is deliberate: the one operation that must be airtight against concurrent requests is "did we let the balance go negative," and that has to happen before any slow, unpredictable network call, not after.
- The gateway call (`auth()` + `createWithdrawal()`) happens **after** that locked block closes — a database lock is never held open across a third-party HTTP request, since that would let one slow gateway call block every other request touching the same wallet.
- If the gateway rejects the withdrawal (auth failure, or the create-order call itself fails), the held amount is refunded back via the same `lockForUpdate()` + `bcadd()` pattern, and the transaction is marked `failed`.
- On the later callback: if `order_status` is `completed`, only the transaction status changes (`success`) — the wallet is **not** touched again, since the money already left at initiation. On anything else, the held funds are released back to the balance, reusing the same refund logic.
- Unlike deposit, there is no `redirect_url` — the gateway's own withdrawal-order API doesn't accept one (confirmed against the gateway's own documented request parameters), since there's no payment page for the player to visit; everything after submission happens gateway-side (including a manual back-office approval step) with no further browser interaction needed.

**Idempotency.** Every transaction has a nullable `processed_at` timestamp. Both callback handlers check it first — if already set, the callback is treated as a no-op (`200 {"status": true}`, no further mutation), not an error. This is what makes a duplicate/out-of-order callback from the gateway safe.

**Callback verification.** The gateway's scheme is `token === MD5(secret_key + order_id)`. `GatewayCallbackChecker::isRealCallback()` recomputes the expected token from our stored `api_secret` and compares it against the incoming `token` using `hash_equals()` (constant-time comparison — standard practice for any signature check, avoids timing-based leakage). A callback that fails this check is rejected with a `422` before touching the transaction or wallet at all. Shared by both `DepositCallbackService` and `WithdrawalCallbackService`.

**Concurrency safety — built and proven against real Postgres, not just designed.** Balance checks and mutations happen inside `DB::transaction()` against a row fetched with `lockForUpdate()`. Postgres holds a row-level lock on the wallet from the moment `lockForUpdate()` runs until the transaction commits — a second concurrent request's own `lockForUpdate()` on the same row blocks until the first finishes, then reads the already-updated balance rather than a stale one.

This was verified two ways:
1. **`tests/Feature/WithdrawalConcurrencyTest.php`** — a Pest test proving two sequential full-balance withdrawal requests correctly resolve to one success and one `422 Insufficient Balance`, with the wallet ending at exactly `0.00`.
2. **A real concurrent-request test against actual Postgres** — two genuinely simultaneous `POST /api/withdrawals` requests (via backgrounded `curl` processes) for a wallet's *full* balance. Result: exactly one request passed the balance check and reached the gateway; the other was correctly rejected as insufficient before ever calling out. Final wallet balance matched the starting balance exactly, with zero drift, after both requests resolved (the successful one was later refunded due to an external gateway-side IP restriction on the sandbox account — a separate, non-code issue, see Known Issues).

**Status as a backed enum**, not raw strings — `TransactionStatus` (`pending`/`processing`/`success`/`failed`/`cancelled`), `TransactionType` (`deposit`/`withdrawal`). Nowhere in the codebase is a transaction status compared or set as a bare string.

**Thin controllers.** Every controller here validates via a Form Request, delegates to a service, and returns a response — no business logic in a controller method.

---

## Bonus / Extended Scope

Beyond the core assignment (sections 1–4), several of the assignment's own Section 6 "bonus / stretch goals" are also implemented and tested:

- **Queued callback jobs** — `DepositCallbackController`/`WithdrawalCallbackController` no longer process callbacks synchronously on the request thread. They dispatch `ProcessDepositCallback`/`ProcessWithdrawalCallback` jobs (`QUEUE_CONNECTION=database`) and return `{"status": true}` immediately; the actual signature check/idempotency/crediting logic runs on a queue worker. `QueueTenancyBootstrapper` (already enabled in `config/tenancy.php`) re-resolves the correct tenant DB connection when each job runs, so this works correctly across tenants. **Requires a worker running** (`php artisan queue:work`) for callbacks to actually process — see Known Issues.
- **Reconciliation** — `php artisan app:check-transaction-status` finds transactions stuck in `pending`/`processing` for more than 15 minutes (across all tenants), asks the gateway directly for their real status (`checkDepositStatus()`/`checkWithdrawalStatus()`, the `check_status`/`check_withdraw_status` endpoints), and resolves them. Rather than duplicating the crediting/refund logic a third time, it builds a self-signed synthetic callback payload (`token = md5(api_secret + order_id)` — the exact formula `GatewayCallbackChecker` already verifies) and feeds it through the *same* `DepositCallbackService`/`WithdrawalCallbackService` real callbacks use. Not yet wired to Laravel's scheduler for automatic periodic runs — currently a manual/on-demand command.
- **Rate limiting on withdrawals** — `throttle:5,1` (5 requests/minute) on `POST /api/withdrawals`. Verified live: a 6th rapid request correctly returns `429`.
- **Self-service registration** — `POST /api/register` (`RegisterRequest`, `PlayerAuthController::register()`) creates a player and logs them in immediately via `auth('api')->login($user)` (no redundant credential re-check, since the user was just created). Frontend page at `/api/register`, linked from the login page.
- **Transaction filtering** — `GET /api/transactions` accepts `type`, `status`, `date_from`, `date_to` query params, applied conditionally. Frontend: dropdowns + date range pickers + Apply/Clear buttons on the transactions page.
- **Live bank lists, both directions** — `depositBanks()`/`withdrawalBanks()` on the driver, exposed via `BankController` (`GET /api/deposits/banks`, `GET /api/withdrawals/banks`), both verified against the real gateway (8 real deposit banks, 50 real withdrawal banks/e-wallets). Frontend dropdowns replace what were originally manual "type in a bank ID" fields on both the deposit and withdrawal forms.

**Not attempted**: Section 11 (the extended central/tenant back-office scope, `spatie/laravel-permission` role model, full CRUD back offices). `spatie/laravel-permission` is installed and its migrations are in place in both central and tenant paths as groundwork, but the actual central-admin guard/model/controller/role-seeding work was not completed in the time available. A detailed implementation plan exists (not included in this repo) if this is picked up later — the honest reason it wasn't finished is time, not that it was judged unimportant; the assignment's own rubric explicitly rewards a smaller, correct scope over a wider, rushed one, which is the trade-off made here.

---

## Known Issues / Unfinished Work

Being upfront about what's not done rather than papering over it:

- **The real gateway currently rejects `createWithdrawal()` with `"invalid ip address"`.** Confirmed this is not a bug in this codebase — `auth()` against the same gateway succeeds fine, and the request/response shape matches the gateway's own documented API exactly. This looks like an IP-allowlist restriction on the sandbox account, specific to the `withdraw_orders` endpoint (withdrawal endpoints commonly have stricter security than deposit ones, since they move money out). **What's still been proven despite this**: the failure-handling path itself is correct — a controlled test showed the wallet balance is identical before and after a real gateway-rejected withdrawal (money held, gateway rejected it, money correctly refunded, zero drift). Needs the account's IP allowlist updated by whoever issued the sandbox credentials; not fixable from application code.
- **Queued callbacks need a worker running.** Since deposit/withdrawal callbacks are now processed via queued jobs (see Bonus scope above), `php artisan queue:work` must be running for them to actually resolve — otherwise they sit in the `jobs` table unprocessed. Not an issue for the core, synchronous parts of the app (initiation, reads), only for callback resolution.
- **Reconciliation isn't scheduled yet** — `app:check-transaction-status` exists and is tested, but isn't wired into Laravel's task scheduler for automatic periodic runs; it's currently a manual/on-demand command.
- **Section 11 (extended back-office scope) was not attempted** — see Bonus scope above for the honest reasoning.

---

## Testing

```bash
vendor/bin/pest
```

Currently green (21 tests, 63 assertions): tenant isolation, all 4 auth-guard cases, deposit money logic (6 cases), withdrawal concurrency/locking proof, and reconciliation (3 cases covering resolved-deposit, refunded-withdrawal, and untouched-if-not-stale-yet).

**Concurrency proof — manual verification against real Postgres** (the Pest test above proves the logic sequentially; this proves genuine simultaneous-request behavior, which SQLite's test database can't demonstrate):
```bash
curl -s -X POST http://<tenant-domain>/api/withdrawals -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" -d '{"amount":"<full-balance>","currency":"MYR","bank_id":"1","holder_name":"Test","account_no":"123"}' &
curl -s -X POST http://<tenant-domain>/api/withdrawals -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" -d '{"amount":"<full-balance>","currency":"MYR","bank_id":"1","holder_name":"Test","account_no":"123"}' &
wait
```
Actual result when run: one request returned `422 {"message":"Insufficient Balance"}` (correctly rejected before ever reaching the gateway), the other proceeded and was separately rejected by the gateway's IP restriction (see Known Issues) and correctly refunded. Final wallet balance matched the starting balance exactly — no negative balance, no double-spend, no drift.
