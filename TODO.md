# To-Do List

## Section 6 — Bonus scope (doing first)

- [ ] Merchant float balance — driver `balance()` method, endpoint, display it somewhere
- [ ] Dashboard with summary stats (total in / out, current balance)
- [ ] Transaction filtering (type / status / date range)
- [ ] Reconciliation — resolve stale `pending`/`processing` transactions via `check_status`/`check_withdraw_status`
- [ ] Queued callback jobs — move callback processing off the request thread

## Section 11.0 — Access Model (senior track, after Section 6)

- [x] `spatie/laravel-permission` installed
- [x] Permission tables migrated (central + tenant, copied)
- [ ] `central_admins` migration + `CentralAdmin` model
- [ ] `HasRoles` trait on `Admin` / `User` / `CentralAdmin`
- [ ] `central-admin` guard + provider in `config/auth.php`
- [ ] `CentralAdminAuthController` (login + me) + routes in `routes/web.php`
- [ ] Permission cache flush on tenant switch (`TenancyServiceProvider`) — the gotcha the assignment explicitly warns about
- [ ] `CentralRoleSeeder` (central-admin role + one real admin)
- [ ] `CreateTenantCommand` — assign `tenant-admin` / `end-user` roles on creation
- [ ] `tests/Feature/CentralAccessModelTest.php` — proves login + guard boundaries
- [ ] `vendor/bin/pint`
- [ ] README — document 11.0 done, explicitly note 11.1/11.2 (full back-office CRUD) not attempted and why

Full detailed plan for the access model: `/Users/user/.claude/plans/kind-whistling-diffie.md`, Part F section.

## Not code

- [ ] Report the gateway's IP-allowlist issue (blocks real `withdraw_orders`) to whoever issued the sandbox credentials

## Already fully done (core assignment)

Tenancy, guards, deposit, withdrawal, concurrency proof, bank lists (both directions), rate limiting on withdrawals, self-service registration, both automated test suites, Pint clean, README, deposit-flow diagram.
