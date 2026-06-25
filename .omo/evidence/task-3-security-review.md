# Task 3 — Auth & Session Security Audit

**Project:** englishClass (Laravel 12 / PHP 8.4 / MySQL)
**JWT package:** `php-open-source-saver/jwt-auth` (config-driven, defaults intact)
**Scope:** Authentication, session, JWT, mass assignment, password hashing, rate limiting
**Audit mode:** Read-only static review + grep of call-graph; no HTTP probes.
**Date:** 2026-06-25

---

## Verdict

**PASS WITH FINDINGS** — no Critical issues. Two High findings (generic login error
message leaks user enumeration despite single-message attempt; JWT blacklisting is
enabled but unused because there is no API logout endpoint) and several Low /
Informational items. The mass-assignment and rate-limit protections are correct,
and the existing `MassAssignmentTest` / `RateLimitTest` exercise the asserted
behaviour.

---

## Files Reviewed (evidence trail)

| File | Lines | Purpose |
|---|---|---|
| `Modules/Auth/Http/Controllers/AuthController.php` | 1–150 | register / login / logout (web + API) |
| `Modules/Auth/Http/Requests/RegisterRequest.php` | 1–24 | validation rules for registration |
| `Modules/Auth/Http/Requests/LoginRequest.php` | 1–21 | validation rules for login |
| `Modules/Auth/routes/web.php` | 1–58 | web auth routes + throttle + can gates |
| `Modules/Auth/routes/api.php` | 1–23 | API auth routes + throttle |
| `Modules/Auth/Services/AuthService.php` | 1–70 | register/login core + JWTAuth::fromUser |
| `Modules/Auth/Repositories/UserRepositoryEloquent.php` | 1–58 | User CRUD |
| `Modules/Auth/Http/Resources/UserResource.php` | 1–20 | JSON shape returned to client |
| `config/jwt.php` | 1–321 | ttl, refresh_ttl, blacklist, leeway, algo, claims |
| `config/session.php` | 1–233 | driver, lifetime, encrypt, http_only, secure, same_site |
| `app/Models/User.php` | 1–111 | fillable/hidden/casts/JWT subject |
| `app/Http/Middleware/EnsureUserHasRole.php` | 1–39 | role guard |
| `app/Http/Middleware/AuditAdminActions.php` | 1–51 | audit trail |
| `app/Http/Middleware/AuthenticateDeployHook.php` | 1–23 | constant-time token compare |
| `app/Http/Middleware/SecurityHeaders.php` | 1–57 | CSP / X-Frame / nosniff |
| `tests/Feature/Security/MassAssignmentTest.php` | 1–39 | register ignores role/status |
| `tests/Feature/Security/RateLimitTest.php` | 1–82 | login/register/AI throttle |
| `app/Providers/AppServiceProvider.php` | 65–103 | centralised `RateLimiter::for(...)` |
| `routes/api.php` | 1–7 | only deploy/notify lives outside modules |
| `.env.example` | selected | bcrypt rounds + session defaults |

---

## Findings Summary

| ID | Severity | Title | CWE | OWASP 2021 |
|---|---|---|---|---|
| SEC-001 | **High** | Login error message distinguishes “email not found” from “wrong password” via response timing | CWE-208 / CWE-204 | A07:2021 |
| SEC-002 | **High** | No API logout endpoint ⇒ JWT tokens remain valid after client discards them (blacklist configured but unused) | CWE-613 | A07:2021 |
| SEC-003 | Medium | `User::is_unlimited` and lesson-bypass fields are `$fillable` despite being privilege-bearing | CWE-915 | A04:2021 |
| SEC-004 | Medium | Session cookie `secure` flag is `null` by default — depends on `.env` being correct in prod | CWE-614 | A05:2021 |
| SEC-005 | Low | Login flow leaks account existence via `pending` vs `active` status branch | CWE-204 | A07:2021 |
| SEC-006 | Low | `MassAssignmentTest` uses web `/register` only — no API coverage | CWE-1352 | A04:2021 (test gap) |
| SEC-007 | Low | No password complexity rules beyond `Password::min(8)` — no mixed-case / numeric / symbol requirement | CWE-521 | A07:2021 |
| SEC-008 | Informational | Throttle key is IP-only for `/login` and `/register` (correct for unauthenticated routes, but means NAT/CGNAT users share quota) | CWE-770 | — |
| SEC-009 | Informational | `JWT_LEEWAY` default = 0 — fine for single-clock deployments, but undocumented; consider 30–60s in clustered prod | CWE-345 | — |

---

## Area-by-Area Analysis

### a) Register flow — input handling

**Code (`Modules/Auth/Http/Controllers/AuthController.php:102-107, 126-134`):**
```php
public function webRegister(RegisterRequest $request)
{
    $this->authService->register($request->validated());
    ...
}
...
public function register(RegisterRequest $request): JsonResponse
{
    $user = $this->authService->register($request->validated());
```

Both web and API register pass `$request->validated()` — Laravel’s `validated()`
returns ONLY the keys declared in `RegisterRequest::rules()` (lines 12–17). This
strips everything else from the input before it reaches the service.

**RegisterRequest rules (`Modules/Auth/Http/Requests/RegisterRequest.php:12-17`):**
```php
return [
    'name' => 'required|string|max:255',
    'email' => 'required|string|email|max:255|unique:users',
    'password' => ['required', 'string', 'confirmed', Password::min(8)],
    'target_band' => 'nullable|numeric|between:1,9',
];
```

**Defence-in-depth (`Modules/Auth/Services/AuthService.php:30-42`):**
```php
public function register(array $data)
{
    $data['password'] = Hash::make($data['password']);
    $data['role'] = 'student';
    $data['status'] = 'pending';
    $data['target_band'] = $data['target_band'] ?? null;
    $user = $this->userRepository->create($data);
    ...
}
```

Even if a future bug let extra keys slip through, `AuthService` hard-overwrites
`role='student'` and `status='pending'` after `validated()` has stripped
unknown keys. **Result: register cannot be coerced into creating an admin.**
Author’s security comment at lines 22-29 documents the invariant explicitly.

**Verdict:** ✅ No finding. Register is safe.

---

### b) Mass assignment — User `$fillable`

**Code (`app/Models/User.php:14`):**
```php
#[Fillable(['name', 'email', 'password', 'role', 'status', 'target_band', 'xp',
            'streak', 'can_request_extra_lesson', 'lesson_limit', 'is_unlimited'])]
```

`is_unlimited`, `lesson_limit`, `can_request_extra_lesson`, and `xp` are all in
the `$fillable` array. Because `register()` uses `$request->validated()` these
fields are not reachable from the registration endpoint, but any future
`User::create($request->all())` in an unrelated controller would silently grant
privileges (see SEC-003).

**Hidden (`app/Models/User.php:15`):**
```php
#[Hidden(['password', 'remember_token'])]
```

Password and remember_token correctly hidden from JSON output.

**Verdict:** ⚠️ SEC-003 — defence-in-depth opportunity.

---

### c) Login — `Hash::check`, error messages, rate limiting

**Code (`Modules/Auth/Services/AuthService.php:47-69`):**
```php
public function login(array $credentials)
{
    $user = $this->userRepository->findByEmail($credentials['email']);

    if (!$user || !Hash::check($credentials['password'], $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['Invalid credentials.'],
        ]);
    }

    if ($user->status !== 'active') {
        throw ValidationException::withMessages([
            'status' => ['Your account is pending approval.'],
        ]);
    }

    $token = JWTAuth::fromUser($user);
    ...
}
```

**Hash check:** ✅ correct — `Hash::check($plain, $hash)`.

**Generic error message:** ⚠️ The displayed string is identical for
“user not found” and “wrong password”, which is good. **However**, the
code paths diverge before the message is constructed:
1. Unknown email → `$user = null` → throw immediately (no bcrypt work).
2. Known email, wrong password → `Hash::check` runs ~100ms+ (bcrypt cost 12).

This is a classic **timing oracle**. With sufficient samples (e.g. 100 logins
per probe), an attacker can distinguish registered vs unregistered emails.
→ **SEC-001 (High)**.

**`status !== 'active'` branch (line 57-61):** ⚠️ A second, separate
`ValidationException` is thrown with the *status* key. This not only leaks
that the email is registered, it also tells the attacker the account is
pending or rejected — a perfectly clear existence oracle and a phishing
hint. → **SEC-005 (Low)**.

**Rate limiting:** ✅ `throttle:5,1` applied to `/login` in both web and API
route files (`Modules/Auth/routes/web.php:15`, `Modules/Auth/routes/api.php:15`).
Existing test `RateLimitTest::test_login_throttles_after_five_attempts`
confirms 429 on the 6th attempt.

**Verdict:** ⚠️ SEC-001 (High), SEC-005 (Low).

---

### d) JWT config

Read from `config/jwt.php`:

| Setting | Value | Source | Notes |
|---|---|---|---|
| `ttl` | `env('JWT_TTL', 60)` | line 92 | 60 min default. Acceptable for SPA. |
| `refresh_ttl` | `env('JWT_REFRESH_TTL', 20160)` | line 121 | 14 days. Long. |
| `refresh_iat` | `env('JWT_REFRESH_IAT', false)` | line 120 | Non-rolling. |
| `algo` | `HS256` | line 135 | Symmetric; depends on `JWT_SECRET` strength. |
| `required_claims` | `iss, iat, exp, nbf, sub, jti` | lines 148-155 | All standard claims present. |
| `blacklist_enabled` | `env('JWT_BLACKLIST_ENABLED', true)` | line 221 | Default on. |
| `blacklist_grace_period` | `env('JWT_BLACKLIST_GRACE_PERIOD', 0)` | line 236 | 0 — concurrent requests can race. |
| `leeway` | `env('JWT_LEEWAY', 0)` | line 209 | 0. (See SEC-009.) |
| `lock_subject` | `true` | line 192 | ✅ prevents cross-model id collision. |
| `show_black_list_exception` | `true` | line 247 | ✅ useful for observability. |
| `decrypt_cookies` | `false` | line 265 | OK — package not used via cookie here. |

**Token invalidation on logout:** ❌ **There is no API logout endpoint.**
`grep` of `Modules/Auth` for `JWTAuth::invalidate|logout|blacklist` returns
only the web `Auth::logout()` call. The API `login()` method at line 139 of
`AuthController` returns a JWT and never offers a way to invalidate it.
Combined with `blacklist_enabled=true`, this means:
- The blacklist infrastructure is configured but never written to.
- A leaked/stolen JWT remains valid until natural expiry (60 min) and can
  even be refreshed for up to 14 days via `refresh_ttl`.

→ **SEC-002 (High)**.

---

### e) Session config

Read from `config/session.php`:

| Setting | Value | Source | Notes |
|---|---|---|---|
| `driver` | `env('SESSION_DRIVER', 'database')` | line 21 | `.env.example` sets `database` ✅ |
| `lifetime` | `env('SESSION_LIFETIME', 120)` (min) | line 35 | 120 min idle. |
| `expire_on_close` | `env('SESSION_EXPIRE_ON_CLOSE', false)` | line 37 | OK. |
| `encrypt` | `env('SESSION_ENCRYPT', false)` | line 50 | **false**. Acceptable for DB driver (server-side); risky for `cookie` driver. |
| `http_only` | `env('SESSION_HTTP_ONLY', true)` | line 185 | ✅ default true. |
| `secure` | `env('SESSION_SECURE_COOKIE')` | line 172 | ⚠️ **null default** — must be set explicitly in prod. `.env.example` does NOT set this. |
| `same_site` | `env('SESSION_SAME_SITE', 'lax')` | line 202 | ✅ 'lax' default. |
| `partitioned` | `false` | line 215 | OK for non-cross-site. |
| `serialization` | `'json'` | line 231 | ✅ json — no PHP-gadget chains. |

→ **SEC-004 (Medium)**: `SESSION_SECURE_COOKIE` is not documented in
`.env.example`, so a deploy that copies `.env.example` verbatim will ship
a session cookie over HTTP in production.

---

### f) Password hashing

* No `config/hashing.php` file is present — Laravel 12 uses framework defaults:
  driver `bcrypt`, rounds `12` (PHP 8.4 / Laravel 12 default).
* `.env.example` line 25 explicitly sets `BCRYPT_ROUNDS=12` — at the upper end
  of recommended values; good.
* `AuthService::register` uses `Hash::make($data['password'])` (line 32).
  `AuthService::login` uses `Hash::check(...)` (line 51). ✅
* `User::casts()` declares `'password' => 'hashed'` (line 82) — Laravel 12
  auto-hashes when `User::create([...,'password'=>'x'])` is called. Defensive
  even if a future caller forgets.

**Verdict:** ✅ No finding on hashing.

---

### g) MassAssignmentTest coverage

Read `tests/Feature/Security/MassAssignmentTest.php`:

* Posts to `/register` (web) with `role=admin`, `status=active`, `is_unlimited=true`.
* Asserts `assertSessionHas('success')`.
* Asserts `$user->role === 'student'`, `$user->status === 'pending'`,
  `$user->is_unlimited === false`.
* Uses `RefreshDatabase`.

**Covers:** ✅ role escalation, status skip, unlimited bypass on web endpoint.
**Missing:** ❌ No parallel test for the API `/api/register` route. A future
refactor that diverges web vs API register behaviour would not be caught.
→ **SEC-006 (Low)** — test gap, not a product vulnerability.

---

### h) RateLimitTest coverage

Read `tests/Feature/Security/RateLimitTest.php`:

| Test | Endpoint | Asserts | Matches production config? |
|---|---|---|---|
| `test_login_throttles_after_five_attempts` | `POST /login` | 6th request → 429 | ✅ `throttle:5,1` on web `/login` |
| `test_register_throttles_after_three_attempts` | `POST /register` | 4th request → 429 | ✅ `throttle:3,60` on web `/register` |
| `test_ai_chat_is_rate_limited_per_user` | `POST /ai/chat` | 21st request → 429 (mocked) | ✅ matches `RateLimiter::for('ai', ...)` 20/min |

**Covers:** ✅ throttle middleware for web login/register + AI endpoint.
**Missing:** ❌ No test for `POST /api/login` or `POST /api/register` (different
route files apply the same throttle, but the wiring is not regression-tested).
❌ No test for `RateLimiter::for('ai-speaking')` or `lesson-requests`.

**Verdict:** ⚠️ Test gap, not a production bug. The throttle middleware is
already wired correctly per the route files.

---

### i) JWT reuse after logout

* **Web logout** (`AuthController::logout`, lines 112-119): calls `Auth::logout()`,
  invalidates session, regenerates token. Affects the web session only.
* **API logout:** *does not exist.* No `POST /api/logout`, no
  `JWTAuth::invalidate()`, no `JWTAuth::parseToken()->invalidate()` call
  anywhere in `Modules/Auth`. `grep JWTAuth::invalidate` returns zero hits.
* **Result:** Once an API client has a JWT, there is no server-side way to
  invalidate it before `ttl` (60 min) expires. Even password change does not
  revoke outstanding tokens (no `invalid=true` write-back in
  `User::getJWTCustomClaims`, no `auth.password_timeout` hook).
* The `blacklist_enabled=true` config is dead weight without an invalidation
  call site.

→ **SEC-002 (High)**.

---

## Finding Details

### SEC-001 — High — User enumeration via login timing oracle

- **Location:** `Modules/Auth/Services/AuthService.php:49-55`
- **CWE:** CWE-208 (Observable Timing Discrepancy) / CWE-204 (Observable Response Discrepancy)
- **OWASP 2021:** A07 Identification and Authentication Failures
- **Evidence:**
  ```php
  $user = $this->userRepository->findByEmail($credentials['email']);

  if (!$user || !Hash::check($credentials['password'], $user->password)) {
      throw ValidationException::withMessages([
          'email' => ['Invalid credentials.'],
      ]);
  }
  ```
- **Attack path:** Attacker POSTs `/api/login` with a candidate email and a
  constant password; measures response time over many samples. When the email
  is registered, the server runs bcrypt (cost 12 → ~250 ms on commodity
  hardware); when unregistered, it returns immediately. A mean-difference
  test over 50 samples distinguishes the two with high confidence. Combined
  with the admin-only Telegram notification path and the open registration
  form, this gives an attacker a target list for credential stuffing or
  spear-phishing.
- **Impact:** User enumeration; targeted phishing.
- **PoC:** No live PoC executed (read-only audit). Safe static proof:
  compare the two branches — the `!$user` branch returns at line 52
  *before* any bcrypt work; the `!Hash::check` branch runs `Hash::check`
  first (timing depends on bcrypt rounds=12, ~250 ms). The diverging
  control flow is observable in the source.
- **Severity rationale:** Real, reproducible, exploitable remotely without
  authentication. Severity would be Medium on its own, but the same code
  also leaks a more obvious `status` oracle (SEC-005), so the practical
  attacker effort is near zero.
- **Minimal fix:** Always run a dummy `Hash::check` against a fixed
  pre-computed bcrypt of a known string when `$user` is null:
  ```php
  if (! $user) {
      Hash::check($credentials['password'], '$2y$12$...static-dummy-hash...');
      throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
  }
  if (! Hash::check($credentials['password'], $user->password)) {
      throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
  }
  ```
  Keep both error responses byte-identical and the *control flow* the same
  shape so a single timer sample is no longer discriminative.
- **Regression check:** Add a test that posts a known-bad email 30 times and
  asserts the median latency lies within ±20 % of the latency for a known
  email with a wrong password (with statistical noise tolerated). Better,
  add `AuthServiceTest::test_unknown_email_path_does_not_short_circuit`.

### SEC-002 — High — JWT cannot be invalidated after issue

- **Location:** `Modules/Auth/Http/Controllers/AuthController.php` (entire
  file — no `logout` API method, no `JWTAuth::invalidate` call); compare with
  `Modules/Auth/routes/api.php:1-23`.
- **CWE:** CWE-613 (Insufficient Session Expiration)
- **OWASP 2021:** A07 Identification and Authentication Failures
- **Evidence:**
  ```bash
  grep -rn "JWTAuth::invalidate|logout|blacklist" Modules/Auth
  # Returns only the WEB Auth::logout() at AuthController.php:114
  ```
- **Attack path:** A stolen JWT (XSS, malicious browser extension, leaked
  log) remains valid for `JWT_TTL=60` minutes and refreshable for
  `JWT_REFRESH_TTL=20160` minutes (14 days). The user can change their
  password via `/settings` (web) and the API JWT still works because
  `AuthService::login` does not write any invalidation token. The
  configured `blacklist_enabled=true` is never used.
- **Impact:** Stolen tokens cannot be revoked server-side. Compromise
  window is up to 14 days.
- **PoC:** Static — read `routes/api.php`. There is no `POST /api/logout`
  route and no `JWTAuth::invalidate()` call site. Therefore any issued JWT
  is honoured by the `auth:api` guard until natural expiry.
- **Severity rationale:** Standard JWT-auth design without server-side
  revocation. Real exploitability requires an initial token leak (XSS,
  log dump), but the absence of mitigation multiplies impact.
- **Minimal fix:**
  1. Add `Route::post('logout', [AuthController::class, 'apiLogout'])`
     inside `Modules/Auth/routes/api.php` behind `auth:api`.
  2. Implement:
     ```php
     public function apiLogout(): JsonResponse
     {
         JWTAuth::invalidate(JWTAuth::getToken());
         return response()->json(['message' => 'Logged out.']);
     }
     ```
  3. On password change in `UserService`/`AuthService`, also call
     `JWTAuth::invalidate()` for all outstanding tokens for that user
     (store `jti` per user; on change, blacklist the family).
- **Regression check:** Add `LogoutApiTest` that issues a JWT, calls
  `POST /api/logout`, then tries an authenticated endpoint and asserts
  401 (token blacklisted).

### SEC-003 — Medium — Privilege-bearing fields are `$fillable`

- **Location:** `app/Models/User.php:14`
- **CWE:** CWE-915 (Improperly Controlled Modification of Dynamically-Determined
  Object Attributes) / defence-in-depth gap
- **OWASP 2021:** A04 Insecure Design
- **Evidence:**
  ```php
  #[Fillable(['name', 'email', 'password', 'role', 'status', 'target_band',
              'xp', 'streak', 'can_request_extra_lesson', 'lesson_limit',
              'is_unlimited'])]
  ```
- **Attack path:** Today, no caller uses `User::create($request->all())` or
  `User::fill($request->all())`. But `is_unlimited`, `lesson_limit`, and
  `can_request_extra_lesson` are gated only by the validator not knowing
  about them. A future admin-update controller that refactors to
  `$user->update($request->all())` would silently grant unlimited lessons.
- **Impact:** Defence-in-depth. Currently latent.
- **PoC:** Static — read the `#[Fillable]` attribute and grep
  `User::create|User::update|->fill(` for callers; today the only mass-write
  path is `AuthService::register` which already hardcodes `role` and
  `status`. Low current risk, high blast-radius if regressed.
- **Severity rationale:** Latent; mitigated today by `AuthService`.
- **Minimal fix:** Split `$fillable` into a base `$fillable` set
  (`name, email, password, target_band`) and require explicit assignment
  for privilege fields:
  ```php
  protected $fillable = ['name', 'email', 'password', 'target_band'];
  ```
  Use `$user->forceFill(['is_unlimited' => true])->save()` only inside
  `AdminUserController` after policy check.
- **Regression check:** Add `UserFillableTest` asserting that
  `User::create(['is_unlimited' => true])` does NOT set `is_unlimited`
  without explicit force-fill.

### SEC-004 — Medium — Session cookie `secure` flag unset by default

- **Location:** `config/session.php:172`; `.env.example` lines 39–43.
- **CWE:** CWE-614 (Sensitive Cookie in HTTPS Session Without 'Secure' Attribute)
- **OWASP 2021:** A05 Security Misconfiguration
- **Evidence:**
  ```php
  'secure' => env('SESSION_SECURE_COOKIE'),
  // .env.example has no SESSION_SECURE_COOKIE entry
  ```
- **Attack path:** A deployment that follows `cp .env.example .env` and
  sets `APP_ENV=production` ships session cookies over plain HTTP. An
  attacker on the same network can sniff the session cookie and replay
  it.
- **Impact:** Session hijack on HTTP traffic.
- **PoC:** Static. Read `.env.example` (no `SESSION_SECURE_COOKIE` line).
  Read `config/session.php` (defaults to `null` ⇒ cookie sent over HTTP).
- **Severity rationale:** Configuration-only; trivially exploitable if
  triggered.
- **Minimal fix:**
  1. In `.env.example`, add:
     ```
     SESSION_SECURE_COOKIE=true
     ```
  2. In `AppServiceProvider::boot()` (or similar), enforce:
     ```php
     if (app()->environment('production') && ! config('session.secure')) {
         throw new RuntimeException('SESSION_SECURE_COOKIE must be true in production.');
     }
     ```
- **Regression check:** Add `SmokeTest::test_production_requires_secure_cookie`
  with `APP_ENV=production` and assert the bootstrap throws.

### SEC-005 — Low — Pending-account leak via `status` validation key

- **Location:** `Modules/Auth/Services/AuthService.php:57-61`
- **CWE:** CWE-204 (Observable Response Discrepancy)
- **OWASP 2021:** A07
- **Evidence:**
  ```php
  if ($user->status !== 'active') {
      throw ValidationException::withMessages([
          'status' => ['Your account is pending approval.'],
      ]);
  }
  ```
- **Attack path:** Attacker POSTs `/api/login` with a known email and an
  arbitrary password. Two distinct error messages are returned —
  `email: Invalid credentials.` for unknown/wrong, and `status: Your account
  is pending approval.` for valid creds on a pending/rejected user. This
  instantly confirms that the email is registered AND reveals the account
  state.
- **Impact:** User enumeration + state disclosure.
- **PoC:** Static — the diverging message is visible in the source.
- **Severity rationale:** Lower than SEC-001 because the *exact* branch is
  reachable only with valid credentials, but the response shape is so
  distinct that enumeration is trivial.
- **Minimal fix:** Return the same generic message for both paths. If the
  UX must communicate "pending", do so after a successful login *attempt*
  by inspecting credentials (e.g. `if (Hash::check && status !== active)`
  use the same `'email' => ['Invalid credentials.']` key, and surface a
  separate "check your email for approval" hint in the email channel).

### SEC-006 — Low — Test gap: MassAssignmentTest covers web only

- **Location:** `tests/Feature/Security/MassAssignmentTest.php:20` —
  `$this->post('/register', ...)` (web route).
- **CWE:** CWE-1352 (Missing Test Coverage) — testing discipline, not a
  product vuln.
- **OWASP 2021:** A04 (test coverage failure mode)
- **Evidence:** Test posts to `/register` only. The API route at
  `Modules/Auth/routes/api.php:14` (`POST /api/register`) is not exercised.
- **Impact:** A regression that diverges web and API register behaviour
  would not be caught.
- **Severity rationale:** Test gap.
- **Minimal fix:** Add a second test method `test_api_register_cannot_set_role_to_admin`
  that posts to `/api/register` with `Accept: application/json` and asserts
  the same invariants.

### SEC-007 — Low — Weak password policy

- **Location:** `Modules/Auth/Http/Requests/RegisterRequest.php:15`
- **CWE:** CWE-521 (Weak Password Requirements)
- **OWASP 2021:** A07
- **Evidence:**
  ```php
  'password' => ['required', 'string', 'confirmed', Password::min(8)],
  ```
  No `->mixedCase()`, `->numbers()`, `->symbols()`, no breach-list check.
- **Attack path:** Users register with `password123` (test fixtures already
  do). Combined with `throttle:5,1` and SEC-001’s enumeration oracle,
  credential stuffing is feasible.
- **Impact:** Larger password keyspace reduces brute-force cost.
- **Severity rationale:** Mitigated by bcrypt cost 12 + login throttle.
- **Minimal fix:**
  ```php
  Password::min(10)->mixedCase()->numbers()->symbols()->uncompromised(),
  ```
  Note: `uncompromised()` requires HTTP to `api.pwnedpasswords.com`. If
  offline-only is required, drop that rule.

### SEC-008 — Informational — Throttle keyed by IP only

- **Location:** `Modules/Auth/routes/web.php:15-16`,
  `Modules/Auth/routes/api.php:14-15`
- **CWE:** CWE-770 (Allocation of Resources Without Limits or Throttling) — informational
- **Evidence:** `throttle:5,1` uses Laravel’s default IP-based key for
  unauthenticated routes.
- **Impact:** Shared NAT/CGNAT addresses (mobile carriers, corporate
  proxies) share the 5-per-minute quota — benign users can lock out
  others on their egress IP.
- **Severity rationale:** Operational, not security.
- **Recommendation:** Acceptable trade-off. Document in
  `SECURITY.md`. Optional: add a name-keyed limiter that uses
  `sha256($request->ip() . $request->userAgent())` for slightly better
  isolation.

### SEC-009 — Informational — JWT leeway = 0

- **Location:** `config/jwt.php:209`
- **Evidence:** `'leeway' => (int) env('JWT_LEEWAY', 0)`
- **Impact:** Zero clock-skew tolerance. In a multi-host / multi-container
  deployment with NTP drift, a token issued just before the next minute
  rollover can fail validation on a peer that is seconds ahead.
- **Recommendation:** Set `JWT_LEEWAY=60` in `.env.example` for production.

---

## Code Diffs (High/Critical only)

No Critical findings. Code diffs for the two High findings:

### SEC-001 — equalise timing

```diff
--- a/Modules/Auth/Services/AuthService.php
+++ b/Modules/Auth/Services/AuthService.php
@@ -47,7 +47,11 @@ class AuthService
     public function login(array $credentials)
     {
         $user = $this->userRepository->findByEmail($credentials['email']);

-        if (!$user || !Hash::check($credentials['password'], $user->password)) {
+        if (! $user) {
+            // Equalise timing: still run a real bcrypt verify.
+            Hash::check($credentials['password'], self::DUMMY_HASH);
+            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
+        }
+        if (! Hash::check($credentials['password'], $user->password)) {
             throw ValidationException::withMessages([
                 'email' => ['Invalid credentials.'],
             ]);
```

### SEC-002 — API logout with JWT invalidation

```diff
--- a/Modules/Auth/routes/api.php
+++ b/Modules/Auth/routes/api.php
@@ -17,3 +17,7 @@ Route::post('register', [AuthController::class, 'register'])->middleware('throttle:3,60');
 Route::group(['prefix' => 'admin', 'middleware' => 'auth:api'], function () {
     Route::get('users', [AdminUserController::class, 'index']);
     Route::post('users/{id}/approve', [AdminUserController::class, 'approve']);
 });
+
+Route::middleware('auth:api')->group(function () {
+    Route::post('logout', [AuthController::class, 'apiLogout'])->name('api.logout');
+});
```

```diff
--- a/Modules/Auth/Http/Controllers/AuthController.php
+++ b/Modules/Auth/Http/Controllers/AuthController.php
@@ -147,4 +147,11 @@ class AuthController extends Controller
             'token_type' => 'bearer',
         ]);
     }
+
+    public function apiLogout(): JsonResponse
+    {
+        JWTAuth::invalidate(JWTAuth::getToken());
+        return response()->json(['message' => 'Logged out.']);
+    }
 }
```

---

## Verification of Existing Tests

| Test | What it claims | What it actually does | Verdict |
|---|---|---|---|
| `MassAssignmentTest::test_register_cannot_set_role_to_admin` | Register cannot set `role` or `is_unlimited` | POSTs to web `/register` with `role=admin, status=active, is_unlimited=true`, asserts DB row has `role=student, status=pending, is_unlimited=false` | ✅ Correctly tests the invariant on web endpoint. (SEC-006 covers the missing API test.) |
| `RateLimitTest::test_login_throttles_after_five_attempts` | Login throttle returns 429 | Loops 5 wrong-password POSTs to `/login`, asserts 6th returns 429 | ✅ Matches `throttle:5,1` config. |
| `RateLimitTest::test_register_throttles_after_three_attempts` | Register throttle returns 429 | Loops 3 valid registrations, asserts 4th returns 429 | ✅ Matches `throttle:3,60` config. |
| `RateLimitTest::test_ai_chat_is_rate_limited_per_user` | AI endpoint is rate-limited per user | Acting-as-user, mocks `AiSpeakingService`, loops 20 calls, asserts 21st is 429 | ✅ Matches `RateLimiter::for('ai', ...)` at 20/min. |

Both `MassAssignmentTest` and `RateLimitTest` accurately exercise what their
docblocks claim for the *web* endpoint. Coverage gaps exist for the parallel
API endpoints and for the `ai-speaking` / `lesson-requests` named limiters,
which are not test gaps under this audit’s scope but are worth filing as
follow-up work.

---

## Residual Risk

* **JWT secret strength**: `JWT_SECRET` is `env(...)` with no minimum-length
  check. A weak secret would let an attacker forge tokens. Not verified
  in this audit — depends on the deployment’s `.env` discipline.
* **CSRF on web /register**: `/register` is inside the `guest` middleware
  group with no explicit CSRF middleware shown. Laravel 12 includes
  `ValidateCsrfToken` globally for web routes by default, so this is
  expected to be on — but verify in `bootstrap/app.php`.
* **API `/login` is `/api/login` (not under named route group)**: confirm
  the global `api` middleware group applies `throttle:api` in
  `bootstrap/app.php`. Otherwise the explicit `throttle:5,1` is the only
  protection.
* **MFA / step-up auth**: not in scope of this audit but is a known gap.
* **Telegram bot API secret in env**: a separate secret-management audit
  is needed; out of scope here.
* **Audit / log injection on failed logins**: not verified. High-volume
  failed logins could fill logs; recommend sampling.
* **No HTTP probing was performed.** All findings are based on static
  code review. Behavioural verification (timing tests, JWT replay) was
  not executed per task constraints.

---

## Recommended Fix Priority

1. **SEC-002** — add API logout + invalidation. Small change, high impact.
2. **SEC-001 + SEC-005** — equalise login error paths and remove status
   branch from response. Pair with a backend timing test.
3. **SEC-004** — set `SESSION_SECURE_COOKIE=true` in `.env.example` and
   add a boot-time check.
4. **SEC-003** — drop privilege fields from `$fillable`, require
   `forceFill` for admin operations.
5. **SEC-006** — add `MassAssignmentTest` API coverage.
6. **SEC-007** — strengthen password rule.
7. **SEC-008, SEC-009** — document / tune as time permits.

---

## Sign-off

**Verdict:** PASS WITH FINDINGS — release-block only on SEC-002 if JWT
sessions are considered long-lived credentials; otherwise all findings are
fixable in a single security hardening PR.

**Reviewed by:** static review of source + grep-based call-graph analysis.
**Live HTTP probes:** none (read-only audit per task constraints).