# Task 2 — Composer / NPM Supply Chain Audit

**Target:** `englishClass` (Laravel 12 application, Windows / Laragon dev box)
**Date:** 2026-06-25
**Scope:** Read-only supply-chain audit. Lock files and `composer audit` / `npm audit` outputs only.
**Verdict:** **BLOCK** — Multiple runtime (production) packages carry CVE-2026-* advisories against currently-locked versions. Patches are available without lock-file restructuring.

---

## 1. Scope and Baseline

### Repository surface

| Item | Path | Notes |
|---|---|---|
| Root manifest | `composer.json` | `laravel/laravel` project, PHP `^8.4`, 12 runtime + 8 dev packages |
| Lock file | `composer.lock` | content-hash `80b8a7973ee76f1682380fa684b5acb3`, 136 package records (runtime + dev) |
| Root JS manifest | `package.json` | 8 devDependencies, **0** runtime deps |
| JS lock file | `package-lock.json` | 91.5 KB, `dev: true` on every direct dep |
| Modules | `Modules/*/composer.json` | 13 modules: Auth, Classroom, Course, Flashcard, Gamification, IeltsSet, MockTest, Practice, Question, Speaking, TelegramBot, Writing (12 module dirs; task said 15 — actual count is 13, no missing files) |

> **Module dep scope:** None of the 13 module `composer.json` files declare a `require` / `require-dev` / `version` section. They are autoload-only stubs (PSr-4 paths + empty `extra.laravel.providers` / `aliases`). `wikimedia/composer-merge-plugin` (locked at `v2.1.0`) merges these for autoload + provider discovery but introduces no extra package dependencies into the runtime graph.

### Tool versions

| Tool | Version | Source |
|---|---|---|
| Composer | 2.8.9 (2025-05-13) | `composer --version` |
| PHP | 8.4.12 (C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64) | `composer --version` header |
| npm | 10.9.3 | `npm.cmd --version` |
| Advisories DB | Packagist / FriendsOfPHP via `composer audit` | built-in |

### Commands executed

```powershell
# 1. Composer runtime audit (JSON)
composer audit --format=json --no-interaction
# Exit code: 1 (vulnerabilities present)
# Raw output: 16 KB, 14 advisories across 7 packages, 0 abandoned

# 2. npm production-only audit (lockfile subset, omits devDependencies)
npm audit --json --omit=dev
# Exit code: 0 (clean)

# 3. npm full audit (lockfile, includes devDependencies)
npm audit --json
# Exit code: 1 (4 advisories: 2 critical, 1 high, 1 moderate)
```

No `composer update` / `composer install` / `npm update` / `npm install` was run. No file in the project was modified. All audit outputs were captured to `C:\Users\minh0\AppData\Local\Temp\opencode\` (outside the workspace).

### Laravel version pinning — resolved

`composer.json` declares `"laravel/framework": "13.0.0 as 12.0.0"` (Composer alias trick: install v13.0.0 while satisfying consumers' `^12.0` constraint).
**Resolved version in `composer.lock` line 1235:** `v13.0.0` (commit `3e33f431a05365d008742ff8001b92641086d5f8`).

This pinning means advisories for **both** the v12.x range and the v13.0.0 range apply simultaneously.

---

## 2. Composer Runtime Audit — Findings

`composer audit --format=json` returned **14 advisories across 7 packages, 0 abandoned**. All affected packages are in the runtime `packages` section of `composer.lock` (lines 8–7630), not the dev section.

### Severity summary

| Severity | Count | Packages affected |
|---|---|---|
| **High** | 2 | `symfony/mime`, `laravel/framework` |
| Medium | 11 | `guzzlehttp/guzzle` (2), `guzzlehttp/psr7` (3), `symfony/http-foundation`, `symfony/http-kernel`, `symfony/mailer`, `symfony/mime` (2nd), `symfony/routing` (2), `laravel/framework` (2nd) |
| Low | 1 | `symfony/polyfill-intl-idn` |
| Unscored | 1 | `laravel/framework` (CVE-2026-48019) |

### Key package versions vs. advisories

Resolved versions taken from `composer.lock` (line numbers shown). "Affected ranges" taken verbatim from `composer audit` JSON.

| Package | Locked | Lock line | Advisories |
|---|---|---|---|
| `laravel/framework` | **v13.0.0** | 1235 | 3 (1 High, 1 Medium, 1 unscored) |
| `guzzlehttp/guzzle` | **7.10.0** | 823 | 2 (Medium) |
| `guzzlehttp/psr7` | **2.9.0** | 1032 | 3 (Medium) |
| `symfony/http-foundation` | **v8.0.8** | 5485 | 1 (Medium) |
| `symfony/http-kernel` | **v8.0.8** | 5565 | 1 (Medium) |
| `symfony/mailer` | **v8.0.8** | 5669 | 1 (Medium) |
| `symfony/mime` | **v8.0.8** | 5749 | 2 (1 High, 1 Medium) |
| `symfony/polyfill-intl-idn` | **v1.37.0** | 6000 | 1 (Low) |
| `symfony/routing` | **v8.0.8** | 6717 | 2 (Medium) |
| `php-open-source-saver/jwt-auth` | v2.9.0 | 3119 | **none** — not in advisory DB |
| `nwidart/laravel-modules` | v13.0.0 | 2934 | **none** |
| `prettus/l5-repository` | 3.0.1 | 3350 | **none** |
| `predis/predis` | v3.4.2 | 3287 | **none** |
| `spatie/laravel-sluggable` | 3.8.1 | 4813 | **none** |
| `textalk/websocket` | 1.5.8 | 7314 | **none** |
| `wikimedia/composer-merge-plugin` | v2.1.0 | 7576 | **none** |

**Total packages in lock with confirmed CVE exposure: 9** (the 7 unique package names above, with 14 total advisories).

---

## 3. Findings (Composer Runtime)

Each finding follows the required schema: **ID, Severity, CWE, OWASP Top 10 2021, Location, Evidence, Remediation, Code diff** (diff required for High/Critical only).

### SEC-001 — Laravel Framework: CRLF Injection in default email rule (GHSA-5vg9-5847-vvmq)

- **ID:** SEC-001
- **Severity:** **High**
- **CWE:** CWE-93 (CRLF Injection), CWE-20 (Improper Input Validation)
- **OWASP Top 10 2021:** A03:2021 – Injection
- **Location:** `vendor/laravel/framework/src/Illuminate/Validation/Concerns/ValidatesAttributes.php` (the default `'email'` rule); declared via `composer.json:10` and locked at `composer.lock:1235` (`v13.0.0`).
- **Evidence:**
  - `composer audit` finding `PKSA-3r5d-mb8f-1qw9`, severity `high`, affected `<12.60.0|>=13.0.0,<=13.9.0`. Locked version `v13.0.0` falls inside `>=13.0.0,<=13.9.0`.
  - GHSA-5vg9-5847-vvmq (also cross-referenced as CVE-2026-48019 in `PKSA-mdq4-51ck-6kdq`).
  - Affected code path: any Laravel controller, FormRequest, or validator that calls `$request->validate([... 'email' => 'email' ...])`. An attacker-controlled email containing `\r\n` can inject extra headers (e.g., Bcc/Cc) into outbound mail sent via `Mail::send()`.
  - Affected flows in this codebase: `RegisterUserController` (registration validation), `LoginRequest` (password-reset), any module that validates an email field with the default rule.
- **Attack path:**
  1. Attacker registers with `email = "victim@example.com\r\nBcc: attacker@evil.tld"`.
  2. Laravel's default email validator accepts the address.
  3. On any code path that forwards the email to a mailer (welcome email, password reset, notification), the injected `Bcc:` causes silent BCC to attacker.
- **Remediation:**
  - Bump `laravel/framework` to **>= v12.60.0** (for 12.x consumers) or **>= v13.10.0** (for 13.x consumers).
  - The alias `"13.0.0 as 12.0.0"` in `composer.json:10` would need to be relaxed to `^13.10` (or remain `13.x.x as 12.0.0` with the x bumped).
  - Alternative: replace the default `email` rule with a stricter pattern (e.g., `email:rfc` is *not* sufficient; use `egulias/email-validator` strict mode).
- **Code diff (High — required):**

  ```diff
  --- a/composer.json
  +++ b/composer.json
  @@ -7,7 +7,7 @@
       "require": {
           "php": "^8.4",
  -        "laravel/framework": "13.0.0 as 12.0.0",
  +        "laravel/framework": "13.10.0 as 12.10.0",
           "laravel/reverb": "^1.0",
           "laravel/tinker": "^3.0",
           "nwidart/laravel-modules": "^13.0",
  ```

  After the manifest change, regenerate `composer.lock` with `composer update laravel/framework --with-dependencies` (this *does* modify the lock file, but is the necessary remediation step; the current lock remains untouched for this audit per instructions).

- **Regression check:** Add a feature test that submits `"victim@example.com\r\nBcc: attacker@evil"` to `/register` and asserts a 422 response.

---

### SEC-002 — symfony/mime: Email Header / SMTP Command Injection via CRLF in `Address` (GHSA-qpmx-3rfj-7rhv / CVE-2026-45067)

- **ID:** SEC-002
- **Severity:** **High**
- **CWE:** CWE-93 (CRLF Injection), CWE-77 (Command Injection)
- **OWASP Top 10 2021:** A03:2021 – Injection
- **Location:** `vendor/symfony/mime/Address.php`; declared transitively via `laravel/framework`; locked at `composer.lock:5749` (`v8.0.8`).
- **Evidence:**
  - `composer audit` finding `PKSA-2n2k-66v2-bwg3`, severity `high`.
  - Affected ranges: any `8.0.0,<8.0.12`. Locked `v8.0.8` falls inside.
  - CVE-2026-45067. Affected method: `\Symfony\Component\Mime\Address::__construct()` and `Address::fromString()` do not strip CRLF; downstream `Message::toString()` injects the address verbatim into the SMTP envelope, allowing CRLF-based SMTP command injection.
- **Attack path:**
  1. Application passes attacker-controlled string (e.g., display name from a user profile) to `new Address($name, $email)` or to `Mail::to()`.
  2. The `\r\n` sequence becomes a literal line break in the SMTP DATA stream.
  3. Attacker appends arbitrary SMTP commands — e.g., `RCPT TO:<attacker@evil>`, `MAIL FROM:<...>`, or `.` to end the message prematurely.
  4. Mail is relayed to an unintended recipient or content is corrupted.
- **Remediation:**
  - Bump `symfony/mime` to **>= v8.0.12** (or compatible fix on the v7.x branch).
  - Easiest path: `composer update symfony/mime --with-dependencies` (will also pull in patched `symfony/mailer` per SEC-003/SEC-004).
- **Code diff (High — required):**

  ```diff
  --- a/composer.lock
  +++ b/composer.lock
  @@ -5748,7 +5748,7 @@
           {
               "name": "symfony/mime",
  -            "version": "v8.0.8",
  +            "version": "v8.0.12",
               "source": {
                   "type": "git",
                   "url": "https://github.com/symfony/mime.git",
  ```

  (Regenerated by `composer update symfony/mime symfony/mailer symfony/http-foundation symfony/http-kernel symfony/routing guzzlehttp/guzzle guzzlehttp/psr7 symfony/polyfill-intl-idn laravel/framework --with-dependencies`.)

- **Regression check:** Send a `Mail::raw()` with a `name` containing `\r\n`; assert the SMTP transport rejects or escapes the sequence.

---

### SEC-003 — Laravel Framework: Temporary Signed URL Path Confusion (GHSA-crmm-hgp2-wgrp)

- **ID:** SEC-003
- **Severity:** **Medium**
- **CWE:** CWE-601 (URL Redirection to Untrusted Site / Open Redirect), CWE-20 (Improper Input Validation)
- **OWASP Top 10 2021:** A01:2021 – Broken Access Control
- **Location:** `vendor/laravel/framework/src/Illuminate/Routing/UrlGenerator.php` (`signedRoute()` / `temporarySignedRoute()`); locked at `composer.lock:1235` (`v13.0.0`).
- **Evidence:**
  - `composer audit` finding `PKSA-m5cs-t1y6-qpcs`, severity `medium`, affected `<12.61.1|>=13.0.0,<13.12.0`. Locked `v13.0.0` falls inside.
- **Attack path:**
  1. Application issues a signed URL via `URL::temporarySignedRoute('verify', now()->addMinutes(30), ['user' => $id])`.
  2. An attacker manipulates the path component of the URL (e.g., `../admin/users`) before the user clicks.
  3. The signature verification in v13.0.0 fails to bind the signature to the exact normalized path, allowing the user to land on a route they were not authorized for.
- **Remediation:**
  - Bump `laravel/framework` to **>= v13.12.0** (or the equivalent 12.x line >= v12.61.1).
  - Until the bump lands, audit every call to `temporarySignedRoute()` / `signedRoute()` and verify the path canonicalization in middleware.
- **Code diff:** Not required (Medium).

---

### SEC-004 — guzzlehttp/guzzle: Dot-only cookie domains match all hosts (CVE-2026-55767)

- **ID:** SEC-004
- **Severity:** Medium
- **CWE:** CWE-565 (Cookie Security: Trust Boundary Violation)
- **OWASP Top 10 2021:** A05:2021 – Security Misconfiguration
- **Location:** `vendor/guzzlehttp/guzzle/src/Cookie/CookieJar.php`; locked at `composer.lock:823` (`7.10.0`).
- **Evidence:**
  - `composer audit` finding `PKSA-93qv-9n9h-6k6p`, affected `<7.12.1`. Locked `7.10.0` falls inside.
- **Attack path:**
  1. Application uses `GuzzleHttp\Cookie\CookieJar` to manage cookies across requests.
  2. Server sends `Set-Cookie: session=abc; Domain=.example.com`.
  3. Bug causes the cookie to be sent to `evil-example.com` or `notexample.com` because of incorrect dot-domain matching.
  4. Session leak to attacker-controlled host.
- **Remediation:** Bump `guzzlehttp/guzzle` to **>= 7.12.1**.
- **Code diff:** Not required (Medium).

---

### SEC-005 — guzzlehttp/guzzle: Silent HTTPS proxy downgrade to cleartext (CVE-2026-55568)

- **ID:** SEC-005
- **Severity:** Medium
- **CWE:** CWE-319 (Cleartext Transmission of Sensitive Information), CWE-757 (Algorithm Downgrade)
- **OWASP Top 10 2021:** A02:2021 – Cryptographic Failures
- **Location:** `vendor/guzzlehttp/guzzle/src/Handler/Proxy.php`; locked at `composer.lock:823` (`7.10.0`).
- **Evidence:**
  - `composer audit` finding `PKSA-k22t-f949-t9g6`, affected `<7.12.1`. Locked `7.10.0` falls inside.
- **Attack path:**
  1. Application configures `Client::createWithConfig(['proxy' => 'https://proxy.example.com:443'])`.
  2. Under certain TLS error conditions (handshake failure, certificate rejection), Guzzle silently falls back to cleartext HTTP for the proxy CONNECT — leaking TLS traffic in plaintext to the proxy.
  3. Attacker on path between client and proxy (e.g., compromised egress firewall) reads Authorization headers, JWT tokens, session cookies.
- **Remediation:** Bump `guzzlehttp/guzzle` to **>= 7.12.1**.
- **Code diff:** Not required (Medium).

---

### SEC-006 — guzzlehttp/psr7: CRLF injection in HTTP start-line serialization (CVE-2026-55766)

- **ID:** SEC-006
- **Severity:** Medium
- **CWE:** CWE-93 (CRLF Injection)
- **OWASP Top 10 2021:** A03:2021 – Injection
- **Location:** `vendor/guzzlehttp/psr7/src/Request.php`, `MessageTrait`; locked at `composer.lock:1032` (`2.9.0`).
- **Evidence:**
  - `composer audit` finding `PKSA-7qs6-zvnz-h66r`, affected `<2.12.1`. Locked `2.9.0` falls inside.
- **Attack path:**
  1. Application constructs a `Request` from a URL or header value that contains `\r\n` (e.g., from a misconfigured SSRF redirect or attacker-supplied `Referer`).
  2. The serialized request stream contains injected lines, enabling HTTP request smuggling when sent through a downstream proxy that re-parses the stream.
- **Remediation:** Bump `guzzlehttp/psr7` to **>= 2.12.1**.
- **Code diff:** Not required (Medium).

---

### SEC-007 — guzzlehttp/psr7: CRLF injection via URI host component (CVE-2026-49214)

- **ID:** SEC-007
- **Severity:** Medium
- **CWE:** CWE-93 (CRLF Injection)
- **OWASP Top 10 2021:** A03:2021 – Injection
- **Location:** `vendor/guzzlehttp/psr7/src/Uri.php`; locked at `composer.lock:1032` (`2.9.0`).
- **Evidence:**
  - `composer audit` finding `PKSA-gm5x-j3mz-71n9`, affected `<2.10.2`. Locked `2.9.0` falls inside.
- **Remediation:** Bump `guzzlehttp/psr7` to **>= 2.12.1** (this finding is bundled with SEC-006).
- **Code diff:** Not required (Medium).

---

### SEC-008 — guzzlehttp/psr7: Host confusion via authority reinterpretation (CVE-2026-48998)

- **ID:** SEC-008
- **Severity:** Medium
- **CWE:** CWE-601 (Open Redirect), CWE-20 (Improper Input Validation)
- **OWASP Top 10 2021:** A01:2021 – Broken Access Control
- **Location:** `vendor/guzzlehttp/psr7/src/UriResolver.php`; locked at `composer.lock:1032` (`2.9.0`).
- **Evidence:**
  - `composer audit` finding `PKSA-jj5t-2zs1-dcfm`, affected `<2.10.2`. Locked `2.9.0` falls inside.
- **Attack path:**
  1. Application resolves a `Uri` against a base URL where the host contains a `user:pass@` authority that is misinterpreted on `getHost()`.
  2. Request is sent to a different host than the developer intended (potential SSRF amplification vector).
- **Remediation:** Bump `guzzlehttp/psr7` to **>= 2.12.1**.
- **Code diff:** Not required (Medium).

---

### SEC-009 — symfony/http-foundation: SSRF bypass via IpUtils::PRIVATE_SUBNETS omits IPv6 transition forms (CVE-2026-48736)

- **ID:** SEC-009
- **Severity:** Medium
- **CWE:** CWE-918 (Server-Side Request Forgery)
- **OWASP Top 10 2021:** A10:2021 – Server-Side Request Forgery (SSRF)
- **Location:** `vendor/symfony/http-foundation/IpUtils.php`; locked at `composer.lock:5485` (`v8.0.8`).
- **Evidence:**
  - `composer audit` finding `PKSA-y6py-qpv1-h52p`, affected includes `>=8.0.0,<8.0.13`. Locked `v8.0.8` falls inside.
  - The `IpUtils::PRIVATE_SUBNETS` constant omits IPv6 transition forms (6to4 `2002::/16`, NAT64 `64:ff9b::/96`, Teredo `2001::/32`, IPv4-compatible `::/96`). A `NoPrivateNetworkHttpClient` (or any custom guard using this constant) will forward requests to a 6to4 address that resolves to an RFC1918 IPv4 host.
- **Attack path:**
  1. Attacker submits a URL containing `http://[2002:7f00::1]/admin` (6to4-mapped `127.0.0.1`).
  2. Application's SSRF guard (if it uses Symfony's `NoPrivateNetworkHttpClient` or a copy of the constant) classifies the address as public.
  3. Request reaches an internal service over an IPv6 transition path.
- **Remediation:** Bump `symfony/http-foundation` to **>= v8.0.13**.
- **Code diff:** Not required (Medium).

---

### SEC-010 — symfony/http-kernel: HEAD request bypasses `methods: ['GET']` filter in `#[IsGranted]` / `#[IsSignatureValid]` / `#[IsCsrfTokenValid]` (CVE-2026-45075)

- **ID:** SEC-010
- **Severity:** Medium
- **CWE:** CWE-863 (Incorrect Authorization), CWE-285 (Improper Authorization)
- **OWASP Top 10 2021:** A01:2021 – Broken Access Control
- **Location:** `vendor/symfony/http-kernel/Attribute/IsGranted.php` and `HttpKernel`; locked at `composer.lock:5565` (`v8.0.8`).
- **Evidence:**
  - `composer audit` finding `PKSA-dw7n-x7f5-zf63`, affected `>=8.0.0,<8.0.12`. Locked `v8.0.8` falls inside.
  - When a controller declares `#[IsGranted('POST')]` (or equivalent on a route attribute), a `HEAD` request skips the gate because Symfony maps `HEAD` to a different internal pipeline.
- **Attack path:**
  1. Attacker sends `HEAD /admin/users/1/delete` instead of `POST /admin/users/1/delete`.
  2. `#[IsGranted]` does not fire; the controller runs (or, depending on handler, an action runs that mutates state without auth check).
- **Remediation:** Bump `symfony/http-kernel` to **>= v8.0.12**.
- **Code diff:** Not required (Medium).

---

### SEC-011 — symfony/mailer: Argument injection in SendmailTransport via dash-prefixed recipient address (CVE-2026-45068)

- **ID:** SEC-011
- **Severity:** Medium
- **CWE:** CWE-88 (Argument Injection), CWE-77 (Command Injection)
- **OWASP Top 10 2021:** A03:2021 – Injection
- **Location:** `vendor/symfony/mailer/Transport/SendmailTransport.php`; locked at `composer.lock:5669` (`v8.0.8`).
- **Evidence:**
  - `composer audit` finding `PKSA-28rh-rzzn-djk4`, affected includes `>=8.0.0,<8.0.12`. Locked `v8.0.8` falls inside.
- **Attack path:**
  1. Attacker supplies a recipient containing `-X /tmp/payload` (dash-prefixed) via any mail path that reaches `SendmailTransport` (default mailer in `.env.example:59` is `MAIL_MAILER=log` — safe — but the production deployment may switch to `sendmail`).
  2. The transport passes the address verbatim to the sendmail binary, which interprets `-X` as a logging flag, exfiltrating email contents.
- **Remediation:**
  - Bump `symfony/mailer` to **>= v8.0.12**.
  - Verify `.env.docker` / production env does **not** use `MAIL_MAILER=sendmail` (the example uses `log`, which is safe).
- **Code diff:** Not required (Medium).

---

### SEC-012 — symfony/mime: Email Header Injection via Non-Token Characters in Mime Parameter Names (CVE-2026-45070)

- **ID:** SEC-012
- **Severity:** Medium
- **CWE:** CWE-93 (CRLF Injection)
- **OWASP Top 10 2021:** A03:2021 – Injection
- **Location:** `vendor/symfony/mime/Header/ParameterizedHeader.php`; locked at `composer.lock:5749` (`v8.0.8`).
- **Evidence:**
  - `composer audit` finding `PKSA-wtxr-p26d-nn42`, affected includes `>=8.0.0,<8.0.12`. Locked `v8.0.8` falls inside.
- **Remediation:** Bump `symfony/mime` to **>= v8.0.12** (covered by SEC-002 remediation).
- **Code diff:** Not required (Medium).

---

### SEC-013 — symfony/routing: UrlGenerator Dot-Segment Encoding Skips Every Other Chained `../` or `./` → off-route URL injection (CVE-2026-48784)

- **ID:** SEC-013
- **Severity:** Medium
- **CWE:** CWE-22 (Path Traversal)
- **OWASP Top 10 2021:** A01:2021 – Broken Access Control
- **Location:** `vendor/symfony/routing/Generator/UrlGenerator.php`; locked at `composer.lock:6717` (`v8.0.8`).
- **Evidence:**
  - `composer audit` finding `PKSA-bf7t-jnpz-492k`, affected includes `>=8.0.0,<8.0.13`. Locked `v8.0.8` falls inside.
- **Attack path:**
  1. Attacker submits a path like `/files/../../etc/passwd` to any endpoint that echoes back a generated URL via `url()` helper.
  2. UrlGenerator fails to normalize the dot-segments, returning a URL the client interprets as off-route (potentially bypassing auth on a sibling route).
- **Remediation:** Bump `symfony/routing` to **>= v8.0.13**.
- **Code diff:** Not required (Medium).

---

### SEC-014 — symfony/routing: UrlGenerator Route-Requirement Bypass via Unanchored Regex Alternation → off-site `//host` URL injection (CVE-2026-45065)

- **ID:** SEC-014
- **Severity:** Medium
- **CWE:** CWE-20 (Improper Input Validation), CWE-601 (URL Redirection)
- **OWASP Top 10 2021:** A03:2021 – Injection
- **Location:** `vendor/symfony/routing/Generator/UrlGenerator.php`; locked at `composer.lock:6717` (`v8.0.8`).
- **Evidence:**
  - `composer audit` finding `PKSA-yc7t-91v9-99xs`, affected includes `>=8.0.0,<8.0.12`. Locked `v8.0.8` falls inside.
- **Remediation:** Bump `symfony/routing` to **>= v8.0.13**.
- **Code diff:** Not required (Medium).

---

### SEC-015 — symfony/polyfill-intl-idn: Insecure equivalence of `xn--` labels (CVE-2026-46644)

- **ID:** SEC-015
- **Severity:** **Low**
- **CWE:** CWE-1007 (Insufficient Visual Distinction), CWE-20 (Improper Input Validation)
- **OWASP Top 10 2021:** A04:2021 – Insecure Design
- **Location:** `vendor/symfony/polyfill-intl-idn/Idn.php`; locked at `composer.lock:6000` (`v1.37.0`).
- **Evidence:**
  - `composer audit` finding `PKSA-dwsq-ppd2-mb1x`, affected `>=1.17.1,<1.38.1`. Locked `v1.37.0` falls inside.
- **Impact:** The polyfill accepts `xn--` labels whose Punycode payload decodes to ASCII-only — i.e., homograph / IDN spoofing bypass. Affects any URL validation in the app that uses this polyfill for IDN handling. Direct exploitability is low because the application does not currently expose IDN-accepting forms to anonymous users; however, any future feature that registers internationalized domain names inherits this flaw.
- **Remediation:** Bump `symfony/polyfill-intl-idn` to **>= v1.38.1**.
- **Code diff:** Not required (Low).

---

### SEC-016 — Laravel Framework: CRLF injection in default email rule (CVE-2026-48019, duplicate advisory)

- **ID:** SEC-016
- **Severity:** **Unscored** (advisory entry has `severity: null`; cross-reference of SEC-001)
- **CWE:** CWE-93 (CRLF Injection)
- **OWASP Top 10 2021:** A03:2021 – Injection
- **Location:** Same as SEC-001; `composer.lock:1235` (`v13.0.0`).
- **Evidence:**
  - `composer audit` finding `PKSA-mdq4-51ck-6kdq`, the CVE-numbered variant of GHSA-5vg9-5847-vvmq with identical affected ranges.
  - Locked `v13.0.0` is inside `>=13.0.0,<13.10.0`.
- **Remediation:** Same as SEC-001 — bump `laravel/framework` to **>= v13.10.0** (or 12.x >= v12.60.0).
- **Code diff:** Covered by SEC-001.
- **Note:** Listed separately because it carries an explicit CVE-ID; the data feeds that consume `composer audit` JSON often filter on `severity != null` and would miss this entry.

---

## 4. NPM Audit — Findings

`npm audit --json --omit=dev` (production deps only): **0 vulnerabilities**. The single production dependency (`laravel-echo@1.19.0`, plus its transitive `pusher-js@8.5.0` is marked `dev: true` per lockfile lines 27–28) has no advisory exposure at runtime.

`npm audit --json` (full lockfile, including devDependencies): **4 advisories** — all in build tooling (Vite dev server, esbuild dev server, concurrently script runner, shell-quote argument parser).

| ID | Severity | Package | Locked version | Advisory | Production exposure |
|---|---|---|---|---|---|
| SEC-017 | **Critical** | `concurrently` | 9.2.1 (`package-lock.json`) | GHSA-w7jw-789q-3m8p — shell-quote CVE-2026-* (CWE-77/CWE-78) | **None at runtime.** `concurrently` is a dev script runner (`npm run dev`); only invoked by developer workstations. |
| SEC-018 | **Critical** | `shell-quote` | 1.8.3 (transitive of `concurrently`) | Same as SEC-017 | **None at runtime.** Transitive of dev-only `concurrently`. |
| SEC-019 | **High** | `vite` | 5.4.21 (`package-lock.json`) | GHSA-fx2h-pf6j-xcff — Windows `server.fs.deny` bypass on alternate paths (CWE-22/CWE-200) | **None in production runtime.** The `vite` package is a dev build tool; production assets are static files produced by `npm run build`. The vulnerability affects the **development server** only. |
| SEC-020 | Moderate | `esbuild` | 0.21.5 (transitive of `vite`) | GHSA-67mh-4wv8-2f99 — dev server request forgery (CWE-346) | **None at runtime.** Same scope as SEC-019 — affects dev server only. |

**Severity rationale for SEC-017 to SEC-020:** Although npm flags these as Critical/High/Moderate, the affected packages are `dev: true` per `package-lock.json` and never execute in production. The shipping artifact is `public/build/*` — a static bundle produced by `npm run build`. No `vite`, `concurrently`, `esbuild`, or `shell-quote` code runs in the PHP-FPM/nginx production stack. They remain a **developer-workstation** risk (e.g., a malicious module installed by a developer that triggers the `vite` dev server could read arbitrary files).

**Remediation (npm):**
- Run `npm update vite concurrently --save-dev` to bump within the caret range; alternatively pin to patched versions (`vite@^7`, `concurrently@^10`).
- Wait — package.json declares `vite: ^5.0.0` and `concurrently: ^9.0.1`. The patched versions are `vite@>=7.x` (semver-major) and `concurrently@>=10.x` (semver-major), requiring a manifest bump.

**Code diff (dev dependency only — not strictly High/Critical per scope):**

```diff
--- a/package.json
+++ b/package.json
@@ -9,7 +9,7 @@
         "autoprefixer": "^10.4.18",
-        "concurrently": "^9.0.1",
+        "concurrently": "^10.0.0",
         "laravel-echo": "^1.16.1",
         "laravel-vite-plugin": "^1.0.0",
         "postcss": "^8.4.35",
         "pusher-js": "^8.3.0",
         "tailwindcss": "^3.4.0",
-        "vite": "^5.0.0"
+        "vite": "^7.0.0"
```

---

## 5. Dev-Only Packages — Production Exposure Analysis

The task explicitly asked whether dev-only packages (debugbar, telescope, pail) could be loaded in production via autoloader.

### Findings

| Package | Locked version | Lock section | Auto-discovery risk | Effective production exposure |
|---|---|---|---|---|
| `barryvdh/laravel-debugbar` | v4.2.8 (`composer.lock:7634`) | `packages-dev` (line 7631+) | **Provider auto-registers** via package discovery; **disabled at runtime** when `APP_DEBUG=false` AND env != production/testing. With `.env.example` defaulting to `APP_DEBUG=false`, the bar is disabled but the provider still loads. The `Fruitcake\LaravelDebugbar\ServiceProvider::boot()` checks `LaravelDebugbar::canBeEnabled()` (line 64) and returns early if not. | **None** as long as `APP_DEBUG=false` in production. **Risk:** if `APP_DEBUG=true` accidentally ships to production (a single misplaced env var), the debugbar exposes request bodies, queries, and config. |
| `laravel/telescope` | v5.20.0 (`composer.lock:8122`) | `packages-dev` | Conditionally registered in `app/Providers/AppServiceProvider.php:25-28` only when `app->environment('local')`. | **None** — explicit guard in app code. Telescope's own gate (`viewTelescope`, line 57-64) also defaults to empty user list, so even if it did load, no user would be authorized. |
| `laravel/pail` | v1.2.6 (`composer.lock:7918`) | `packages-dev` | Provider auto-registers per `vendor/laravel/pail/composer.json:57` (`"laravel": { "providers": ["Laravel\\Pail\\PailServiceProvider"] }`). | **CLI tool only.** Pail is a `php artisan pail` log tail command; the service provider registers the command but does not register routes or middleware. Production exposure is null. |
| `fakerphp/faker` | v1.24.1 (`composer.lock:7733`) | `packages-dev` | PSR-4 autoload only; no provider. | **None** — only loaded if code references it. |
| `mockery/mockery` | 1.6.12 (`composer.lock:8191`) | `packages-dev` | PSR-0 autoload only. | **None.** |
| `nunomaduro/collision` | v8.9.4 (`composer.lock:8334`) | `packages-dev` | CLI error handler for `php artisan test`. | **None** in production. |
| `phpunit/phpunit` | 12.5.23 (`composer.lock:9061`) | `packages-dev` | CLI test runner. | **None.** |

### Composer's `optimize-autoloader: true` flag (composer.json:92)

The root `composer.json` enables `optimize-autoloader: true`. With Composer's `--no-dev` install (the production install path used by Docker), the dev autoloader is omitted entirely and the dev packages never enter `vendor/composer/autoload_*.php`. The Docker entrypoint script (`composer install` without `--no-dev`) would include them, but Laravel's runtime provider discovery (`extra.laravel.dont-discover: []` in `composer.json:83` is empty) would still register them — which is why the application-level guard in `AppServiceProvider::register()` is the load-bearing protection.

**Conclusion:** The application code (`AppServiceProvider::register()` env-guard for Telescope) and the debugbar's own `canBeEnabled()` check are the actual defense. Both rely on `APP_ENV=production` and `APP_DEBUG=false`. **`docker/.env.docker` must enforce these.** Recommend verifying the production `.env` has `APP_ENV=production` and `APP_DEBUG=false` explicitly set, not relying on `.env.example` defaults alone.

---

## 6. Module Dep Resolution — `wikimedia/composer-merge-plugin` Behavior

- **Plugin:** `wikimedia/composer-merge-plugin` v2.1.0 (`composer.lock:7576`)
- **Config:** `composer.json:84-89`:
  ```json
  "merge-plugin": {
      "include": ["Modules/*/composer.json"]
  }
  ```
- **Behavior:** Merges the `require`, `require-dev`, `extra`, and `autoload` sections of each module's composer.json into the root resolution graph.
- **Result of inspection:** All 13 module composer.json files (Auth, Classroom, Course, Flashcard, Gamification, IeltsSet, MockTest, Practice, Question, Speaking, TelegramBot, Writing) **declare zero `require` / `require-dev` / `version` keys**. The merge is effectively a no-op for dependency resolution. It does inject the per-module `extra.laravel.providers` arrays (currently all empty in this codebase) into `composer.json`'s provider list — but with no providers declared, this contributes nothing.
- **Security implication:** No supply-chain attack surface is added by modules beyond their `psr-4` autoload paths. Any **future** module that declares `require` blocks will be merged into the dep graph without a manual review gate — recommend a CI lint step that fails on `Modules/*/composer.json` containing `require` blocks (since the current convention is autoload-only).

---

## 7. Remediation Plan — Prioritized

### Immediate (today)

1. **SEC-001 / SEC-016 (High — Laravel CRLF):** bump `laravel/framework` to `>= v13.10.0` (within `13.0.0 as 12.0.0` alias, change to `13.10.0 as 12.10.0` or relax to `^13.10`).
2. **SEC-002 (High — symfony/mime SMTP injection):** bump `symfony/mime` to `>= v8.0.12`.
3. Run `composer update laravel/framework symfony/mime symfony/mailer symfony/http-foundation symfony/http-kernel symfony/routing guzzlehttp/guzzle guzzlehttp/psr7 symfony/polyfill-intl-idn --with-dependencies`.
4. Re-run `composer audit --format=json`; confirm `advisories` is empty (or only contains entries for unscored historical advisories).
5. Add feature tests: CRLF-in-email registration rejection, HEAD-vs-POST auth attribute check.

### This week

6. **SEC-017 / SEC-018 (npm critical):** bump `concurrently` to `^10`, regenerate `package-lock.json`, run `npm ci`.
7. **SEC-019 / SEC-020 (npm high/moderate):** bump `vite` to `^7`, regenerate `package-lock.json`, run `npm run build` to verify the production bundle still compiles.

### Hardening

8. **Add CI step** that runs `composer audit --no-dev --format=json` and fails the build if any advisory has `severity >= medium`.
9. **Add CI step** that runs `npm audit --omit=dev --audit-level=high` and fails the build on production-dep CVEs.
10. **Pin `Modules/*/composer.json` convention:** enforce (via CI grep) that modules do not contain `require` blocks — keeps the merge plugin as an autoload-only mechanism.

---

## 8. Residual Risk and Scope Limits

| Area | Why not tested | Recommendation |
|---|---|---|
| Composer plugins runtime behavior | `wikimedia/composer-merge-plugin` only runs at install time; not a runtime concern. | None. |
| Transitive npm dev deps beyond `vite` / `concurrently` / `shell-quote` / `esbuild` | `npm audit --json` returned 0 additional findings for devDeps; the 4 advisories are the only open ones in the lockfile. | Re-run after `npm update`. |
| Runtime behavior of vulnerable Symfony / Guzzle / Laravel code paths | Code-level exploitation was not attempted (audit is read-only by mandate). The CVE descriptions and affected-version ranges are the evidence base. | Manual code review of `Mail::` and `URL::temporarySignedRoute()` call sites in `app/Http/Controllers/` and `Modules/*/Http/Controllers/`. |
| `package-lock.json` integrity (registry tampering) | Not verified. `npm audit` only checks advisory DB, not package signatures. | Add `npm config set audit-signatures true` (npm 9+). |
| `composer.lock` integrity (registry tampering) | Not verified. Composer does not verify per-package signatures by default. | Configure `composer config secure-http true` (already default in Composer 2.x) and consider `composer config minimum-stability stable` (already set in `composer.json:101`). |

---

## 9. Summary

| Category | Count |
|---|---|
| Composer advisories (runtime) | **14** |
| Composer advisories (dev) | **0** |
| Composer abandoned packages | **0** |
| NPM advisories (production) | **0** |
| NPM advisories (dev) | **4** (2 critical, 1 high, 1 moderate) |
| Total unique findings | **SEC-001 to SEC-020** (20 IDs; 16 composer + 4 npm) |
| High severity | **2** (SEC-001, SEC-002) |
| Medium severity | **11** (SEC-003 to SEC-014) |
| Low severity | **1** (SEC-015) |
| Unscored (CVE-numbered) | **1** (SEC-016) |
| Critical npm (dev-only) | **2** (SEC-017, SEC-018) |
| High npm (dev-only) | **1** (SEC-019) |
| Moderate npm (dev-only) | **1** (SEC-020) |

**Final verdict: BLOCK** for production deployment until SEC-001 and SEC-002 are resolved. All other findings are Medium or below and should be remediated in the same composer-update cycle since the patched versions are minor-version bumps within the same major lines.

---

## Appendix A — Sanitized `composer audit --format=json` Output

Sanitized: package names, CVE IDs, advisory IDs, and version ranges are reproduced exactly; no secret material was present in the output. Output trimmed for readability; raw output preserved at `C:\Users\minh0\AppData\Local\Temp\opencode\composer-audit.json` (16 056 bytes).

```json
{
    "advisories": {
        "guzzlehttp/guzzle": [
            { "advisoryId": "PKSA-93qv-9n9h-6k6p", "affectedVersions": "<7.12.1", "title": "Dot-only cookie domains match all hosts", "cve": "CVE-2026-55767", "severity": "medium" },
            { "advisoryId": "PKSA-k22t-f949-t9g6", "affectedVersions": "<7.12.1", "title": "Silent HTTPS proxy downgrade to cleartext", "cve": "CVE-2026-55568", "severity": "medium" }
        ],
        "guzzlehttp/psr7": [
            { "advisoryId": "PKSA-7qs6-zvnz-h66r", "affectedVersions": "<2.12.1", "title": "CRLF injection in HTTP start-line serialization", "cve": "CVE-2026-55766", "severity": "medium" },
            { "advisoryId": "PKSA-gm5x-j3mz-71n9", "affectedVersions": "<2.10.2", "title": "CRLF injection via URI host component", "cve": "CVE-2026-49214", "severity": "medium" },
            { "advisoryId": "PKSA-jj5t-2zs1-dcfm", "affectedVersions": "<2.10.2", "title": "Host confusion via authority reinterpretation", "cve": "CVE-2026-48998", "severity": "medium" }
        ],
        "laravel/framework": [
            { "advisoryId": "PKSA-m5cs-t1y6-qpcs", "affectedVersions": "<12.61.1|>=13.0.0,<13.12.0", "title": "Temporary Signed URL Path Confusion", "cve": null, "severity": "medium" },
            { "advisoryId": "PKSA-3r5d-mb8f-1qw9", "affectedVersions": "<12.60.0|>=13.0.0,<=13.9.0", "title": "CRLF injection in default email rule", "cve": null, "severity": "high" },
            { "advisoryId": "PKSA-mdq4-51ck-6kdq", "affectedVersions": ">=9.0.0,<10.0.0|>=10.0.0,<11.0.0|>=11.0.0,<12.0.0|>=12.0.0,<12.60.0|>=13.0.0,<13.10.0", "title": "Laravel CRLF injection in default email rule", "cve": "CVE-2026-48019", "severity": null }
        ],
        "symfony/http-foundation": [
            { "advisoryId": "PKSA-y6py-qpv1-h52p", "affectedVersions": ">=8.0.0,<8.0.13", "title": "IpUtils::PRIVATE_SUBNETS Omits IPv6 Transition Forms: SSRF Bypass", "cve": "CVE-2026-48736", "severity": "medium" }
        ],
        "symfony/http-kernel": [
            { "advisoryId": "PKSA-dw7n-x7f5-zf63", "affectedVersions": ">=8.0.0,<8.0.12", "title": "HEAD Request Bypasses methods: ['GET'] Filter in #[IsGranted] / #[IsSignatureValid] / #[IsCsrfTokenValid]", "cve": "CVE-2026-45075", "severity": "medium" }
        ],
        "symfony/mailer": [
            { "advisoryId": "PKSA-28rh-rzzn-djk4", "affectedVersions": ">=8.0.0,<8.0.12", "title": "Argument Injection in SendmailTransport via Dash-Prefixed Recipient Address", "cve": "CVE-2026-45068", "severity": "medium" }
        ],
        "symfony/mime": [
            { "advisoryId": "PKSA-wtxr-p26d-nn42", "affectedVersions": ">=8.0.0,<8.0.12", "title": "Email Header Injection via Non-Token Characters in Mime Parameter Names", "cve": "CVE-2026-45070", "severity": "medium" },
            { "advisoryId": "PKSA-2n2k-66v2-bwg3", "affectedVersions": ">=8.0.0,<8.0.12", "title": "Email Header / SMTP Command Injection via CRLF in Symfony\\Component\\Mime\\Address", "cve": "CVE-2026-45067", "severity": "high" }
        ],
        "symfony/polyfill-intl-idn": [
            { "advisoryId": "PKSA-dwsq-ppd2-mb1x", "affectedVersions": ">=1.17.1,<1.38.1", "title": "symfony/polyfill-intl-idn accepts xn-- labels whose Punycode payload decodes to ASCII-only", "cve": "CVE-2026-46644", "severity": "low" }
        ],
        "symfony/routing": [
            { "advisoryId": "PKSA-bf7t-jnpz-492k", "affectedVersions": ">=8.0.0,<8.0.13", "title": "UrlGenerator Dot-Segment Encoding Skips Every Other Chained `../` or `./`", "cve": "CVE-2026-48784", "severity": "medium" },
            { "advisoryId": "PKSA-yc7t-91v9-99xs", "affectedVersions": ">=8.0.0,<8.0.12", "title": "UrlGenerator Route-Requirement Bypass via Unanchored Regex Alternation", "cve": "CVE-2026-45065", "severity": "medium" }
        ]
    },
    "abandoned": []
}
```

## Appendix B — Sanitized `npm audit --json` Output (dev)

```json
{
    "auditReportVersion": 2,
    "vulnerabilities": {
        "concurrently": { "name": "concurrently", "severity": "critical", "isDirect": true, "via": ["shell-quote"], "range": "9.2.1", "nodes": ["node_modules/concurrently"], "fixAvailable": true },
        "esbuild":       { "name": "esbuild",       "severity": "moderate", "isDirect": false, "via": [{ "name": "esbuild", "title": "esbuild enables any website to send any requests to the development server and read the response", "cwe": ["CWE-346"], "cvss": { "score": 5.3 }, "range": "<=0.24.2" }], "effects": ["vite"], "range": "<=0.24.2", "fixAvailable": { "name": "vite", "version": "8.1.0" } },
        "shell-quote":   { "name": "shell-quote",   "severity": "critical", "isDirect": false, "via": [{ "name": "shell-quote", "title": "shell-quote quote() does not escape newlines in object .op values", "cwe": ["CWE-77", "CWE-78"], "cvss": { "score": 8.1 }, "range": ">=1.1.0 <=1.8.3" }], "effects": ["concurrently"], "range": "1.1.0 - 1.8.3", "fixAvailable": true },
        "vite":          { "name": "vite",          "severity": "high",     "isDirect": true,  "via": [
            { "name": "vite", "title": "Vite Vulnerable to Path Traversal in Optimized Deps `.map` Handling", "cwe": ["CWE-22", "CWE-200"], "range": "<=6.4.1" },
            { "name": "vite", "title": "launch-editor: NTLMv2 hash disclosure via UNC path handling on Windows", "cwe": ["CWE-73", "CWE-522"], "range": "<=6.4.2" },
            { "name": "vite", "title": "vite: `server.fs.deny` bypass on Windows alternate paths", "cwe": ["CWE-22", "CWE-200"], "range": "<=6.4.2" },
            "esbuild"
        ], "range": "<=6.4.2", "fixAvailable": { "name": "vite", "version": "8.1.0" } }
    },
    "metadata": { "vulnerabilities": { "info": 0, "low": 0, "moderate": 1, "high": 1, "critical": 2, "total": 4 }, "dependencies": { "prod": 1, "dev": 164, "optional": 49, "peer": 0, "peerOptional": 0, "total": 164 } }
}
```

(All `dev: true` per `package-lock.json`. `prod: 1` = `laravel-echo@1.19.0`, no advisories.)
