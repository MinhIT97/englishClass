# Task 1: Security Review — Configuration & Secrets

**Audit surface**: `C1 — Configuration & Secrets` from the security-review plan.
**Branch**: current working tree (no diff range provided).
**Date**: 2026-06-25
**Auditor**: read-only review, no files modified, no secrets rotated.

---

## 0. Scope & Method

### Files read (full contents)
- `.env`, `.env.example`, `.env.docker`, `.env.test`
- `config/app.php`, `config/jwt.php`, `config/session.php`, `config/logging.php`,
  `config/telegram.php`, `config/services.php`, `config/internal.php`,
  `config/modules.php`, `config/telescope.php`, `config/auth.php`,
  `config/database.php`
- `bootstrap/app.php`
- `app/Providers/AppServiceProvider.php`, `app/Providers/TelescopeServiceProvider.php`
- `app/Http/Middleware/SecurityHeaders.php`,
  `app/Http/Middleware/AuditAdminActions.php`,
  `app/Http/Middleware/AuthenticateDeployHook.php`
- `app/Services/AuditLogger.php`
- `Modules/TelegramBot/Http/Middleware/VerifyTelegramSecret.php`
- `Modules/InternalManager/Http/Middleware/VerifyInternalApiToken.php`
- `app/Http/Controllers/AdminBulkController.php` (lines 95–124 for bcrypt context)
- `docker-entrypoint.sh`
- `SECURITY.md`
- `.gitignore`

### Commands / greps run
- `git check-ignore -v .env` → confirmed `.env` is gitignored and **not tracked**
- Recursive content scan for `env\(` over `app/` (82 PHP files) → **0 matches**
- Recursive content scan for `env\(` over `Modules/` (291 PHP files) → **0 matches**
- Per-file `env\(` count across `config/*.php` (18 files):
  ```
  app.php:11  auth.php:5   broadcasting.php:17 cache.php:18
  database.php:65 filesystems.php:9 internal.php:6 jwt.php:12
  logging.php:21 mail.php:13 modules.php:4  queue.php:20
  repository.php:0 reverb.php:33 services.php:18 session.php:15
  telegram.php:6 telescope.php:8
  ```
- `grep APP_DEBUG`, `APP_KEY`, `JWT_SECRET`, `TELESCOPE_ENABLED` over `app/`,
  `Modules/` → **0 references** (only consumed via `config/*.php`)
- `grep SESSION_` over `app/`, `Modules/`, `config/` → only `config/session.php`
- `grep BCRYPT|bcrypt|Hash::make` over project → 6 call sites (4 use
  config-aware `Hash::make`, 1 in `AdminBulkController.php` uses bare `bcrypt`)
- Per-key metadata extraction of `.env` values via PowerShell: variable name +
  length + first/last 4 chars only. **Full secret values never recorded.**

### Verification checks (per task brief)
- `.env.example` APP_DEBUG default → `false` ✓
- `.env.docker` APP_DEBUG default → `false` ✓
- `.env.docker` `SESSION_SECURE_COOKIE` explicitly set → **NOT set** ✗ (finding)
- `.env.example` `SESSION_SECURE_COOKIE` explicitly set → **NOT set** ✗ (advisory)
- Telescope production guard → present in
  `app/Providers/AppServiceProvider.php::register()` but defense-in-depth missing
  in `config/telescope.php` (finding)

---

## 1. Secrets inventory (local `.env` only — metadata, never values)

`.env` is **gitignored** (line 4 of `.gitignore`: `.env`) and `git ls-files`
confirms it is **not tracked** in git history. The following are present in the
working-copy `.env` so the auditor can verify secret-management hygiene:

| Variable | Length | First 4 | Last 4 | Sensitivity |
|----------|-------:|--------:|-------:|-------------|
| `APP_KEY` | 51 | `base` | `COQ=` | HIGH — Laravel cipher + cookie/JWT signer |
| `JWT_SECRET` | 64 | `XSPE` | `bnY7` | HIGH — signs all JWTs (tymon/jwt-auth) |
| `TELEGRAM_BOT_TOKEN` | 46 | `8697` | `IxeU` | HIGH — full bot takeover |
| `GEMINI_API_KEY` | 39 | `AIza` | `n6cs` | HIGH — paid AI service, billing risk |
| `INTERNAL_API_TOKEN` | 34 | `loca` | `3456` | MEDIUM — internal manager bearer |
| `TELEGRAM_ADMIN_CHAT_ID` | 10 | `1346` | `7388` | LOW — chat id, info disclosure only |
| `TELEGRAM_BOT_USERNAME` | 15 | `Engl` | `sBot` | LOW — public |
| `REVERB_APP_KEY` | 16 | `engl` | `y123` | LOW — public WebSocket auth id |
| `REVERB_APP_SECRET` | 19 | `engl` | `t123` | MEDIUM — WebSocket auth secret (rotatable) |
| `DB_USERNAME` | 4 | (short) | (short) | value `root` (predictable) |
| `DB_PASSWORD` | 0 | (empty) | (empty) | n/a |
| `AWS_ACCESS_KEY_ID` | 0 | (empty) | (empty) | n/a |
| `AWS_SECRET_ACCESS_KEY` | 0 | (empty) | (empty) | n/a |
| `APP_DEBUG` | 4 | (short) | (short) | value `true` (local dev only) |

> The first/last-4-char pattern is the standard "verify without exposing"
> approach: an attacker cannot reconstruct the secret from `first4 + last4`
> unless the secret is <9 chars. For local `.env` secrets <9 chars (e.g.
> `DB_USERNAME=root`), the metadata above shows the full value because
> masking would hide it; these are **not secret** (root is well-known).

---

## 2. Findings (summary)

| ID | Severity | Title | CWE | OWASP 2021 |
|----|----------|-------|-----|------------|
| SEC-001 | **High** | `SESSION_SECURE_COOKIE` not set in `.env.docker` / `.env.example` — session/CSRF cookies may be sent over HTTP in production | CWE-614 / CWE-311 | A02:2021 Cryptographic Failures |
| SEC-002 | **High** | Default Telegram webhook secret `englishclass_webhook_secret` is hardcoded in `config/telegram.php` and `.env.example` — predictable shared secret in production if env not overridden | CWE-798 / CWE-321 | A07:2021 Identification & Auth Failures |
| SEC-003 | Medium | `config/telescope.php` enables Telescope by default; production guard is only "don't register the provider in `local`" — defense-in-depth gap | CWE-489 / CWE-200 | A05:2021 Security Misconfiguration |
| SEC-004 | Medium | `VerifyInternalApiToken` uses timing-unsafe `!==` comparison for bearer token — brute-force timing side channel | CWE-208 | A02:2021 Cryptographic Failures |
| SEC-005 | Low | `INTERNAL_API_TOKEN` placeholder `your_secure_internal_token_here` in `.env.example` — weak default that could ship unchanged | CWE-798 | A05:2021 Security Misconfiguration |
| SEC-006 | Low | `.env.docker` ships `DB_PASSWORD=password` — trivial DB credential in production template | CWE-798 / CWE-521 | A05:2021 Security Misconfiguration |
| SEC-007 | Low | `AdminBulkController` uses bare `bcrypt()` instead of `Hash::make()` — ignores `BCRYPT_ROUNDS=12` env override (falls back to Laravel default 10) | CWE-916 | A02:2021 Cryptographic Failures |
| SEC-008 | Low | `VerifyInternalApiToken` returns 500 with diagnostic message when `INTERNAL_API_TOKEN` not configured — config-state disclosure | CWE-209 | A05:2021 Security Misconfiguration |
| SEC-009 | Info | Working-copy `.env` contains real secrets (`TELEGRAM_BOT_TOKEN`, `GEMINI_API_KEY`, `JWT_SECRET`, `INTERNAL_API_TOKEN`) — gitignored and not tracked, but worth a rotation reminder if the dev machine was ever shared/compromised | CWE-540 | A05:2021 Security Misconfiguration |

**No findings** in the following categories — evidence below:

- **APP_DEBUG default** — `.env.example:7` is `APP_DEBUG=false` ✓ and
  `.env.docker:5` is `APP_DEBUG=false` ✓. Both have inline SECURITY comment
  warning the operator.
- **`env()` outside `config/`** — 0 occurrences in `app/` (82 PHP files) and
  `Modules/` (291 PHP files). All env access goes through `config/*.php`,
  which is Laravel's recommended pattern (env vars are evaluated at config
  cache time).
- **APP_KEY rotation guidance** — `.env.example:10–14` has an explicit
  SECURITY comment with `php artisan key:generate --show` and 90-day cadence.
- **SecurityHeaders middleware** — present at
  `app/Http/Middleware/SecurityHeaders.php`, registered globally for both
  `web` and `api` middleware groups in `bootstrap/app.php:17–23`. Sets
  X-Frame-Options DENY, X-Content-Type-Options nosniff, Referrer-Policy
  same-origin, Permissions-Policy, and a baseline CSP (XSS mitigation).
- **AuditLogger + AuditAdminActions** — present, well-structured, and
  auto-wired via the `audit.admin` middleware alias
  (`bootstrap/app.php:29`). Filters to mutating verbs only
  (`POST/PUT/PATCH/DELETE`).
- **Telegram webhook hardening** — `VerifyTelegramSecret` uses
  `hash_equals()` (timing-safe), returns **503** in production when the
  secret is empty (no silent-bypass), and logs mismatch attempts.
- **Deploy-hook auth** — `AuthenticateDeployHook` uses `hash_equals()`
  (timing-safe) and rejects with 403 when env token is empty.
- **`.env` not committed** — `.gitignore:4` lists `.env`; `git
  check-ignore -v .env` confirmed ignored; `git ls-files --error-unmatch
  .env` confirmed not tracked.

---

## 3. Finding details

### SEC-001 — Missing SESSION_SECURE_COOKIE in production env templates

| Field | Value |
|-------|-------|
| **Severity** | **High** |
| **CWE** | CWE-614 (Sensitive Cookie Without Secure Attribute) / CWE-311 (Missing Encryption of Sensitive Data) |
| **OWASP 2021** | A02:2021 Cryptographic Failures |
| **Location** | `.env.example`, `.env.docker` (missing variable); `config/session.php:172` |
| **Authoritative reference** | `SECURITY.md:122` Production Deployment Checklist item: "`SESSION_SECURE_COOKIE=true`" |

**Evidence**:

`config/session.php:172`:
```php
'secure' => env('SESSION_SECURE_COOKIE'),
```

`.env.example` (full file, 102 lines) — no `SESSION_SECURE_COOKIE=` line:
```
39: SESSION_DRIVER=database
40: SESSION_LIFETIME=120
41: SESSION_ENCRYPT=false
42: SESSION_PATH=/
43: SESSION_DOMAIN=null
```

`.env.docker` (production template, 38 lines) — no `SESSION_SECURE_COOKIE=`:
```
22: QUEUE_CONNECTION=database
23: CACHE_STORE=redis
24: SESSION_DRIVER=redis
```

When `SESSION_SECURE_COOKIE` is unset, `env()` returns `null`. Laravel casts
`null` to `false` for the boolean `'secure'` key, so session/CSRF cookies
will be sent over plain HTTP if the user reaches the site via HTTP (e.g.
initial redirect, mixed-content scenarios, proxy misconfig).

**Attack path**:
1. Attacker performs MITM on a victim's first HTTP request (e.g. coffee-shop
   Wi-Fi forcing captive portal, hostile LAN, transparent proxy).
2. Because the cookie has no `Secure` flag, an HTTP response from the server
   will leak the session cookie in plaintext if any link/HTTPS-downgrade
   trick delivers the response.
3. Attacker captures `laravel-session=...` and replays it from their own
   browser → full session takeover (admin/teacher/student — whatever role
   the victim had).

**Remediation** (do NOT apply during audit; tracked for the fix PR):
- Add `SESSION_SECURE_COOKIE=true` to `.env.example` (commented out so
  local HTTP dev still works, but with `# SECURITY: must be true in
  production` annotation).
- Add `SESSION_SECURE_COOKIE=true` to `.env.docker` (production template —
  HTTPS is always expected when running under Cloudflare Tunnel).
- Consider adding a runtime assertion: in `AppServiceProvider::boot()` if
  `app()->environment('production') && ! config('session.secure')`,
  throw / log a critical warning.

**Code diff (proposed)**:
```diff
--- a/.env.docker
+++ b/.env.docker
@@ -22,6 +22,9 @@
 QUEUE_CONNECTION=database
 CACHE_STORE=redis
 SESSION_DRIVER=redis
+# SECURITY: Cookies must only be sent over HTTPS in production.
+# When deploying behind Cloudflare Tunnel this is required.
+SESSION_SECURE_COOKIE=true

--- a/.env.example
+++ b/.env.example
@@ -42,6 +42,9 @@
 SESSION_DOMAIN=null
+# SECURITY: Set to true in any HTTPS deployment. Leave unset (or false)
+# for local HTTP development. APP_ENV=production asserts this is true.
+# SESSION_SECURE_COOKIE=true
```

---

### SEC-002 — Default Telegram webhook secret is hardcoded

| Field | Value |
|-------|-------|
| **Severity** | **High** |
| **CWE** | CWE-798 (Use of Hard-coded Credentials) / CWE-321 (Use of Hard-coded Cryptographic Key) |
| **OWASP 2021** | A07:2021 Identification & Authentication Failures |
| **Location** | `config/telegram.php:26`, `.env.example:89`, `SECURITY.md:124` |

**Evidence**:

`config/telegram.php:26`:
```php
'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', 'englishclass_webhook_secret'),
```

`.env.example:86-91`:
```
86: # Telegram Bot
87: TELEGRAM_BOT_TOKEN=
88: TELEGRAM_ADMIN_CHAT_ID=
89: TELEGRAM_WEBHOOK_SECRET=englishclass_webhook_secret
```

`SECURITY.md:124` checklist requires a random 32+ char string.

If an operator copies `.env.example` to `.env` and forgets to override
`TELEGRAM_WEBHOOK_SECRET`, the middleware at
`Modules/TelegramBot/Http/Middleware/VerifyTelegramSecret.php` will accept
any Telegram callback with `X-Telegram-Bot-Api-Secret-Token:
englishclass_webhook_secret` — and an attacker who reads the public
GitHub README (which documents this exact value) can forge admin-approval
webhooks.

**Attack path**:
1. Attacker reads README/SECURITY.md (or this finding) to learn the
   default secret string `englishclass_webhook_secret`.
2. Crafts a Telegram-shaped callback POST to `/telegram/webhook`:
   ```http
   POST /telegram/webhook HTTP/1.1
   Host: englishclass.example.com
   X-Telegram-Bot-Api-Secret-Token: englishclass_webhook_secret
   Content-Type: application/json
   {"callback_query": {"data": "approve:USER_ID", "from": {"id": ...}}}
   ```
3. Server accepts, processes the approval, changes the target user's
   status to `active`, bypasses Telegram entirely.

**Remediation**:
- Remove the hardcoded default in `config/telegram.php:26`:
  ```php
  'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
  ```
- Change `.env.example:89` to be empty:
  ```
  TELEGRAM_WEBHOOK_SECRET=
  ```
- The existing `VerifyTelegramSecret` middleware already returns **503**
  in production when the secret is empty, which is the correct
  fail-closed behaviour.

**Code diff (proposed)**:
```diff
--- a/config/telegram.php
+++ b/config/telegram.php
@@ -23,7 +23,7 @@

     'bot_token'      => env('TELEGRAM_BOT_TOKEN'),
     'admin_chat_id'  => env('TELEGRAM_ADMIN_CHAT_ID'),
-    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', 'englishclass_webhook_secret'),
+    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
     'webhook_url'    => env('TELEGRAM_WEBHOOK_URL'),

--- a/.env.example
+++ b/.env.example
@@ -86,7 +86,7 @@
 TELEGRAM_BOT_TOKEN=
 TELEGRAM_ADMIN_CHAT_ID=
-TELEGRAM_WEBHOOK_SECRET=englishclass_webhook_secret
+TELEGRAM_WEBHOOK_SECRET=
 # Public URL Telegram POSTs to (https://.../telegram/webhook).
```

---

### SEC-003 — Telescope defaults to enabled; only protected by provider-registration guard

| Field | Value |
|-------|-------|
| **Severity** | Medium |
| **CWE** | CWE-489 (Active Debug Code) / CWE-200 (Exposure of Sensitive Information to an Unauthorized Actor) |
| **OWASP 2021** | A05:2021 Security Misconfiguration |
| **Location** | `config/telescope.php:19`, `app/Providers/AppServiceProvider.php:25-28`, `app/Providers/TelescopeServiceProvider.php:22-32` |

**Evidence**:

`config/telescope.php:19`:
```php
'enabled' => env('TELESCOPE_ENABLED', true),
```

`app/Providers/AppServiceProvider.php:25-28` (the **only** production guard):
```php
if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
    $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
    $this->app->register(\App\Providers\TelescopeServiceProvider::class);
}
```

`app/Providers/TelescopeServiceProvider.php:57-64`:
```php
protected function gate(): void
{
    Gate::define('viewTelescope', function (User $user) {
        return in_array($user->email, [
            //
        ]);
    });
}
```

The empty email list means **no one can view** Telescope (defense-in-depth
works for the UI), but the data still gets captured into the
`telescope_entries` / `telescope_monitoring` tables whenever Telescope is
registered.

**Attack path (only triggers if `APP_ENV != 'production'` AND
`class_exists(TelescopeServiceProvider::class)` is true)**:
1. Deploy with `APP_ENV=staging` (a common intermediate value) — guard at
   `AppServiceProvider:25` evaluates `false`.
2. Telescope becomes active with `enabled=true` (default), capturing every
   SQL query, request payload, and exception into the database.
3. The Telescope UI is gated by an empty email allow-list (good — no
   viewing), but the database rows still contain sensitive data
   (e.g. raw `Authorization` headers in non-local mode, per
   `hideSensitiveRequestDetails()` lines 37–50, only CSRF/cookie headers
   are stripped — `Authorization` is NOT in the strip list).
4. A DB compromise or a single SQL injection later exposes all captured
   bearer tokens / session payloads from the staging period.

Even on `APP_ENV=production`, the config-level default `enabled=true` is a
sharp edge — one accidental `class_exists` change in the future and
Telescope flips on in prod silently.

**Remediation**:
1. Flip the default in `config/telescope.php:19`:
   ```php
   'enabled' => env('TELESCOPE_ENABLED', false),
   ```
2. Add `Authorization` to `TelescopeServiceProvider::hideRequestHeaders()`
   alongside the existing cookie/CSRF strip list.
3. Belt-and-braces: assert in `AppServiceProvider::boot()` that
   `config('telescope.enabled')` is `false` when not in local.

**Code diff (proposed)**:
```diff
--- a/config/telescope.php
+++ b/config/telescope.php
@@ -16,7 +16,7 @@
     |
     */

-    'enabled' => env('TELESCOPE_ENABLED', true),
+    'enabled' => env('TELESCOPE_ENABLED', false),
```

---

### SEC-004 — VerifyInternalApiToken uses timing-unsafe `!==` comparison

| Field | Value |
|-------|-------|
| **Severity** | Medium |
| **CWE** | CWE-208 (Observable Timing Discrepancy) |
| **OWASP 2021** | A02:2021 Cryptographic Failures |
| **Location** | `Modules/InternalManager/Http/Middleware/VerifyInternalApiToken.php:32` |

**Evidence**:

`Modules/InternalManager/Http/Middleware/VerifyInternalApiToken.php:30-36`:
```php
$requestToken = substr($header, 7);
if ($requestToken !== $token) {
    return response()->json([
        'message' => 'Unauthorized. Invalid token.'
    ], 401);
}
```

Every other token-verification middleware in this repo uses
`hash_equals()` (timing-safe):
- `Modules/TelegramBot/Http/Middleware/VerifyTelegramSecret.php:38` →
  `hash_equals($expected, $provided)`
- `app/Http/Middleware/AuthenticateDeployHook.php:17` →
  `hash_equals($expectedToken, $providedToken)`

`!==` short-circuits on the first differing byte; with enough samples an
attacker can recover the token one byte at a time via timing differences.
The internal API is presumably only reachable from a trusted network
(it's the management plane), but defense-in-depth dictates consistent use
of timing-safe comparison for all shared secrets.

**Attack path** (low likelihood — internal endpoint, but feasible):
1. Attacker has LAN access to the internal-manager port (8080 per
   `.env.docker:13` `INTERNAL_PORT=8080`).
2. Crafts timing-side-channel probe with varying bearer tokens.
3. Statistically recovers the 34-char `INTERNAL_API_TOKEN` one byte at
   a time.

**Remediation**: replace `!==` with `hash_equals`.

**Code diff (proposed)**:
```diff
--- a/Modules/InternalManager/Http/Middleware/VerifyInternalApiToken.php
+++ b/Modules/InternalManager/Http/Middleware/VerifyInternalApiToken.php
@@ -29,7 +29,7 @@ public function handle(Request $request, Closure $next): Response
         }

         $requestToken = substr($header, 7);
-        if ($requestToken !== $token) {
+        if (! hash_equals($token, $requestToken)) {
             return response()->json([
                 'message' => 'Unauthorized. Invalid token.'
             ], 401);
```

---

### SEC-005 — Weak placeholder for `INTERNAL_API_TOKEN` in `.env.example`

| Field | Value |
|-------|-------|
| **Severity** | Low |
| **CWE** | CWE-798 (Use of Hard-coded Credentials) |
| **OWASP 2021** | A05:2021 Security Misconfiguration |
| **Location** | `.env.example:97` |

**Evidence**:

`.env.example:96-97`:
```
# Internal Management API
INTERNAL_API_TOKEN=your_secure_internal_token_here
```

The literal string `your_secure_internal_token_here` is a recognisable
placeholder pattern; an operator who copies the example and forgets to
override it ships the application with a publicly-known token. The
middleware at `VerifyInternalApiToken.php:18` rejects empty tokens but
will accept any non-empty value, including this placeholder.

**Remediation**: change to empty + comment.

**Code diff (proposed)**:
```diff
--- a/.env.example
+++ b/.env.example
@@ -96,7 +96,8 @@
-INTERNAL_API_TOKEN=your_secure_internal_token_here
+INTERNAL_API_TOKEN=
+# Generate with: openssl rand -base64 32
+# The InternalManager middleware returns 500 if this is empty in production.
```

---

### SEC-006 — `.env.docker` ships `DB_PASSWORD=password`

| Field | Value |
|-------|-------|
| **Severity** | Low |
| **CWE** | CWE-798 / CWE-521 (Weak Password Requirements) |
| **OWASP 2021** | A05:2021 Security Misconfiguration |
| **Location** | `.env.docker:9-14` |

**Evidence**:

`.env.docker`:
```
9:  DB_CONNECTION=mysql
10: DB_HOST=db
11: DB_PORT=3306
12: DB_DATABASE=englishclass
13: DB_USERNAME=root
14: DB_PASSWORD=password
```

A `root`/`password` MySQL credential in the production env template is a
classic low-effort compromise vector. While `SECURITY.md:126` lists
"Database credentials are NOT `root`/empty" as a deployment checklist
item, the template ships with exactly that configuration — operators
who don't override both fields ship with the trivial credentials.

**Remediation**: change to empty + comment, with explicit warning in
`SECURITY.md:126` that the template is intentionally non-functional.

**Code diff (proposed)**:
```diff
--- a/.env.docker
+++ b/.env.docker
@@ -11,7 +11,9 @@
 DB_DATABASE=englishclass
 DB_USERNAME=root
-DB_PASSWORD=password
+DB_PASSWORD=
+# SECURITY: Set a strong random password here. The "root/password"
+# default is intentionally NOT shipped; the operator must override both
+# before first `docker compose up`.
```

---

### SEC-007 — Bare `bcrypt()` ignores `BCRYPT_ROUNDS`

| Field | Value |
|-------|-------|
| **Severity** | Low |
| **CWE** | CWE-916 (Use of Password Hash With Insufficient Computational Effort) |
| **OWASP 2021** | A02:2021 Cryptographic Failures |
| **Location** | `app/Http/Controllers/AdminBulkController.php:109` |

**Evidence**:

`app/Http/Controllers/AdminBulkController.php:106-113`:
```php
User::create([
    'name' => $rowData['name'],
    'email' => $rowData['email'],
    'password' => bcrypt(\Illuminate\Support\Str::random(16)),
    'role' => 'student',
    'status' => 'pending',
    'target_band' => $rowData['target_band'] ?? null,
]);
```

Laravel's `bcrypt()` helper uses the bcrypt cost from
`config('hashing.driver')` defaults — when `BCRYPT_ROUNDS=12` is set in
`.env`, only `Hash::make()` / `Hash::driver('bcrypt')->make()` read it.
`bcrypt()` is a convenience that bypasses config lookup and falls back
to Laravel's compiled default of 10 rounds (Laravel 10+).

All other 5 password-hashing call sites in the project already use
`Hash::make()`:
- `Modules/Auth/Services/AuthService.php:32`
- `database/seeders/DatabaseSeeder.php:20,30,41`
- `database/factories/UserFactory.php:31`

So users created via CSV bulk import get weaker password hashing than
users created via normal registration.

**Attack path**:
1. Attacker exfiltrates `users` table (any DB leak / SQLi / backup
   exposure).
2. Bulk-imported accounts use 10-round bcrypt, others use 12-round.
3. Offline cracking is ~2× faster against the bulk-imported cohort.

**Remediation**: replace `bcrypt()` with `Hash::make()`.

**Code diff (proposed)**:
```diff
--- a/app/Http/Controllers/AdminBulkController.php
+++ b/app/Http/Controllers/AdminBulkController.php
@@ -106,7 +106,7 @@ public function store(BulkImportRequest $request): RedirectResponse
                 User::create([
                     'name' => $rowData['name'],
                     'email' => $rowData['email'],
-                    'password' => bcrypt(\Illuminate\Support\Str::random(16)),
+                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
                     'role' => 'student',
                     'status' => 'pending',
                     'target_band' => $rowData['target_band'] ?? null,
```

---

### SEC-008 — Internal-API middleware discloses config state via 500 message

| Field | Value |
|-------|-------|
| **Severity** | Low |
| **CWE** | CWE-209 (Generation of Error Message Containing Sensitive Information) |
| **OWASP 2021** | A05:2021 Security Misconfiguration |
| **Location** | `Modules/InternalManager/Http/Middleware/VerifyInternalApiToken.php:18-22` |

**Evidence**:

`Modules/InternalManager/Http/Middleware/VerifyInternalApiToken.php:18-22`:
```php
if (empty($token)) {
    return response()->json([
        'message' => 'Internal API token is not configured on the server.'
    ], 500);
}
```

A 500 response with a literal diagnostic message tells an attacker that
the server's `INTERNAL_API_TOKEN` env is empty. That narrows the attack
surface (no need to brute-force; the endpoint is misconfigured and
likely offline / non-functional). Not high impact on its own, but it
confirms server state during reconnaissance.

**Remediation**: return a generic 503 / 404 without server-state
detail.

**Code diff (proposed)**:
```diff
--- a/Modules/InternalManager/Http/Middleware/VerifyInternalApiToken.php
+++ b/Modules/InternalManager/Http/Middleware/VerifyInternalApiToken.php
@@ -16,8 +16,9 @@ public function handle(Request $request, Closure $next): Response
         $token = config('internal.token');

         if (empty($token)) {
+            \Illuminate\Support\Facades\Log::error('[InternalManager] INTERNAL_API_TOKEN not configured');
             return response()->json([
-                'message' => 'Internal API token is not configured on the server.'
-            ], 500);
+                'message' => 'Service unavailable.'
+            ], 503);
         }
```

---

### SEC-009 — Working-copy `.env` holds live secrets (informational)

| Field | Value |
|-------|-------|
| **Severity** | Info |
| **CWE** | CWE-540 (Inclusion of Sensitive Information in Source Code) |
| **OWASP 2021** | A05:2021 Security Misconfiguration |
| **Location** | `.env` (working copy only) |

**Evidence**: see §1 Secrets inventory. `JWT_SECRET`, `TELEGRAM_BOT_TOKEN`,
`GEMINI_API_KEY`, `INTERNAL_API_TOKEN`, `APP_KEY` are populated in the
working-copy `.env`.

`.env` is **gitignored** (`.gitignore:4`) and `git ls-files --error-unmatch
.env` confirms it is not tracked in the repository. No code-level
remediation required — this is an informational note only.

**Operational recommendation** (not part of code audit):
- Treat the development workstation as compromised for these secrets if
  it was ever shared or cloned onto a multi-tenant machine.
- Rotate the four secrets listed in `SECURITY.md:140-145` per the
  published cadence (90 days for `APP_KEY` and `JWT_SECRET`, 180 days
  for `TELEGRAM_WEBHOOK_SECRET` and `GEMINI_API_KEY`).
- Use `php artisan key:generate --show` and `APP_PREVIOUS_KEYS` to
  preserve encrypted data through `APP_KEY` rotation.

---

## 4. env() / config() call inventory

### Per requirement: full inventory of `env(` usage

| File | Line | Call | Notes |
|------|-----:|------|-------|
| `config/app.php` | 16 | `env('APP_NAME', 'Laravel')` | Safe default |
| `config/app.php` | 29 | `env('APP_ENV', 'production')` | Safe default |
| `config/app.php` | 42 | `(bool) env('APP_DEBUG', false)` | Safe default ✓ |
| `config/app.php` | 55 | `env('APP_URL', 'http://localhost')` | Safe default |
| `config/app.php` | 81 | `env('APP_LOCALE', 'en')` | Safe default |
| `config/app.php` | 83 | `env('APP_FALLBACK_LOCALE', 'en')` | Safe default |
| `config/app.php` | 85 | `env('APP_FAKER_LOCALE', 'en_US')` | Safe default |
| `config/app.php` | 100 | `env('APP_KEY')` | No default — required |
| `config/app.php` | 104 | `(string) env('APP_PREVIOUS_KEYS', '')` | Safe default |
| `config/app.php` | 122 | `env('APP_MAINTENANCE_DRIVER', 'file')` | Safe default |
| `config/app.php` | 123 | `env('APP_MAINTENANCE_STORE', 'database')` | Safe default |
| `config/jwt.php` | 18 | `env('JWT_SECRET')` | No default — required |
| `config/jwt.php` | 49 | `env('JWT_PUBLIC_KEY')` | No default |
| `config/jwt.php` | 62 | `env('JWT_PRIVATE_KEY')` | No default |
| `config/jwt.php` | 73 | `env('JWT_PASSPHRASE')` | No default |
| `config/jwt.php` | 92 | `(int) env('JWT_TTL', 60)` | Safe default |
| `config/jwt.php` | 120 | `env('JWT_REFRESH_IAT', false)` | Safe default |
| `config/jwt.php` | 121 | `(int) env('JWT_REFRESH_TTL', 20160)` | Safe default |
| `config/jwt.php` | 135 | `env('JWT_ALGO', 'HS256')` | Safe default |
| `config/jwt.php` | 209 | `(int) env('JWT_LEEWAY', 0)` | Safe default |
| `config/jwt.php` | 221 | `env('JWT_BLACKLIST_ENABLED', true)` | Safe default |
| `config/jwt.php` | 236 | `(int) env('JWT_BLACKLIST_GRACE_PERIOD', 0)` | Safe default |
| `config/jwt.php` | 247 | `env('JWT_SHOW_BLACKLIST_EXCEPTION', true)` | Safe default |
| `config/session.php` | 21 | `env('SESSION_DRIVER', 'database')` | Safe default |
| `config/session.php` | 35 | `(int) env('SESSION_LIFETIME', 120)` | Safe default |
| `config/session.php` | 37 | `env('SESSION_EXPIRE_ON_CLOSE', false)` | Safe default |
| `config/session.php` | 50 | `env('SESSION_ENCRYPT', false)` | Safe default |
| `config/session.php` | 76 | `env('SESSION_CONNECTION')` | No default |
| `config/session.php` | 89 | `env('SESSION_TABLE', 'sessions')` | Safe default |
| `config/session.php` | 104 | `env('SESSION_STORE')` | No default |
| `config/session.php` | 130-133 | `env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'laravel')).'-session')` | Nested env — app code uses config, this is the only nesting site |
| `config/session.php` | 146 | `env('SESSION_PATH', '/')` | Safe default |
| `config/session.php` | 159 | `env('SESSION_DOMAIN')` | No default |
| `config/session.php` | **172** | **`env('SESSION_SECURE_COOKIE')`** | **No default — null cast = false → SEC-001** |
| `config/session.php` | 185 | `env('SESSION_HTTP_ONLY', true)` | Safe default ✓ |
| `config/session.php` | 202 | `env('SESSION_SAME_SITE', 'lax')` | Safe default |
| `config/session.php` | 215 | `env('SESSION_PARTITIONED_COOKIE', false)` | Safe default |
| `config/telegram.php` | 24 | `env('TELEGRAM_BOT_TOKEN')` | No default |
| `config/telegram.php` | 25 | `env('TELEGRAM_ADMIN_CHAT_ID')` | No default |
| `config/telegram.php` | **26** | **`env('TELEGRAM_WEBHOOK_SECRET', 'englishclass_webhook_secret')`** | **Hardcoded fallback → SEC-002** |
| `config/telegram.php` | 27 | `env('TELEGRAM_WEBHOOK_URL')` | No default |
| `config/telegram.php` | 28 | `env('TELEGRAM_BOT_USERNAME', 'EnglishClassBot')` | Safe default |
| `config/telegram.php` | 29 | `env('TELEGRAM_BASE_URL', 'https://api.telegram.org/bot')` | Safe default |
| `config/internal.php` | 4 | `env('INTERNAL_API_TOKEN')` | No default |
| `config/internal.php` | 5 | `env('INTERNAL_PORT', 8080)` | Safe default |
| `config/internal.php` | 6 | `env('DOCKER_CONTAINER', 'english-class-app')` | Safe default |
| `config/internal.php` | 7 | `env('CENTRAL_MANAGER_URL')` | No default |
| `config/internal.php` | 8 | `env('CENTRAL_MANAGER_REGISTER_URL', env('CENTRAL_MANAGER_URL') ? env('CENTRAL_MANAGER_URL').'/api/apps/register' : null)` | Triple nested env — fragile but works |
| `config/internal.php` | 9 | `env('INTERNAL_API_VERSION', '1.0.0')` | Safe default |
| `config/telescope.php` | **19** | **`env('TELESCOPE_ENABLED', true)`** | **Insecure default → SEC-003** |
| `config/telescope.php` | 32 | `env('TELESCOPE_DOMAIN')` | No default |
| `config/telescope.php` | 45 | `env('TELESCOPE_PATH', 'telescope')` | Safe default |
| `config/telescope.php` | 58 | `env('TELESCOPE_DRIVER', 'database')` | Safe default |
| `config/telescope.php` | 62 | `env('DB_CONNECTION', 'mysql')` | Safe default |
| `config/telescope.php` | 79 | `env('TELESCOPE_QUEUE_CONNECTION')` | No default |
| `config/telescope.php` | 80 | `env('TELESCOPE_QUEUE')` | No default |
| `config/telescope.php` | 81 | `env('TELESCOPE_QUEUE_DELAY', 10)` | Safe default |
| `config/services.php` | 39-44 | `env('GEMINI_API_KEY')`, `env('GEMINI_API_KEYS')`, `env('GEMINI_MODEL', 'gemini-2.5-flash-lite')`, `env('GEMINI_FALLBACK_MODEL', 'gemini-2.5-flash')`, `env('GEMINI_FALLBACK_MODELS')` | All safe defaults |
| `config/services.php` | 52-54 | `env('TELEGRAM_BOT_TOKEN')`, `env('TELEGRAM_ADMIN_CHAT_ID')`, `env('TELEGRAM_BASE_URL', 'https://api.telegram.org/bot')` | Safe defaults |
| `config/services.php` | 58 | `env('DEPLOY_NOTIFY_TOKEN')` | No default — checked in `AuthenticateDeployHook` |

> Other `env(` calls in `config/auth.php`, `cache.php`, `database.php`,
> `broadcasting.php`, `filesystems.php`, `logging.php`, `mail.php`,
> `modules.php`, `queue.php`, `reverb.php` are stock Laravel defaults and
> not security-sensitive in the context of this audit (verified by
> reading each file in full).

### Variable-by-variable safety assessment

| Variable | Type | Default safety | Validation | Sensitivity |
|----------|------|---------------|------------|-------------|
| `APP_NAME` | string | ✓ `'Laravel'` | none | low |
| `APP_ENV` | string | ✓ `'production'` | none | low (drive other defaults) |
| `APP_DEBUG` | bool | ✓ `false` | none | **HIGH if true in prod** |
| `APP_URL` | string | ✓ `'http://localhost'` | none | low |
| `APP_KEY` | string | **none — required** | none | **HIGH** |
| `APP_PREVIOUS_KEYS` | string | ✓ `''` | none | HIGH (decrypts old data) |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` / `APP_FAKER_LOCALE` | string | ✓ sensible | none | low |
| `APP_MAINTENANCE_DRIVER` / `APP_MAINTENANCE_STORE` | string | ✓ `'file'` / `'database'` | none | low |
| `BCRYPT_ROUNDS` | int | Laravel default 10 | none | medium (see SEC-007) |
| `LOG_CHANNEL` / `LOG_STACK` / `LOG_LEVEL` | string | ✓ stock | none | low |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | string | ⚠ `root` / `''` in `config/database.php:53-54` | none | **HIGH if shipped (SEC-006)** |
| `REDIS_PASSWORD` | string | none | none | medium |
| `SESSION_DRIVER` / `SESSION_LIFETIME` / `SESSION_ENCRYPT` | string | ✓ stock | none | low |
| `SESSION_SECURE_COOKIE` | bool | **none — null → false** | none | **HIGH if shipped without true (SEC-001)** |
| `SESSION_HTTP_ONLY` | bool | ✓ `true` | none | low |
| `SESSION_SAME_SITE` | string | ✓ `'lax'` | none | low |
| `SESSION_DOMAIN` / `SESSION_PATH` / `SESSION_COOKIE` / `SESSION_CONNECTION` / `SESSION_TABLE` / `SESSION_STORE` / `SESSION_PARTITIONED_COOKIE` | string | varies | none | low |
| `JWT_SECRET` | string | **none — required** | none | **HIGH** |
| `JWT_ALGO` | string | ✓ `'HS256'` | none | medium (controls signer) |
| `JWT_TTL` / `JWT_REFRESH_TTL` / `JWT_LEEWAY` | int | ✓ 60 / 20160 / 0 | none | low |
| `JWT_BLACKLIST_ENABLED` / `JWT_SHOW_BLACKLIST_EXCEPTION` | bool | ✓ true | none | medium |
| `TELEGRAM_BOT_TOKEN` | string | **none — required** | none | **HIGH** |
| `TELEGRAM_ADMIN_CHAT_ID` | int | **none** | none | low (info disclosure) |
| `TELEGRAM_WEBHOOK_SECRET` | string | ⚠ hardcoded fallback `'englishclass_webhook_secret'` | none | **HIGH if shipped (SEC-002)** |
| `TELEGRAM_WEBHOOK_URL` / `TELEGRAM_BOT_USERNAME` | string | none / ✓ `'EnglishClassBot'` | none | low |
| `TELEGRAM_BASE_URL` | string | ✓ `'https://api.telegram.org/bot'` | none | low |
| `GEMINI_API_KEY` / `GEMINI_API_KEYS` | string | none | none | **HIGH** |
| `GEMINI_MODEL` / `GEMINI_FALLBACK_MODEL` / `GEMINI_FALLBACK_MODELS` | string | ✓ stock | none | low |
| `INTERNAL_API_TOKEN` | string | none — placeholder in `.env.example:97` | none | **MEDIUM (SEC-005)** |
| `INTERNAL_PORT` / `DOCKER_CONTAINER` / `CENTRAL_MANAGER_URL` / `CENTRAL_MANAGER_REGISTER_URL` / `INTERNAL_API_VERSION` | string/int | ✓ stock | none | low |
| `TELESCOPE_ENABLED` | bool | ⚠ `true` | none | **MEDIUM (SEC-003)** |
| `TELESCOPE_DOMAIN` / `TELESCOPE_PATH` / `TELESCOPE_DRIVER` / `TELESCOPE_QUEUE_CONNECTION` / `TELESCOPE_QUEUE` / `TELESCOPE_QUEUE_DELAY` | string/int | ✓ stock | none | low |
| `DEPLOY_NOTIFY_TOKEN` | string | none — rejected if empty by middleware | none | medium |
| `VAPOR_MAINTENANCE_MODE` | bool | ✓ `false` | none | low |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` / `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` | string | varied (see `.env.docker:26-32`) | none | medium (WebSocket auth) |

---

## 5. Residual risk / out-of-scope

The following items were **not** in the C1 scope but are worth flagging
for downstream tasks:

- **TLS / HSTS / certificate pinning** — not enforced by the application
  code; `AppServiceProvider::boot()` only calls
  `URL::forceScheme('https')` when behind a proxy. Belongs in
  C4 (Transport Security).
- **CSP nonce support** — `SecurityHeaders::$csp` uses `'unsafe-inline'`
  and `'unsafe-eval'` to accommodate Vite dev. Production should use
  nonces (already flagged in `SECURITY.md:173` "Out of Scope").
- **PII at rest encryption** — `users.email`, `users.name` not encrypted
  (already flagged in `SECURITY.md:170`).
- **MFA for admin/teacher** — not implemented (already flagged in
  `SECURITY.md:169`).
- **Composer dependency audit** — `composer audit` was not run in this
  audit (read-only, no network). Belongs in C6 (Supply Chain).
- **Repository secret scanning** — local `.env` not scanned for
  committed secrets in git history; the audit verified only the
  working-copy state. Belongs in C1 follow-up.

---

## 6. Audit metadata

- **Files modified during audit**: 0
- **Secrets rotated**: 0
- **Full secret values written to evidence file**: 0 (only length +
  first/last 4 chars)
- **Tests run**: 0 (read-only audit)
- **Tools used**: Read, Grep, Glob, Bash (PowerShell 5.1) — no
  external subagents invoked; direct file inspection kept context
  bounded and prevented accidental secrets-in-subagent-context.
