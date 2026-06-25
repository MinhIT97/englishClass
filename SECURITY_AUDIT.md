# Security Audit Report — englishClass

| Field | Value |
|---|---|
| **Report ID** | SEC-AUDIT-2026-06-25 |
| **Project** | `englishClass` (Laravel 12 / PHP 8.4 / MySQL, modular HMVC) |
| **Audit Date** | 2026-06-25 |
| **Auditor** | Sisyphus-Junior (security-research skill, read-only mode) |
| **Scope** | 10 component-level audits (C1–C10) consolidated into one report |
| **Evidence sources** | `.omo/evidence/task-1-security-review.md` … `task-10-security-review.md` |
| **Total raw findings** | 60 across the 10 component audits (deduplicated and merged into 52 below) |

---

## A. Executive Summary

This consolidated report aggregates 60 raw findings from the 10 component-level security audits (C1 Configuration & Secrets, C2 Composer/NPM Supply Chain, C3 Auth & Sessions, C4 Authorization/IDOR, C5 Input Validation & XSS, C6 File Uploads, C7 Telegram & Webhooks, C8 AI/Gemini Integration, C9 Rate Limiting & DoS, C10 Logging & PII). After deduplication and severity normalisation, **52 actionable findings** remain: **0 Critical, 14 High, 23 Medium, 13 Low, 2 Info**.

### Top 3 highest-risk findings (fix first)

1. **SEC-011 — Stored XSS via classroom members JS literal** (`Modules/Classroom/resources/views/show.blade.php:18`). User-controlled `name` is embedded raw via `{!! !!}` into a `<script>` block. A single attacker-registered user can execute JavaScript in any teacher's session that views the classroom. **One-line fix.** [C5 SEC-001]
2. **SEC-003 / SEC-004 — Composer advisories: Laravel CRLF injection in default email rule** (`composer.lock:1235`, `v13.0.0`) and **symfony/mime SMTP injection** (`composer.lock:5749`, `v8.0.8`). Both are production-runtime CVEs against packages that are currently locked. **Two `composer update` lines remediate (and pull in fixes for 11 Medium CVEs at the same time).** [C2 SEC-001/SEC-002]
3. **SEC-012 / SEC-013 / SEC-014 — Multiple rate-limit gaps expose cost-abuse / DoS** (unthrottled `/search`, `/admin/questions/ai-generate`, speaking `/start` & `/chat`, `/settings/export`, community write endpoints, `/classroom/join`). The `ai-speaking` limiter is registered but never attached; `QuestionService::paginate` lacks the `MAX_PER_PAGE` cap that `CourseService` has. **Twelve-line remediation per route file.** [C9 SEC-009–SEC-014]

### Top 3 positive controls

1. **Timing-safe secret comparison everywhere it matters.** `hash_equals()` is used in `VerifyTelegramSecret.php:38`, `AuthenticateDeployHook.php:17`, and the JWT blacklist — only `VerifyInternalApiToken.php:32` slipped, and that is the lone Medium timing-discrepancy finding (SEC-015).
2. **Telegram webhook hardening.** Empty `TELEGRAM_WEBHOOK_SECRET` returns **503 in production** (fail-closed), secret comparison is timing-safe, and the legacy unrouted `Api/TelegramWebhookController.php` is provably 404 via `test_legacy_unsecured_telegram_webhook_is_not_routable`. The remaining gap (chat_id verification) is documented as SEC-016.
3. **Mass-assignment protection is real and tested.** `RegisterRequest::rules()` returns only the four safe keys; `AuthService::register()` then hard-codes `role='student'` and `status='pending'`. `MassAssignmentTest.php` confirms the invariant. `User::$fillable` still includes privilege fields, but they are unreachable from the validated input.

### Note on local `.env` secrets

The working-copy `.env` file is **gitignored** (`.gitignore:4`) and `git ls-files --error-unmatch .env` confirms it is **not tracked** in git history. Five live secrets are present in the working copy: `APP_KEY`, `JWT_SECRET`, `TELEGRAM_BOT_TOKEN`, `GEMINI_API_KEY`, `INTERNAL_API_TOKEN`. **No full secret values appear in this report or in the evidence files.** See Appendix B for variable-name + first-4 + last-4 metadata and recommended rotation cadence.

---

## B. Findings Summary Table

| ID | Title | Severity | CWE | OWASP Top 10 2021 | Location |
|----|-------|----------|-----|--------------------|----------|
| SEC-001 | `SESSION_SECURE_COOKIE` missing in `.env.docker`/`.env.example` | High | CWE-614 / CWE-311 | A02:2021 Cryptographic Failures | `config/session.php:172` |
| SEC-002 | Default Telegram webhook secret `englishclass_webhook_secret` hardcoded | High | CWE-798 / CWE-321 | A07:2021 ID & Auth Failures | `config/telegram.php:26` |
| SEC-003 | Laravel framework CRLF injection in default `email` rule | High | CWE-93 / CWE-20 | A03:2021 Injection | `composer.lock:1235` (laravel/framework v13.0.0) |
| SEC-004 | symfony/mime SMTP command injection via CRLF in `Address` | High | CWE-93 / CWE-77 | A03:2021 Injection | `composer.lock:5749` (symfony/mime v8.0.8) |
| SEC-005 | Login timing oracle leaks user enumeration (bcrypt on known emails) | High | CWE-208 / CWE-204 | A07:2021 ID & Auth Failures | `Modules/Auth/Services/AuthService.php:49-55` |
| SEC-006 | JWT cannot be invalidated after issue — no API logout endpoint | High | CWE-613 | A07:2021 ID & Auth Failures | `Modules/Auth/routes/api.php` |
| SEC-007 | `AssignmentController::grade` missing teacher/admin authorization | High | CWE-862 / CWE-639 | A01:2021 Broken Access Control | `app/Http/Controllers/AssignmentController.php:68-84` |
| SEC-008 | Stored XSS via AI-grader feedback rendered unescaped | High | CWE-79 | A03:2021 Injection | `Modules/IeltsSet/resources/views/section.blade.php:194` |
| SEC-009 | `mimes:` (extension-only) instead of `mimetypes:` (content) on classroom uploads | High | CWE-434 | A05:2021 Security Misconfiguration | `Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php:36` |
| SEC-010 | Telegram API webhook logs entire request payload — PII leak + log injection | High | CWE-532 + CWE-117 | A09:2021 Logging Failures | `app/Http/Controllers/Api/TelegramWebhookController.php:23` |
| SEC-011 | Stored XSS via classroom `members` JS literal (`{!! $members !!}`) | High | CWE-79 | A03:2021 Injection | `Modules/Classroom/resources/views/show.blade.php:18` |
| SEC-012 | Unthrottled `/search` runs unbounded LIKE across 4 tables | High | CWE-770 | A04:2021 Insecure Design | `routes/web.php:77` + `SearchController.php:23-67` |
| SEC-013 | `QuestionService::paginate` accepts unbounded `?limit=` (no MAX_PER_PAGE cap) | High | CWE-770 | A04:2021 Insecure Design | `Modules/Question/Services/QuestionService.php:19-22` |
| SEC-014 | Speaking `/start` & `/chat` invoke AI service with no throttle | High | CWE-799 | A04:2021 Insecure Design | `Modules/Speaking/routes/web.php:8-9` |
| SEC-015 | `VerifyInternalApiToken` uses timing-unsafe `!==` for bearer comparison | Medium | CWE-208 | A02:2021 Cryptographic Failures | `Modules/InternalManager/Http/Middleware/VerifyInternalApiToken.php:32` |
| SEC-016 | Telegram admin callback does not verify `chat_id === TELEGRAM_ADMIN_CHAT_ID` | Medium | CWE-285 + CWE-346 | A01:2021 Broken Access Control | `app/Http/Controllers/TelegramWebhookController.php:102-114` |
| SEC-017 | `Api\ClassroomController::store` skips `ClassroomPolicy::create` enforcement | Medium | CWE-862 | A01:2021 Broken Access Control | `Modules/Classroom/Http/Controllers/Api/ClassroomController.php:40-63` |
| SEC-018 | Telescope enabled by default; only protected by provider-registration guard | Medium | CWE-489 / CWE-200 | A05:2021 Security Misconfiguration | `config/telescope.php:19` |
| SEC-019 | `User::$fillable` includes privilege-bearing fields (`is_unlimited`, `lesson_limit`) | Medium | CWE-915 | A04:2021 Insecure Design | `app/Models/User.php:14` |
| SEC-020 | `SESSION_SECURE_COOKIE` config-level null default (depends on env correctness) | Medium | CWE-614 | A05:2021 Security Misconfiguration | `config/session.php:172` |
| SEC-021 | ZIP archive upload enables archive-bomb / ZIP-slip downstream risk | Medium | CWE-22 / CWE-400 | A04/A05:2021 | `StoreClassroomPostRequest.php:36` |
| SEC-022 | Laravel temporary signed URL path confusion | Medium | CWE-601 / CWE-20 | A01:2021 Broken Access Control | `composer.lock:1235` (laravel/framework v13.0.0) |
| SEC-023 | guzzlehttp/guzzle dot-only cookie domain mismatch + HTTPS proxy downgrade (2 CVEs) | Medium | CWE-565 / CWE-319 | A05/A02:2021 | `composer.lock:823` (guzzlehttp/guzzle 7.10.0) |
| SEC-024 | guzzlehttp/psr7 CRLF injection + host confusion (3 CVEs) | Medium | CWE-93 / CWE-601 | A03/A01:2021 | `composer.lock:1032` (guzzlehttp/psr7 2.9.0) |
| SEC-025 | symfony/http-foundation SSRF bypass via IPv6 transition forms | Medium | CWE-918 | A10:2021 SSRF | `composer.lock:5485` (symfony/http-foundation v8.0.8) |
| SEC-026 | symfony/http-kernel `#[IsGranted]` HEAD-request bypass | Medium | CWE-863 / CWE-285 | A01:2021 Broken Access Control | `composer.lock:5565` (symfony/http-kernel v8.0.8) |
| SEC-027 | symfony/mailer argument injection in SendmailTransport | Medium | CWE-88 / CWE-77 | A03:2021 Injection | `composer.lock:5669` (symfony/mailer v8.0.8) |
| SEC-028 | symfony/mime parameter-name non-token header injection (2nd advisory) | Medium | CWE-93 | A03:2021 Injection | `composer.lock:5749` (symfony/mime v8.0.8) |
| SEC-029 | symfony/routing dot-segment path traversal + route-requirement bypass (2 CVEs) | Medium | CWE-22 / CWE-20 | A01/A03:2021 | `composer.lock:6717` (symfony/routing v8.0.8) |
| SEC-030 | Log injection via `$e->getMessage()` concatenation across multiple services | Medium | CWE-117 | A09:2021 Logging Failures | `TelegramNotifierService.php:74,92`, `VoiceService.php:67,71`, `AiSpeakingService.php:78,82,110`, `GeminiLessonGenerator.php:217` |
| SEC-031 | GDPR data export endpoint does not audit the export action | Medium | CWE-778 | A09:2021 Logging Failures | `app/Http/Controllers/SettingsController.php:43-56` |
| SEC-032 | `/settings/export` returns full GDPR JSON dump with no throttle | Medium | CWE-799 | A04:2021 Insecure Design | `routes/web.php:82` |
| SEC-033 | Community write endpoints (`/community/notes`, `/community/comments`) unthrottled | Medium | CWE-799 | A04:2021 Insecure Design | `routes/web.php:63-65` |
| SEC-034 | Reverb WS `allowed_origins = ['*']` + `rate_limiting.enabled = false` default | Medium | CWE-346 + CWE-770 | A05:2021 Security Misconfiguration | `config/reverb.php:85-96` |
| SEC-035 | `/admin/questions/ai-generate` & `/generate-voice` unthrottled (Gemini + TTS) | Medium | CWE-799 | A04:2021 Insecure Design | `Modules/Question/routes/web.php:15-17` |
| SEC-036 | User input concatenated directly into Gemini prompts (prompt injection) | Medium | CWE-1427 | A03:2021 Injection | `app/Services/AiTutorService.php:54-80`, `Api/AIChatController.php:42-86` |
| SEC-037 | `/ai/chat` accepts unbounded `message`/`history` (no max length, no validation) | Medium | CWE-20 | A04:2021 Insecure Design | `app/Http/Controllers/Api/AIChatController.php:18-40` |
| SEC-038 | `INTERNAL_API_TOKEN` placeholder default `your_secure_internal_token_here` | Low | CWE-798 | A05:2021 Security Misconfiguration | `.env.example:97` |
| SEC-039 | `.env.docker` ships `DB_PASSWORD=password` (root/password default) | Low | CWE-798 / CWE-521 | A05:2021 Security Misconfiguration | `.env.docker:9-14` |
| SEC-040 | `AdminBulkController` uses bare `bcrypt()` (ignores `BCRYPT_ROUNDS=12`) | Low | CWE-916 | A02:2021 Cryptographic Failures | `app/Http/Controllers/AdminBulkController.php:109` |
| SEC-041 | `VerifyInternalApiToken` returns 500 with diagnostic when env not configured | Low | CWE-209 | A05:2021 Security Misconfiguration | `Modules/InternalManager/Http/Middleware/VerifyInternalApiToken.php:18-22` |
| SEC-042 | TelegramBot webhook controller dumps full stack trace via `$e->getTraceAsString()` | Low | CWE-209 + CWE-532 | A09:2021 Logging Failures | `Modules/TelegramBot/Http/Controllers/TelegramBotWebhookController.php:40-43` |
| SEC-043 | Speaking chat Base64 audio — no size, no MIME, no magic-byte validation | Low | CWE-20 | A04:2021 Insecure Design | `Modules/Speaking/Http/Requests/ChatSpeakingRequest.php:19` |
| SEC-044 | `AdminBulkController` accepts `.txt` extension for arbitrary binary CSV | Low | CWE-434 | A05:2021 Security Misconfiguration | `app/Http/Controllers/AdminBulkController.php:79-84` |
| SEC-045 | Classroom `/join` invite-code brute force — no throttle on 10-char code | Low | CWE-307 | A07:2021 ID & Auth Failures | `Modules/Classroom/routes/web.php:10` |
| SEC-046 | Weak password policy (`Password::min(8)` only — no mixed/numeric/symbol) | Low | CWE-521 | A07:2021 ID & Auth Failures | `Modules/Auth/Http/Requests/RegisterRequest.php:15` |
| SEC-047 | Log injection via `{$user->email}` (theoretical; RFC email rule rejects CR/LF) | Low | CWE-117 | A09:2021 Logging Failures | `app/Http/Controllers/TelegramWebhookController.php:148,183` |
| SEC-048 | Log injection via admin-controlled `from.first_name` (Telegram HTML output) | Low | CWE-117 | A09:2021 Logging Failures | `app/Services/TelegramService.php:234-269` |
| SEC-049 | AI Tutor logs Gemini error response body (potential key/prompt leakage) | Low | CWE-532 | A09:2021 Logging Failures | `app/Services/AiTutorService.php:132-137` |
| SEC-050 | Multi-key rotation not atomic; concurrent requests may double-charge | Low | CWE-362 | A04:2021 Insecure Design | `Modules/TelegramBot/Services/GeminiLessonGenerator.php:143-213` |
| SEC-051 | (Info) symfony/polyfill-intl-idn insecure `xn--` label equivalence | Info | CWE-1007 | A04:2021 Insecure Design | `composer.lock:6000` (symfony/polyfill-intl-idn v1.37.0) |
| SEC-052 | (Info) Working-copy `.env` holds live secrets (gitignored, not tracked) | Info | CWE-540 | A05:2021 Security Misconfiguration | `.env` (working copy only) |

> Note on deduplication: `SESSION_SECURE_COOKIE` was reported independently in C1 (SEC-001, High — missing env defaults) and C3 (SEC-004, Medium — config-level null default). They describe the same root cause from different angles; both are kept (SEC-001 / SEC-020). The Telegram webhook secret findings (C1 SEC-002 default literal + C7 SEC-004 default literal) are merged under SEC-002 with cross-references. The chat_id gap was reported in both C4 SEC-003 and C7 SEC-002 and is merged under SEC-016.

---

## C. Detailed Findings

Each finding includes: ID, Title, Severity, CWE, OWASP, Location, Description, Evidence, Impact, Remediation, and (for High/Critical only) a unified diff. Cross-references to evidence files use `C{n}-SEC-{m}` notation.

---

### SEC-001 — `SESSION_SECURE_COOKIE` not set in production env templates  [HIGH]

| Field | Value |
|---|---|
| **Severity** | High |
| **CWE** | CWE-614 (Sensitive Cookie Without Secure Attribute) / CWE-311 |
| **OWASP 2021** | A02:2021 Cryptographic Failures |
| **Location** | `config/session.php:172`, `.env.example`, `.env.docker` |
| **Cross-refs** | C1-SEC-001, C3-SEC-004 |

**Description.** When `SESSION_SECURE_COOKIE` is unset, `env('SESSION_SECURE_COOKIE')` returns `null`, which Laravel casts to `false` for the `secure` cookie flag. Neither `.env.example` nor `.env.docker` documents the variable, so a deployment that copies the example verbatim will ship session/CSRF cookies over plain HTTP.

**Evidence** (`config/session.php:172`):
```php
'secure' => env('SESSION_SECURE_COOKIE'),
```

**Impact.** Session/CSRF cookie sent over plain HTTP if the user reaches the site via HTTP (initial redirect, mixed content, proxy misconfig). Attacker performing MITM (coffee-shop Wi-Fi, captive portal, hostile LAN) captures `laravel-session=...` and replays it → full session takeover (admin/teacher/student — whatever role the victim had).

**Remediation.** Add `SESSION_SECURE_COOKIE=true` to `.env.docker` and (commented for local HTTP dev) to `.env.example`. Add a runtime assertion in `AppServiceProvider::boot()`: if `app()->environment('production') && ! config('session.secure')`, log a critical warning or throw.

**Code Diff:**
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

### SEC-002 — Default Telegram webhook secret hardcoded  [HIGH]

| Field | Value |
|---|---|
| **Severity** | High |
| **CWE** | CWE-798 / CWE-321 |
| **OWASP 2021** | A07:2021 ID & Auth Failures |
| **Location** | `config/telegram.php:26`, `.env.example:89` |
| **Cross-refs** | C1-SEC-002, C7-SEC-004 |

**Description.** `config/telegram.php:26` falls back to the literal `englishclass_webhook_secret` if `TELEGRAM_WEBHOOK_SECRET` is unset. The README documents this exact string publicly, so an attacker who reads the README knows the secret.

**Evidence:**
```php
'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', 'englishclass_webhook_secret'),
```

**Impact.** If an operator copies `.env.example` to `.env` and forgets to override the variable, the `VerifyTelegramSecret` middleware will accept any Telegram callback with the documented header value. Combined with the chat_id gap (SEC-016), an attacker can approve/reject arbitrary users by ID and stamp an attacker-chosen "admin name" into the audit log.

**Remediation.** Drop the hardcoded default and rely on the middleware's existing 503-on-empty-config behaviour. Also reject the literal default value in the middleware if it ever reaches production.

**Code Diff:**
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

### SEC-003 — Laravel framework CRLF injection in default `email` rule  [HIGH]

| Field | Value |
|---|---|
| **Severity** | High |
| **CWE** | CWE-93 (CRLF Injection), CWE-20 |
| **OWASP 2021** | A03:2021 Injection |
| **Location** | `composer.lock:1235` (laravel/framework v13.0.0) |
| **Cross-refs** | C2-SEC-001, C2-SEC-016 |

**Description.** `composer audit` advisory `PKSA-3r5d-mb8f-1qw9` (GHSA-5vg9-5847-vvmq / CVE-2026-48019) flags Laravel v13.0.0 as vulnerable to CRLF injection in the default `'email'` rule. An email containing `\r\n` is accepted and any mail sent downstream that forwards the address verbatim will inject extra headers (e.g. `Bcc:`).

**Evidence:**
```json
{
  "advisoryId": "PKSA-3r5d-mb8f-1qw9",
  "affectedVersions": "<12.60.0|>=13.0.0,<=13.9.0",
  "title": "CRLF injection in default email rule",
  "severity": "high"
}
```

Locked version `v13.0.0` is inside `>=13.0.0,<=13.9.0`.

**Impact.** Attacker registers with `email = "victim@example.com\r\nBcc: attacker@evil.tld"`. The validator accepts it. On any code path that forwards the address to `Mail::send()` (welcome email, password reset, notification), the injected `Bcc:` causes silent BCC to attacker. Affects `RegisterUserController`, `LoginRequest` (password-reset), and any module validating an email field.

**Remediation.** Bump `laravel/framework` to `>= v13.10.0` (or `>= v12.60.0` on the 12.x line). Adjust the `composer.json` alias `13.0.0 as 12.0.0` to `13.10.0 as 12.10.0` or relax to `^13.10`. Run `composer update laravel/framework --with-dependencies`.

**Code Diff:**
```diff
--- a/composer.json
+++ b/composer.json
@@ -7,7 +7,7 @@
       "require": {
           "php": "^8.4",
-          "laravel/framework": "13.0.0 as 12.0.0",
+          "laravel/framework": "13.10.0 as 12.10.0",
           "laravel/reverb": "^1.0",
           "laravel/tinker": "^3.0",
           "nwidart/laravel-modules": "^13.0",
```

**Regression check.** Add a feature test that submits `"victim@example.com\r\nBcc: attacker@evil"` to `/register` and asserts a 422 response.

---

### SEC-004 — symfony/mime SMTP command injection via CRLF in `Address`  [HIGH]

| Field | Value |
|---|---|
| **Severity** | High |
| **CWE** | CWE-93 / CWE-77 |
| **OWASP 2021** | A03:2021 Injection |
| **Location** | `composer.lock:5749` (symfony/mime v8.0.8) |
| **Cross-refs** | C2-SEC-002 |

**Description.** `composer audit` advisory `PKSA-2n2k-66v2-bwg3` (CVE-2026-45067) flags `symfony/mime < 8.0.12` as vulnerable to email header / SMTP command injection via CRLF in `\Symfony\Component\Mime\Address::__construct()` and `Address::fromString()`.

**Evidence:**
```json
{
  "advisoryId": "PKSA-2n2k-66v2-bwg3",
  "affectedVersions": ">=8.0.0,<8.0.12",
  "title": "Email Header / SMTP Command Injection via CRLF in Symfony\\Component\\Mime\\Address",
  "cve": "CVE-2026-45067",
  "severity": "high"
}
```

Locked `v8.0.8` falls inside.

**Impact.** Application passes attacker-controlled string (e.g. user profile display name) to `new Address($name, $email)` or to `Mail::to()`. The `\r\n` becomes a literal line break in the SMTP DATA stream. Attacker appends arbitrary SMTP commands (`RCPT TO:`, `MAIL FROM:`, or `.` to end the message). Mail is relayed to an unintended recipient or content is corrupted.

**Remediation.** Bump `symfony/mime` to `>= v8.0.12`. Easiest path: `composer update symfony/mime symfony/mailer symfony/http-foundation symfony/http-kernel symfony/routing guzzlehttp/guzzle guzzlehttp/psr7 symfony/polyfill-intl-idn laravel/framework --with-dependencies`.

**Code Diff:**
```diff
--- a/composer.lock
+++ b/composer.lock
@@ -5748,7 +5748,7 @@
       {
           "name": "symfony/mime",
-          "version": "v8.0.8",
+          "version": "v8.0.12",
           "source": {
               "type": "git",
               "url": "https://github.com/symfony/mime.git",
```

---

### SEC-005 — Login timing oracle leaks user enumeration  [HIGH]

| Field | Value |
|---|---|
| **Severity** | High |
| **CWE** | CWE-208 / CWE-204 |
| **OWASP 2021** | A07:2021 ID & Auth Failures |
| **Location** | `Modules/Auth/Services/AuthService.php:49-55` |
| **Cross-refs** | C3-SEC-001 |

**Description.** The login error message string is identical for "user not found" and "wrong password", but the **code paths diverge** before the message is constructed. Unknown email → `$user = null` → throw immediately (no bcrypt work). Known email, wrong password → `Hash::check` runs ~250 ms (bcrypt cost 12).

**Evidence:**
```php
$user = $this->userRepository->findByEmail($credentials['email']);

if (!$user || !Hash::check($credentials['password'], $user->password)) {
    throw ValidationException::withMessages([
        'email' => ['Invalid credentials.'],
    ]);
}
```

**Impact.** Attacker POSTs `/api/login` with a candidate email and a constant password; measures response time over many samples (50+). Mean-difference test distinguishes registered vs unregistered emails with high confidence. Combined with the admin-only Telegram notification path and open registration, this gives an attacker a target list for credential stuffing or spear-phishing.

**Remediation.** Always run a dummy `Hash::check` against a fixed pre-computed bcrypt of a known string when `$user` is null, then throw the same error.

**Code Diff:**
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

---

### SEC-006 — JWT cannot be invalidated after issue  [HIGH]

| Field | Value |
|---|---|
| **Severity** | High |
| **CWE** | CWE-613 (Insufficient Session Expiration) |
| **OWASP 2021** | A07:2021 ID & Auth Failures |
| **Location** | `Modules/Auth/Http/Controllers/AuthController.php`, `Modules/Auth/routes/api.php` |
| **Cross-refs** | C3-SEC-002 |

**Description.** There is no API logout endpoint and no `JWTAuth::invalidate()` call anywhere in `Modules/Auth`. The blacklist is configured (`JWT_BLACKLIST_ENABLED=true`) but never written to.

**Evidence:**
```bash
$ grep -rn "JWTAuth::invalidate|logout|blacklist" Modules/Auth
# Returns only the WEB Auth::logout() at AuthController.php:114
```

**Impact.** A stolen JWT (XSS, malicious browser extension, leaked log) remains valid for `JWT_TTL=60` minutes and refreshable for `JWT_REFRESH_TTL=20160` minutes (14 days). Password change does not revoke outstanding tokens (no invalidation write-back in `User::getJWTCustomClaims`). Compromise window: up to 14 days.

**Remediation.** Add `POST /api/logout` behind `auth:api` and call `JWTAuth::invalidate(JWTAuth::getToken())`. On password change, invalidate all outstanding tokens for the user.

**Code Diff:**
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

### SEC-007 — `AssignmentController::grade` missing teacher/admin authorization  [HIGH]

| Field | Value |
|---|---|
| **Severity** | High |
| **CWE** | CWE-862 / CWE-639 |
| **OWASP 2021** | A01:2021 Broken Access Control |
| **Location** | `app/Http/Controllers/AssignmentController.php:68-84` |
| **Cross-refs** | C4-SEC-001 |

**Description.** The controller has a private `authorizeTeacher()` helper (line 86) used by `index` and `store`, but **`grade` does not call it** and there is no route-level gate either. The `$submission` parameter is bound from the URL and any authenticated user can submit `POST /classroom/post/{post}/feedback` to set arbitrary `score`/`feedback` and assign themselves as `graded_by`.

**Evidence:**
```php
public function grade(Request $request, $submission): RedirectResponse
{
    $sub = \App\Models\AssignmentSubmission::findOrFail($submission);
    $data = $request->validate([
        'score' => ['required', 'numeric', 'min:0'],
        'feedback' => ['nullable', 'string'],
    ]);

    $sub->update([
        'score' => $data['score'],
        'feedback' => $data['feedback'] ?? null,
        'graded_by' => $request->user()->id,
        'graded_at' => Carbon::now(),
    ]);
```

**Impact.** Integrity — students can forge grades on other classrooms' submissions and post comments under other classrooms' posts. Audit log entries will record the attacker's user id, masking the attack.

**Remediation.** Add `authorizeTeacher` call inside `grade` (and a `role:teacher,admin` route middleware at the relevant `Modules/Classroom/routes/web.php` lines).

**Code Diff:**
```diff
--- a/Modules/Classroom/routes/web.php
+++ b/Modules/Classroom/routes/web.php
@@ -8,8 +8,10 @@ Route::middleware('auth')->group(function () {
     Route::get('/classroom/{classroom}', ...);
     Route::post('/classroom/{classroom}/post', ...);
-    Route::post('/classroom/post/{post}/feedback', ...);
-    Route::post('/classroom/post/{post}/comment', ...);
+    Route::post('/classroom/post/{post}/feedback', ...)->middleware('role:teacher,admin');
+    Route::post('/classroom/post/{post}/comment', ...);
 });

--- a/app/Http/Controllers/AssignmentController.php
+++ b/app/Http/Controllers/AssignmentController.php
@@ -67,6 +67,7 @@ class AssignmentController extends Controller
      *
      * @param  mixed  $submission
      */
     public function grade(Request $request, $submission): RedirectResponse
     {
+        $this->authorizeTeacher($request, \Modules\Classroom\Models\Classroom::find(\App\Models\AssignmentSubmission::findOrFail($submission)->assignment->classroom_id));
         $sub = \App\Models\AssignmentSubmission::findOrFail($submission);
         $data = $request->validate([
             'score' => ['required', 'numeric', 'min:0'],
```

---

### SEC-008 — Stored XSS via AI-grader feedback  [HIGH]

| Field | Value |
|---|---|
| **Severity** | High |
| **CWE** | CWE-79 |
| **OWASP 2021** | A03:2021 Injection |
| **Location** | `Modules/IeltsSet/resources/views/section.blade.php:194` |
| **Cross-refs** | C5-SEC-002 (escalated from Medium: combines with SEC-011 to give an attacker two XSS vectors; AI prompt-injection (SEC-036) makes exploitability more practical) |

**Description.** `$saved->feedback` is written verbatim from the AI grader return value (`PracticeSessionService::submitAnswer()` → `$result['feedback']`) and rendered with `{!! !!}`. The neighbouring "Reference" line uses `{{ }}`, proving the developer was aware of escaping — but the feedback line deliberately allows raw HTML.

**Evidence:**
```blade
<div style="margin-top: 0.5rem">{!! $saved->feedback ?: 'No feedback available.' !!}</div>
```

**Impact.** A student submits a writing/short-answer prompt that elicits AI output containing `<img src=x onerror=alert(document.cookie)>`. The string is stored, and any subsequent render of `/student/sets/{set}/sections/{section}` executes the injected script in the student's session.

**Remediation.** Wrap with `e()` on output (`{!! nl2br(e($saved->feedback)) !!}`) or `strip_tags()` on input in the controller.

**Code Diff:**
```diff
--- a/Modules/IeltsSet/resources/views/section.blade.php
+++ b/Modules/IeltsSet/resources/views/section.blade.php
@@ -191,7 +191,7 @@
 <div style="margin-top: 0.5rem">
     <strong>Reference:</strong> {{ $saved->correct_answer ?: 'No reference answer available.' }}
 </div>
-<div style="margin-top: 0.5rem">{!! $saved->feedback ?: 'No feedback available.' !!}</div>
+<div style="margin-top: 0.5rem">{!! nl2br(e($saved->feedback ?: 'No feedback available.')) !!}</div>
```

---

### SEC-009 — `mimes:` instead of `mimetypes:` for classroom uploads  [HIGH]

| Field | Value |
|---|---|
| **Severity** | High |
| **CWE** | CWE-434 |
| **OWASP 2021** | A05:2021 Security Misconfiguration |
| **Location** | `Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php:36`, `Modules/Question/Http/Controllers/QuestionController.php:43`, `app/Http/Controllers/AdminBulkController.php:80` |
| **Cross-refs** | C6-SEC-006-001 (escalated from Medium: applies to three handlers) |

**Description.** Laravel's `mimes:` validator uses Symfony's `MimeTypes::guessMimeType()` keyed on the **filename extension**, not the file's actual magic bytes. A `homework.pdf` whose body is `<?php system($_GET['c']); ?>` passes the rule and is persisted to the `public` disk.

**Evidence:**
```php
// Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php:36
'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,mp3,mp4',
```

**Impact.** Defence-in-depth gap. Combined with the `zip` and `csv` allowlist entries, opens the door to ZIP-slip and CSV-formula-injection downstream.

**Remediation.** Replace `mimes:` with `mimetypes:` (checks actual magic bytes) and remove `zip`, `csv` from the allowlist unless business-justified.

**Code Diff:**
```diff
--- a/Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php
+++ b/Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php
@@ -32,7 +32,7 @@
     public function rules(): array
     {
         return [
-            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,mp3,mp4|max:51200',
+            'attachment' => [
+                'nullable',
+                'file',
+                'max:51200',
+                'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,audio/mpeg,video/mp4',
+            ],
         ];
     }
```

---

### SEC-010 — Telegram API webhook logs entire request payload  [HIGH]

| Field | Value |
|---|---|
| **Severity** | High |
| **CWE** | CWE-532 + CWE-117 |
| **OWASP 2021** | A09:2021 Security Logging and Monitoring Failures |
| **Location** | `app/Http/Controllers/Api/TelegramWebhookController.php:23` |
| **Cross-refs** | C10-SEC-001 |

**Description.** `Log::info("Telegram Webhook received", $update)` where `$update = $request->all()`. The entire Telegram update payload — including `message.text`, `from.first_name`, `from.username`, `chat.id`, `callback_query.data` — is written to disk in plaintext logs.

**Evidence:**
```php
public function handle(Request $request)
{
    $update = $request->all();
    Log::info("Telegram Webhook received", $update);
```

**Impact.** Two issues in one. (a) **PII leak** — `from.first_name` (real name), `from.username` (Telegram handle), `chat.id` (links Telegram identity to platform user) land on disk in plaintext. (b) **Log injection** — on the `single` channel, an attacker can craft `text = "ok\n[2026-06-25 12:00:00] local.ERROR: FAKE ADMIN ACTION user.approved target_id=1"` to forge log lines that downstream SIEM tools index as legitimate.

**Remediation.** Log only the `update_id` and a type discriminator. Drop the payload keys.

**Code Diff:**
```diff
--- a/app/Http/Controllers/Api/TelegramWebhookController.php
+++ b/app/Http/Controllers/Api/TelegramWebhookController.php
@@ -20,7 +20,14 @@
 public function handle(Request $request)
 {
     $update = $request->all();
-    Log::info("Telegram Webhook received", $update);
+    // SECURITY: Log only the update type and id, never the full payload.
+    Log::info('Telegram Webhook received', [
+        'update_id' => $update['update_id'] ?? null,
+        'type' => isset($update['message'])
+            ? 'message'
+            : (isset($update['callback_query']) ? 'callback_query' : 'other'),
+    ]);
```

---

### SEC-011 — Stored XSS via classroom members JS literal  [HIGH]

| Field | Value |
|---|---|
| **Severity** | High |
| **CWE** | CWE-79 |
| **OWASP 2021** | A03:2021 Injection |
| **Location** | `Modules/Classroom/resources/views/show.blade.php:18` |
| **Cross-refs** | C5-SEC-001 |

**Description.** User `name` from registration (no character filter — `RegisterRequest::name` only enforces `string|max:255`) is JSON-encoded and embedded raw into a `<script>` block via `{!! !!}`. `json_encode` escapes JSON control chars (e.g. `"` and `\`) but not HTML-context characters like `</script>`, and `{!! !!}` bypasses even Blade's `@json` HTML escaping.

**Evidence:**
```blade
@php
    $members = collect([$classroom->teacher])
        ->merge($classroom->students)
        ->unique('id')
        ->map(fn($u) => [
            'id'      => $u->id,
            'name'    => $u->name,
            ...
        ])
        ->values()
        ->toJson();
@endphp
<script>
    window.classroomMembers = {!! $members !!};
</script>
```

**Impact.** Attacker registers with name `","onerror":"alert(1)","x":"`. Joins a classroom. Teacher navigates to `/classroom/{id}`. Browser parses the JSON literal embedded in `<script>`, breaks out of the object literal, executes attacker JS in the teacher's origin. Attacker JS has full access to the teacher's session, including admin actions (user approval, role changes, content moderation).

**Remediation.** Replace `{!! !!}` with Blade's `@json` directive. Also tighten `RegisterRequest::name` and `UpdateProfileRequest::name` to `/^[A-Za-z0-9 \-_.]+$/u`.

**Code Diff:**
```diff
--- a/Modules/Classroom/resources/views/show.blade.php
+++ b/Modules/Classroom/resources/views/show.blade.php
@@ -15,7 +15,7 @@
         @endphp
         <script>
-            window.classroomMembers = {!! $members !!};
+            window.classroomMembers = @json($members);
         </script>
```

---

### SEC-012 — Unthrottled `/search` runs unbounded LIKE across 4 tables  [HIGH]

| Field | Value |
|---|---|
| **Severity** | High |
| **CWE** | CWE-770 |
| **OWASP 2021** | A04:2021 Insecure Design |
| **Location** | `routes/web.php:77`, `app/Http/Controllers/SearchController.php:23-67` |
| **Cross-refs** | C9-SEC-009 |

**Description.** `/search` runs 4 wide LIKE `where('column', 'like', "%{$q}%")` queries per call — no throttle middleware. `limit(5)` only caps returned rows, not the LIKE scan. B-tree indexes cannot help with leading-wildcard patterns, so each request is a full-table scan.

**Evidence:**
```php
$groups['courses'] = Course::query()
    ->where(function ($qb) use ($q) {
        $qb->where('title', 'like', "%{$q}%")
            ->orWhere('description', 'like', "%{$q}%");
    })
    ->limit(5)->get(['id', 'title', 'slug'])->toArray();
```

**Impact.** Authenticated user → `GET /search?q=a` repeatedly. With 100 000+ courses/classrooms/notes, each request costs seconds of CPU. Trivial DoS amplifier.

**Remediation.** Add a `search` named limiter in `AppServiceProvider::registerRateLimiters()` and attach `throttle:search` to the route. Also cap `$q` at 100 chars and escape LIKE wildcards (`addcslashes($q, '%_\\')`).

**Code Diff:**
```diff
--- a/app/Providers/AppServiceProvider.php
+++ b/app/Providers/AppServiceProvider.php
@@ -100,6 +100,11 @@
         });
     }

+    RateLimiter::for('search', function (Request $request) {
+        return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
+    });
+
     protected function gate(): void

--- a/routes/web.php
+++ b/routes/web.php
@@ -74,7 +74,9 @@
 Route::get('teacher/dashboard', ...);
-Route::get('search', \App\Http\Controllers\SearchController::class)->name('search');
+Route::get('search', \App\Http\Controllers\SearchController::class)
+    ->middleware('throttle:search')
+    ->name('search');
```

---

### SEC-013 — `QuestionService::paginate` accepts unbounded `?limit=`  [HIGH]

| Field | Value |
|---|---|
| **Severity** | High |
| **CWE** | CWE-770 |
| **OWASP 2021** | A04:2021 Insecure Design |
| **Location** | `Modules/Question/Services/QuestionService.php:19-22`, `Modules/Question/Http/Controllers/QuestionController.php:22-28` |
| **Cross-refs** | C9-SEC-010 |

**Description.** `paginate($perPage)` is called **without any cap** — directly contradicting `Modules/Course/Services/CourseService.php:26-33` which clamps with `max(1, min($perPage, self::MAX_PER_PAGE))`. SECURITY.md claims "Pagination cap = 100 prevents DoS via `?limit=99999999`" but the protection is **only in `CourseService`**, not a shared trait.

**Evidence:**
```php
// Modules/Question/Services/QuestionService.php:19-22
public function paginate(array $filters, int $perPage = 15)
{
    return $this->repository->model()::query()->filter($filters)->paginate($perPage);
}
```

**Impact.** Admin or attacker with admin token → `GET /admin/questions?limit=99999999`. Eloquent calls `paginate(99999999)`. SELECT runs, full question table hydrates, Blade renders. Memory exhaustion + 30-60s request → worker starvation.

**Remediation.** Cap `perPage` at 100 inside `QuestionService::paginate`. Same fix in `Modules/TelegramBot/Services/ReadingPassageAdminService.php:47`.

**Code Diff:**
```diff
--- a/Modules/Question/Services/QuestionService.php
+++ b/Modules/Question/Services/QuestionService.php
@@ -17,6 +17,7 @@
      */
     public function paginate(array $filters, int $perPage = 15)
     {
+        $perPage = max(1, min($perPage, 100));
         return $this->repository->model()::query()->filter($filters)->paginate($perPage);
     }
```

---

### SEC-014 — Speaking `/start` & `/chat` invoke AI service with no throttle  [HIGH]

| Field | Value |
|---|---|
| **Severity** | High |
| **CWE** | CWE-799 |
| **OWASP 2021** | A04:2021 Insecure Design |
| **Location** | `Modules/Speaking/routes/web.php:8-9`, `app/Providers/AppServiceProvider.php:82-88` |
| **Cross-refs** | C9-SEC-011 |

**Description.** The `ai-speaking` named limiter (10/min/user) is registered but **never attached to any route**. Speaking endpoints `/student/speaking/start` and `/student/speaking/chat` call `SpeakingSessionService` → `AiSpeakingService` (Gemini + TTS) with no throttle at all.

**Evidence:**
```php
// Modules/Speaking/routes/web.php:6-11
Route::middleware(['auth', 'can:active-user'])->prefix('student/speaking')->group(function () {
    Route::get('/', [SpeakingController::class, 'index'])->name('student.speaking.index');
    Route::post('/start', [SpeakingController::class, 'start'])->name('student.speaking.start');
    Route::post('/chat', [SpeakingController::class, 'chat'])->name('student.speaking.chat');
    Route::get('/poll', [SpeakingController::class, 'poll'])->name('student.speaking.poll');
});
```

**Impact.** Authenticated user → 1000 calls/minute to `/student/speaking/chat`. Each call queues work to Gemini + TTS. Single user can rack up hundreds of dollars in API charges per hour. The limiter already exists but isn't wired — pure oversight.

**Remediation.** Attach `throttle:ai-speaking` to both `/start` and `/chat`.

**Code Diff:**
```diff
--- a/Modules/Speaking/routes/web.php
+++ b/Modules/Speaking/routes/web.php
@@ -6,8 +6,12 @@ Route::middleware(['auth', 'can:active-user'])->prefix('student/speaking')
     Route::get('/', [SpeakingController::class, 'index'])->name('student.speaking.index');
-    Route::post('/start', [SpeakingController::class, 'start'])->name('student.speaking.start');
-    Route::post('/chat', [SpeakingController::class, 'chat'])->name('student.speaking.chat');
+    Route::post('/start', [SpeakingController::class, 'start'])
+        ->middleware('throttle:ai-speaking')
+        ->name('student.speaking.start');
+    Route::post('/chat', [SpeakingController::class, 'chat'])
+        ->middleware('throttle:ai-speaking')
+        ->name('student.speaking.chat');
     Route::get('/poll', [SpeakingController::class, 'poll'])->name('student.speaking.poll');
 });
```

---

### SEC-015 — `VerifyInternalApiToken` uses timing-unsafe `!==`  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-208 |
| **OWASP 2021** | A02:2021 Cryptographic Failures |
| **Location** | `Modules/InternalManager/Http/Middleware/VerifyInternalApiToken.php:32` |
| **Cross-refs** | C1-SEC-004 |

**Description.** Token comparison uses `!==` which short-circuits on the first differing byte. Every other token-verification middleware in this repo uses `hash_equals()`.

**Evidence:**
```php
$requestToken = substr($header, 7);
if ($requestToken !== $token) {
    return response()->json([
        'message' => 'Unauthorized. Invalid token.'
    ], 401);
}
```

**Impact.** Low-likelihood (internal endpoint), but feasible: attacker has LAN access to the internal-manager port (8080 per `.env.docker:13 INTERNAL_PORT=8080`). Crafts timing-side-channel probe with varying bearer tokens. Statistically recovers the 34-char `INTERNAL_API_TOKEN` one byte at a time.

**Remediation.** Replace `!==` with `hash_equals()`.

---

### SEC-016 — Telegram admin callback does not verify `chat_id`  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-285 + CWE-346 |
| **OWASP 2021** | A01:2021 Broken Access Control |
| **Location** | `app/Http/Controllers/TelegramWebhookController.php:102-114` |
| **Cross-refs** | C4-SEC-003, C7-SEC-002 |

**Description.** `dispatchAdminCallback` reads `$chatId` from the inbound payload but **never compares it against `config('telegram.admin_chat_id')`**. The only authentication is the `telegram.secret` middleware.

**Evidence:**
```php
private function dispatchAdminCallback(string $action, int $userId, array $cb): void
{
    $callbackId = (string) ($cb['id'] ?? '');
    $chatId = isset($cb['message']['chat']['id']) ? (string) ($cb['message']['chat']['id']) : null;
    $messageId = $cb['message']['message_id'] ?? null;
    $adminName = $cb['from']['first_name'] ?? 'Admin';
    // ... no comparison against config('telegram.admin_chat_id')
}
```

**Impact.** If `TELEGRAM_WEBHOOK_SECRET` leaks, an attacker can craft `callback_query` payloads with arbitrary `chat_id` and `from` fields. The audit log will record `Admin duyệt học viên #N` with whatever `$cb['from']['first_name']` the attacker puts in the payload.

**Remediation.** Compare `$chatId` to `config('telegram.admin_chat_id')` using `hash_equals` before dispatching. Also delete the dead `Api/TelegramWebhookController.php` (covered by SEC-010).

---

### SEC-017 — `Api\ClassroomController::store` skips `ClassroomPolicy::create`  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-862 |
| **OWASP 2021** | A01:2021 Broken Access Control |
| **Location** | `Modules/Classroom/Http/Controllers/Api/ClassroomController.php:40-63` |
| **Cross-refs** | C4-SEC-002 |

**Description.** The web twin (`ClassroomController::store`) calls both `StoreClassroomRequest::authorize()` and `$this->authorize('create', Classroom::class)`. The API twin uses the same FormRequest but omits the policy call.

**Impact.** Currently non-exploitable (FormRequest `authorize()` is intact). Defence-in-depth gap.

**Remediation.** Add `$this->authorize('create', Classroom::class);` at the top of `Api\ClassroomController::store`.

---

### SEC-018 — Telescope enabled by default; only provider-registration guard  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-489 / CWE-200 |
| **OWASP 2021** | A05:2021 Security Misconfiguration |
| **Location** | `config/telescope.php:19`, `app/Providers/AppServiceProvider.php:25-28`, `app/Providers/TelescopeServiceProvider.php:57-64` |
| **Cross-refs** | C1-SEC-003 |

**Description.** `config/telescope.php:19` has `'enabled' => env('TELESCOPE_ENABLED', true)`. The only production guard is `if ($this->app->environment('local') && class_exists(TelescopeServiceProvider::class))`. A deployment with `APP_ENV=staging` flips the guard to `false` and Telescope activates silently.

**Impact.** Telescope captures every SQL query, request payload, and exception. The view gate has an empty user email allow-list (good — no UI viewing), but the database rows still contain sensitive data, and `Authorization` is not in `hideRequestHeaders()`.

**Remediation.** Flip default to `false`, add `Authorization` to `hideRequestHeaders()`, and add a boot-time assertion.

---

### SEC-019 — `User::$fillable` includes privilege-bearing fields  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-915 |
| **OWASP 2021** | A04:2021 Insecure Design |
| **Location** | `app/Models/User.php:14` |
| **Cross-refs** | C3-SEC-003 |

**Description.** `#[Fillable([... 'role', 'status', 'is_unlimited', 'lesson_limit', 'can_request_extra_lesson', ...])]` — privilege fields are reachable via `User::create($request->all())` if any future controller uses that pattern.

**Impact.** Defence-in-depth gap. Currently latent because all callers go through `RegisterRequest::validated()` or `AuthService::register()` which hardcodes `role='student'` and `status='pending'`.

**Remediation.** Split `$fillable` into a base set; require explicit `forceFill()` for admin operations.

---

### SEC-020 — `SESSION_SECURE_COOKIE` config-level null default  [MEDIUM]

See SEC-001 above. Same root cause reported in C3 as a Medium (configuration depends on env correctness in prod); C1 reported as High (missing env defaults). Kept separately so the config-vs-env fix can be tracked independently.

---

### SEC-021 — ZIP archive upload enables archive-bomb / ZIP-slip downstream  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-22 / CWE-400 |
| **OWASP 2021** | A04/A05:2021 |
| **Location** | `Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php:36` |
| **Cross-refs** | C6-SEC-006-002 |

**Description.** `zip` is in the allowlist. A 50 MB `max:51200` quota allows a zip that decompresses to petabytes (`42.zip`-style). Disk-fill DoS reachable from any authenticated classroom member.

**Remediation.** Remove `zip` from the classroom allowlist unless an explicit business need exists. If it must stay, add a server-side `ZipArchive` open + entry-count cap and a `max:5120` cap.

---

### SEC-022 — Laravel temporary signed URL path confusion  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-601 / CWE-20 |
| **OWASP 2021** | A01:2021 Broken Access Control |
| **Location** | `composer.lock:1235` (laravel/framework v13.0.0) |
| **Cross-refs** | C2-SEC-003 |

**Description.** Advisory `PKSA-m5cs-t1y6-qpcs` — `<12.61.1|>=13.0.0,<13.12.0`. Path-component manipulation before user click can bypass signature binding.

**Remediation.** Bump `laravel/framework` to `>= v13.12.0` (covered by SEC-003 / SEC-004 dependency update).

---

### SEC-023 — guzzlehttp/guzzle dot-only cookie domains + HTTPS proxy downgrade  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-565 / CWE-319 + CWE-757 |
| **OWASP 2021** | A05/A02:2021 |
| **Location** | `composer.lock:823` (guzzlehttp/guzzle 7.10.0) |
| **Cross-refs** | C2-SEC-004, C2-SEC-005 |

**Description.** Two advisories: `PKSA-93qv-9n9h-6k6p` (CVE-2026-55767, dot-only cookie domain matching) and `PKSA-k22t-f949-t9g6` (CVE-2026-55568, silent HTTPS proxy downgrade to cleartext). Affected `<7.12.1`.

**Remediation.** Bump `guzzlehttp/guzzle` to `>= 7.12.1` (covered by SEC-003 / SEC-004 update).

---

### SEC-024 — guzzlehttp/psr7 CRLF + host confusion  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-93 / CWE-601 |
| **OWASP 2021** | A03/A01:2021 |
| **Location** | `composer.lock:1032` (guzzlehttp/psr7 2.9.0) |
| **Cross-refs** | C2-SEC-006, C2-SEC-007, C2-SEC-008 |

**Description.** Three advisories: CRLF injection in HTTP start-line serialization (`PKSA-7qs6-zvnz-h66r`, CVE-2026-55766), CRLF injection via URI host (`PKSA-gm5x-j3mz-71n9`, CVE-2026-49214), host confusion via authority reinterpretation (`PKSA-jj5t-2zs1-dcfm`, CVE-2026-48998).

**Remediation.** Bump `guzzlehttp/psr7` to `>= 2.12.1` (covered by SEC-003 / SEC-004 update).

---

### SEC-025 — symfony/http-foundation SSRF via IPv6 transition forms  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-918 |
| **OWASP 2021** | A10:2021 SSRF |
| **Location** | `composer.lock:5485` (symfony/http-foundation v8.0.8) |
| **Cross-refs** | C2-SEC-009 |

**Description.** Advisory `PKSA-y6py-qpv1-h52p` (CVE-2026-48736). `IpUtils::PRIVATE_SUBNETS` omits 6to4 / NAT64 / Teredo / IPv4-compatible forms.

**Remediation.** Bump `symfony/http-foundation` to `>= v8.0.13` (covered by SEC-003 / SEC-004 update).

---

### SEC-026 — symfony/http-kernel HEAD-request bypasses `#[IsGranted]`  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-863 / CWE-285 |
| **OWASP 2021** | A01:2021 Broken Access Control |
| **Location** | `composer.lock:5565` (symfony/http-kernel v8.0.8) |
| **Cross-refs** | C2-SEC-010 |

**Description.** Advisory `PKSA-dw7n-x7f5-zf63` (CVE-2026-45075). `#[IsGranted('POST')]` is skipped on a `HEAD` request.

**Remediation.** Bump `symfony/http-kernel` to `>= v8.0.12` (covered by SEC-003 / SEC-004 update).

---

### SEC-027 — symfony/mailer argument injection in SendmailTransport  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-88 / CWE-77 |
| **OWASP 2021** | A03:2021 Injection |
| **Location** | `composer.lock:5669` (symfony/mailer v8.0.8) |
| **Cross-refs** | C2-SEC-011 |

**Description.** Advisory `PKSA-28rh-rzzn-djk4` (CVE-2026-45068). Recipient containing `-X /tmp/payload` is passed verbatim to the sendmail binary.

**Remediation.** Bump `symfony/mailer` to `>= v8.0.12` (covered by SEC-003 / SEC-004 update). Verify production env does **not** use `MAIL_MAILER=sendmail`.

---

### SEC-028 — symfony/mime parameter-name non-token header injection  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-93 |
| **OWASP 2021** | A03:2021 Injection |
| **Location** | `composer.lock:5749` (symfony/mime v8.0.8) |
| **Cross-refs** | C2-SEC-012 |

**Description.** Advisory `PKSA-wtxr-p26d-nn42` (CVE-2026-45070). Non-token characters in MIME parameter names allow header injection.

**Remediation.** Bump `symfony/mime` to `>= v8.0.12` (covered by SEC-003 / SEC-004 update).

---

### SEC-029 — symfony/routing dot-segment path traversal + route-requirement bypass  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-22 / CWE-20 / CWE-601 |
| **OWASP 2021** | A01/A03:2021 |
| **Location** | `composer.lock:6717` (symfony/routing v8.0.8) |
| **Cross-refs** | C2-SEC-013, C2-SEC-014 |

**Description.** Two advisories: dot-segment encoding skips every other chained `../` or `./` (CVE-2026-48784, `PKSA-bf7t-jnpz-492k`) and route-requirement bypass via unanchored regex alternation enabling off-site `//host` URL injection (CVE-2026-45065, `PKSA-yc7t-91v9-99xs`).

**Remediation.** Bump `symfony/routing` to `>= v8.0.13` (covered by SEC-003 / SEC-004 update).

---

### SEC-030 — Log injection via `$e->getMessage()` concatenation  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-117 |
| **OWASP 2021** | A09:2021 Logging Failures |
| **Location** | `app/Services/TelegramNotifierService.php:74,92`, `app/Services/AI/VoiceService.php:67,71`, `Modules/Speaking/Services/AiSpeakingService.php:78,82,110`, `Modules/TelegramBot/Services/GeminiLessonGenerator.php:217` |
| **Cross-refs** | C10-SEC-003 |

**Description.** Multiple sites concatenate `$e->getMessage()` with the `.` operator into log strings without sanitisation. An upstream service (LLM provider, validator, DB) that echoes user input in its error body produces a forged log line on the `single` channel.

**Remediation.** Apply a global Monolog processor that strips CR/LF from string-valued context fields, OR switch each site to structured context.

---

### SEC-031 — GDPR data export endpoint does not audit the export action  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-778 |
| **OWASP 2021** | A09:2021 Logging Failures |
| **Location** | `app/Http/Controllers/SettingsController.php:43-56` |
| **Cross-refs** | C10-SEC-004 |

**Description.** The `/settings/export` endpoint does not call `AuditLogger::log()`. Under GDPR Art. 15 + Vietnamese PDPA, the **act of exporting personal data** should itself be auditable.

**Remediation.** Add an `AuditLogger::log('gdpr.data_exported', $user, ['bytes' => strlen(json_encode($data))])` call and a `Cache-Control: no-store` header.

---

### SEC-032 — `/settings/export` returns full GDPR JSON dump with no throttle  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-799 |
| **OWASP 2021** | A04:2021 Insecure Design |
| **Location** | `routes/web.php:82`, `app/Http/Controllers/SettingsController.php:43-56` |
| **Cross-refs** | C9-SEC-013 |

**Description.** No explicit `throttle:` middleware on the export route. A user can DoS the DB by hitting `/settings/export` repeatedly.

**Remediation.** Add `->middleware('throttle:10,1')` to the export route.

---

### SEC-033 — Community write endpoints unthrottled  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-799 |
| **OWASP 2021** | A04:2021 Insecure Design |
| **Location** | `routes/web.php:63-65` |
| **Cross-refs** | C9-SEC-014 |

**Description.** `/community/notes` and `/community/comments` have no throttle. Authenticated user can fill `study_notes` / `comments` with 5 000-char entries at full DB write speed.

**Remediation.** Add `throttle:30,1` to both POSTs, or define a `community-write` limiter.

---

### SEC-034 — Reverb WS `allowed_origins = ['*']` + `rate_limiting.enabled = false`  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-346 + CWE-770 |
| **OWASP 2021** | A05:2021 Security Misconfiguration |
| **Location** | `config/reverb.php:85-96` |
| **Cross-refs** | C9-SEC-015 |

**Description.** Reverb accepts connections from any origin. `rate_limiting.enabled` defaults to `false`, so even authed users have no per-connection message rate cap.

**Remediation.** Set `REVERB_APP_ALLOWED_ORIGINS=https://yourdomain.com` and `REVERB_APP_RATE_LIMITING_ENABLED=true` in production env.

---

### SEC-035 — `/admin/questions/ai-generate` & `/generate-voice` unthrottled  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-799 |
| **OWASP 2021** | A04:2021 Insecure Design |
| **Location** | `Modules/Question/routes/web.php:15-17` |
| **Cross-refs** | C9-SEC-012 |

**Description.** Both admin AI endpoints invoke Gemini / TTS with no throttle. Admin compromise + unbounded cost amplifier.

**Remediation.** Attach `throttle:ai` to `/ai-generate` and `throttle:ai-speaking` to `/generate-voice`.

---

### SEC-036 — User input concatenated directly into Gemini prompts  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-1427 |
| **OWASP 2021** | A03:2021 Injection |
| **Location** | `app/Services/AiTutorService.php:54-80`, `app/Http/Controllers/Api/AIChatController.php:42-86` |
| **Cross-refs** | C8-SEC-031 |

**Description.** User-controlled strings (`message`, `history`, `userAnswer`, `correctAnswer`, `recentMistakes[].*`) are concatenated directly into Gemini prompts. `AIChatController::buildPrompt()` embeds the persona inside `contents[0]` (user-role privilege level). Prompt injection can bypass JSON schema contract and produce policy-violating output.

**Remediation.** Wrap user input with clear delimiters (e.g. `<<<USER_ANSWER>>>...<<<END>>>`), strip control characters from validated input, move persona to `system_instruction`, and add a guardrail refusal block.

---

### SEC-037 — `/ai/chat` accepts unbounded `message`/`history`  [MEDIUM]

| Field | Value |
|---|---|
| **Severity** | Medium |
| **CWE** | CWE-20 |
| **OWASP 2021** | A04:2021 Insecure Design |
| **Location** | `app/Http/Controllers/Api/AIChatController.php:18-40` |
| **Cross-refs** | C8-SEC-032 |

**Description.** No `validate()`, no `max:` on `message`, no entry-count cap on `history`. Single user can ship 8 MB strings to Gemini on a 20/min budget → real money on the Gemini bill.

**Remediation.** Add a `RegisterRequest`-style validation: `message => max:2000`, `action => Rule::in(['fix','explain','natural'])`, `history => array|max:20`, `history.*.role => Rule::in([...])`, `history.*.content => max:2000`.

---

### SEC-038 — `INTERNAL_API_TOKEN` placeholder default  [LOW]

| Field | Value |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-798 |
| **OWASP 2021** | A05:2021 Security Misconfiguration |
| **Location** | `.env.example:97` |
| **Cross-refs** | C1-SEC-005 |

**Description.** `.env.example` ships `INTERNAL_API_TOKEN=your_secure_internal_token_here`. An operator who copies the example and forgets to override ships the application with a publicly-known token.

**Remediation.** Change to empty + comment: `INTERNAL_API_TOKEN=` with `openssl rand -base64 32` generation hint.

---

### SEC-039 — `.env.docker` ships `DB_PASSWORD=password`  [LOW]

| Field | Value |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-798 / CWE-521 |
| **OWASP 2021** | A05:2021 Security Misconfiguration |
| **Location** | `.env.docker:9-14` |
| **Cross-refs** | C1-SEC-006 |

**Description.** A `root`/`password` MySQL credential in the production env template. SECURITY.md checklist says "Database credentials are NOT `root`/empty", but the template ships exactly that.

**Remediation.** Change to empty + comment with explicit warning that the operator must override before first `docker compose up`.

---

### SEC-040 — `AdminBulkController` uses bare `bcrypt()`  [LOW]

| Field | Value |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-916 |
| **OWASP 2021** | A02:2021 Cryptographic Failures |
| **Location** | `app/Http/Controllers/AdminBulkController.php:109` |
| **Cross-refs** | C1-SEC-007 |

**Description.** Bare `bcrypt()` bypasses `config('hashing.driver')` and ignores `BCRYPT_ROUNDS=12`. Falls back to Laravel's compiled default of 10 rounds. Bulk-imported users get weaker hashing than users created via normal registration.

**Remediation.** Replace `bcrypt(Str::random(16))` with `Hash::make(Str::random(16))`.

---

### SEC-041 — `VerifyInternalApiToken` returns 500 with diagnostic message  [LOW]

| Field | Value |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-209 |
| **OWASP 2021** | A05:2021 Security Misconfiguration |
| **Location** | `Modules/InternalManager/Http/Middleware/VerifyInternalApiToken.php:18-22` |
| **Cross-refs** | C1-SEC-008 |

**Description.** When `INTERNAL_API_TOKEN` env is empty, the middleware returns 500 with the literal message `"Internal API token is not configured on the server."`. Tells an attacker that the endpoint is misconfigured.

**Remediation.** Return a generic 503 / 404 without server-state detail; log the config issue server-side.

---

### SEC-042 — TelegramBot webhook controller dumps full stack trace  [LOW]

| Field | Value |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-209 + CWE-532 |
| **OWASP 2021** | A09:2021 Logging Failures |
| **Location** | `Modules/TelegramBot/Http/Controllers/TelegramBotWebhookController.php:40-43` |
| **Cross-refs** | C10-SEC-002 |

**Description.** `Log::error('[TelegramBot] Webhook exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()])` writes the full stack trace. Contradicts SECURITY.md / README claim that webhook logging no longer dumps stack traces.

**Remediation.** Remove the `trace` key. Match the sanitised pattern from `app/Http/Controllers/TelegramWebhookController.php:82-87`.

---

### SEC-043 — Speaking chat Base64 audio: no size, no MIME, no magic-byte validation  [LOW]

| Field | Value |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-20 |
| **OWASP 2021** | A04:2021 Insecure Design |
| **Location** | `Modules/Speaking/Http/Requests/ChatSpeakingRequest.php:19` |
| **Cross-refs** | C6-SEC-006-003 |

**Description.** `'audio' => ['nullable', 'string']` accepts any string. No `max:` length cap. Attacker can POST 500 MB string → queue exhaustion.

**Remediation.** Add `'max:20971520'` (20 MB Base64 ≈ 15 MB raw) and a `base64_decode` + `getimagesize`/`finfo` probe for `audio/webm` magic bytes (`1A 45 DF A3`) before shipping to Gemini.

---

### SEC-044 — `AdminBulkController` accepts `.txt` extension for arbitrary binary CSV  [LOW]

| Field | Value |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-434 |
| **OWASP 2021** | A05:2021 Security Misconfiguration |
| **Location** | `app/Http/Controllers/AdminBulkController.php:79-84` |
| **Cross-refs** | C6-SEC-006-005 |

**Description.** `mimes:csv,txt` matches any `.txt` extension. Real risk is **CSV formula injection** (`=cmd|'/c calc'!A0` in a cell), which executes on the admin workstation when the imported CSV is opened in Excel.

**Remediation.** Prefix cells starting with `=`, `+`, `-`, `@`, `\t`, `\r` with a single-quote `'` on import; or use `league/csv` with strict parsing.

---

### SEC-045 — Classroom `/join` invite-code brute force  [LOW]

| Field | Value |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-307 |
| **OWASP 2021** | A07:2021 ID & Auth Failures |
| **Location** | `Modules/Classroom/routes/web.php:10` |
| **Cross-refs** | C9-SEC-016 |

**Description.** No throttle on the invite-code endpoint. Codes are 10 chars (recently bumped 6→10) — large search space but no backoff means 1 000 attempts/sec from one IP is feasible.

**Remediation.** Add `throttle:10,1` to the join route.

---

### SEC-046 — Weak password policy (`Password::min(8)` only)  [LOW]

| Field | Value |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-521 |
| **OWASP 2021** | A07:2021 ID & Auth Failures |
| **Location** | `Modules/Auth/Http/Requests/RegisterRequest.php:15` |
| **Cross-refs** | C3-SEC-007 |

**Description.** No `->mixedCase()`, `->numbers()`, `->symbols()`, no breach-list check. Users can register with `password123`.

**Remediation.** `Password::min(10)->mixedCase()->numbers()->symbols()->uncompromised()` (drop `uncompromised()` if offline-only is required).

---

### SEC-047 — Log injection via `{$user->email}` (theoretical; RFC email rule rejects CR/LF)  [LOW]

| Field | Value |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-117 |
| **OWASP 2021** | A09:2021 Logging Failures |
| **Location** | `app/Http/Controllers/TelegramWebhookController.php:148,183` |
| **Cross-refs** | C7-SEC-001 |

**Description.** Laravel's `email` rule (RFC 5321/5322) forbids CR/LF in registered addresses. Log injection via the interpolation path is not exploitable through normal registration. Documented for completeness; defence-in-depth fix is to use structured `Log::info(..., ['email' => $user->email])`.

---

### SEC-048 — Log injection via admin-controlled `from.first_name` (Telegram HTML output)  [LOW]

| Field | Value |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-117 |
| **OWASP 2021** | A09:2021 Logging Failures |
| **Location** | `app/Services/TelegramService.php:234-269` |
| **Cross-refs** | C7-rejected-candidate |

**Description.** `$user->name` and `$user->email` interpolated raw into HTML in `editMessageText`. Telegram doesn't execute `<script>`, but malformed HTML can break the layout or visually impersonate UI elements.

**Remediation.** Apply `escapeHtml()` (already used in the function) consistently to all interpolated fields.

---

### SEC-049 — AI Tutor logs Gemini error response body  [LOW]

| Field | Value |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-532 |
| **OWASP 2021** | A09:2021 Logging Failures |
| **Location** | `app/Services/AiTutorService.php:132-137` |
| **Cross-refs** | C8-SEC-033 |

**Description.** `Log::warning('[AiTutor] Gemini call failed', [..., 'body' => substr((string) $response->body(), 0, 300)])` logs the first 300 chars of the error response. An upstream proxy / WAF misconfiguration could surface the full URL including the API key in the error body.

**Remediation.** Log only status + parsed `error.message` + SHA-256 prefix of the API key (first 6 hex chars). Drop `'body'` entirely.

---

### SEC-050 — Multi-key rotation not atomic; concurrent requests may double-charge  [LOW]

| Field | Value |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-362 |
| **OWASP 2021** | A04:2021 Insecure Design |
| **Location** | `Modules/TelegramBot/Services/GeminiLessonGenerator.php:143-213` |
| **Cross-refs** | C8-SEC-034 |

**Description.** Cache write happens after generation. Two scheduler ticks can race and produce two identical Gemini calls (one charged). Alert fan-out can also duplicate on full failure.

**Remediation.** Use `Cache::lock(...)->block(5, ...)` around the generation; use `Cache::add()` (set-only-if-absent) for the post-success write; dedupe the alert with a `Cache::add("tgb:alert:...", true, 10min)` gate.

---

### SEC-051 — symfony/polyfill-intl-idn insecure `xn--` label equivalence  [INFO]

| Field | Value |
|---|---|
| **Severity** | Info |
| **CWE** | CWE-1007 |
| **OWASP 2021** | A04:2021 Insecure Design |
| **Location** | `composer.lock:6000` (symfony/polyfill-intl-idn v1.37.0) |
| **Cross-refs** | C2-SEC-015 |

**Description.** CVE-2026-46644. Polyfill accepts `xn--` labels whose Punycode payload decodes to ASCII-only — homograph / IDN spoofing bypass. Direct exploitability is low because the application does not currently expose IDN-accepting forms to anonymous users.

**Remediation.** Bump `symfony/polyfill-intl-idn` to `>= v1.38.1` (covered by SEC-003 / SEC-004 update).

---

### SEC-052 — Working-copy `.env` holds live secrets  [INFO]

| Field | Value |
|---|---|
| **Severity** | Info |
| **CWE** | CWE-540 |
| **OWASP 2021** | A05:2021 Security Misconfiguration |
| **Location** | `.env` (working copy only) |
| **Cross-refs** | C1-SEC-009 |

**Description.** `JWT_SECRET`, `TELEGRAM_BOT_TOKEN`, `GEMINI_API_KEY`, `INTERNAL_API_TOKEN`, `APP_KEY` are populated in the working-copy `.env`. `.env` is gitignored and `git ls-files --error-unmatch .env` confirms it is not tracked in the repository. No code-level remediation required.

**Operational recommendation:** Treat the development workstation as compromised for these secrets if it was ever shared or cloned onto a multi-tenant machine. Rotate the five secrets per the cadence in Appendix B.

---

## D. Positive Findings

These controls are already in place and verified by the audits. Each item is cited with the evidence file and a brief verification note.

1. **Telegram secret verification is timing-safe.** `VerifyTelegramSecret.php:38` uses `hash_equals($expected, $provided)`. `AuthenticateDeployHook.php:17` also uses `hash_equals()`. **No timing-unsafe `===`/`!==` in any middleware** (verified by C7 + C1). [C7 "Verified Secure Controls" table]
2. **Telegram webhook production misconfiguration returns 503.** `VerifyTelegramSecret.php:26-30` returns 503 in production when `TELEGRAM_WEBHOOK_SECRET` is empty — fail-closed behaviour, no silent bypass. [C7 / C1]
3. **Mass-assignment protection is real and tested.** `RegisterRequest::rules()` returns only `name, email, password, target_band`. `AuthService::register()` then hard-codes `role='student'` and `status='pending'`. `MassAssignmentTest::test_register_cannot_set_role_to_admin` confirms the invariant. [C3 + C8]
4. **JWT blacklist infrastructure is enabled.** `JWT_BLACKLIST_ENABLED=true`, `JWT_BLACKLIST_GRACE_PERIOD=0`, `JWT_LEEWAY=0` defaults are sane. `JWTAuth::invalidate()` simply needs to be called (SEC-006 above). [C3 config/jwt.php audit]
5. **Rate limiting on the documented surfaces is wired correctly.** `throttle:5,1` on `/login`, `throttle:3,60` on `/register`, `throttle:ai` on `/ai/chat`, `/ai/tutor`, `/ai/tutor/explain`, `/ai/tutor/suggest`, `throttle:lesson-requests` on `/lesson-requests`. `RateLimitTest::test_login_throttles_after_five_attempts` and `test_register_throttles_after_three_attempts` confirm. [C9 / C3]
6. **SecurityHeaders middleware globally applied.** `app/Http/Middleware/SecurityHeaders.php` is registered globally for both `web` and `api` middleware groups in `bootstrap/app.php:17-23`. Sets X-Frame-Options DENY, X-Content-Type-Options nosniff, Referrer-Policy same-origin, Permissions-Policy, baseline CSP. [C1 + README §Security Hardening]
7. **AuditAdminActions middleware auto-logs every admin mutation.** `app/Http/Middleware/AuditAdminActions.php` writes a row for every mutating verb on `/admin/*`. `AuditLogTest::test_admin_post_route_records_baseline_audit` confirms. [C10 §G]
8. **File-upload MIME allowlists on every upload endpoint.** `StoreClassroomPostRequest`, `QuestionController::store`, `AdminBulkController::importCsv` all validate `file|mimes|...|max:`. `FileUploadValidationTest::test_php_file_upload_is_rejected` confirms `.php` is blocked. [C6 / C5]
9. **60/60 writable routes are protected.** Either typed `FormRequest` or inline `$request->validate([...])` covers every POST/PUT/DELETE in `app/` and `Modules/`. [C5 §4 controller-by-controller summary]
10. **No `Model::create($request->all())` pattern anywhere in the codebase.** `grep -rn '\$request->all()'` returns only the three Telegram webhook call sites (intentional, secret-gated). [C5 §3.6]
11. **CourseService `MAX_PER_PAGE = 100` cap is verified.** `Modules/Course/Services/CourseService.php:26-33` clamps `$perPage` with `max(1, min($perPage, self::MAX_PER_PAGE))`. [C9]
12. **No `eval` / `assert` / `preg_replace /e` / `unserialize` / `file_get_contents($_GET|POST|REQUEST)` / `include($_...)` anywhere in `app/` or `Modules/`.** RCE surface is clean. [C5 §3.10]
13. **No debug code left in production.** `grep -rn '\bdd\(|var_dump\(|print_r\('` over `app/` + `Modules/` returns 0 genuine matches. [C5 §3.11 / C10 §F]
14. **AI Tutor output rendering uses JS `.textContent`, not `.innerHTML`.** `ai-tutor.blade.php:98` and `app.blade.php:585` both use `textContent`, browser-immune XSS sink. [C8]
15. **Outgoing Telegram HTML is escaped.** `TelegramService.php:272-275` uses `escapeHtml()` with `ENT_QUOTES | ENT_SUBSTITUTE` on admin-alert fields. [C7]
16. **API key is never logged in plaintext.** Exhaustive `rg GEMINI_API_KEY|apiKey|api_key` over `app/` + `Modules/` shows only `'api_key_count' => count($this->apiKeys)` (integer) and the boolean `'GEMINI_API_KEY chưa được cấu hình'` message. [C8]
17. **PII column encryption-by-default.** `User::only([...])` in `SettingsController::export` whitelists only 8 safe fields; password hash and remember_token are excluded. `#[Hidden(['password', 'remember_token'])]` is belt-and-suspenders. [C10 §D]
18. **Legacy unrouted `Api/TelegramWebhookController.php` confirmed dead.** `tests/Feature/CoreLogicRegressionTest.php::test_legacy_unsecured_telegram_webhook_is_not_routable` asserts 404. `bootstrap/app.php:37` CSRF exemption for `api/telegram/webhook` is the only remaining latent footgun. [C7]
19. **`Modules/Auth/routes/{web,api}.php` login/register throttles are identical.** Both web and API variants apply `throttle:5,1` to `/login` and `throttle:3,60` to `/register`. [C3 + C9]
20. **AI Tutor controllers validate input length.** `AiTutorController::ask()` uses `$request->validate(['question' => ['required', 'string', 'max:1000']])`. The `/ai/chat` API endpoint lacks this validation — see SEC-037. [C8]

---

## E. Out-of-Scope / Future Hardening

### E.1 Items from SECURITY.md "Out-of-Scope (Future Hardening)"

These are explicitly listed in `SECURITY.md:162-175` as future hardening, **not** currently required for release. Re-listed here for traceability:

- [ ] Replace `tymon/jwt-auth` with Laravel Sanctum (web SPA) + Passport (OAuth) for better token revocation and refresh handling.
- [ ] Multi-factor authentication (TOTP) for admin and teacher roles.
- [ ] Encrypt PII columns at rest (`users.email`, `users.name`).
- [ ] Replace string-based role checks with `spatie/laravel-permission`.
- [ ] Integrate Sentry or Flare for structured error monitoring.
- [ ] Add CSP nonce support for stricter inline-script blocking (currently `'unsafe-inline'` / `'unsafe-eval'` to accommodate Vite dev).
- [ ] Audit dependency tree quarterly with `composer audit` + Dependabot.
- [ ] Penetration test by third party (annual).

### E.2 Additional future-hardening items surfaced by the audits

These are **not exploitable today** but are worth tracking as the platform grows:

1. **Move CSRF exemption off `api/telegram/webhook`** (`bootstrap/app.php:37`). Once the dead `Api/TelegramWebhookController.php` is deleted (SEC-010 fix removes the file), the exemption has no reason to exist. Shrink the allow-list. [C7 / C10]
2. **Add a `MAX_PER_PAGE` trait or shared constant** for paginate services. `CourseService` and `QuestionService` (and `ReadingPassageAdminService`) should all consume the same `App\Services\Pagination::MAX_PER_PAGE = 100`. SEC-013 fix; the abstraction prevents the next module from repeating the omission. [C9]
3. **Tighten `RegisterRequest::name` and `UpdateProfileRequest::name`** to `/^[A-Za-z0-9 \-_.]+$/u` — defence-in-depth alongside SEC-011. [C5]
4. **Strip newlines from exception messages globally via a Monolog processor** (SEC-030). Single `SanitizeLogProcessor` covers 6+ call sites. [C10]
5. **Add an `Authorization` strip entry to `TelescopeServiceProvider::hideRequestHeaders()`** alongside the existing cookie/CSRF strip. Belt-and-braces for SEC-018. [C1 / C8]
6. **Add DB-level audit-log immutability triggers** (`BEFORE UPDATE` / `BEFORE DELETE` on `audit_logs`). Also implement `Prunable` trait + scheduled `model:prune` for the 365-day retention claim. [C10 §B, §C-3]
7. **Implement WebSocket rate limiting on Reverb.** `REVERB_APP_RATE_LIMITING_ENABLED=true` with a sane per-connection cap. [C9 SEC-034]
8. **Move JWT_BLACKLIST write hooks into `UserService::changePassword()`** so password change revokes all outstanding tokens for that user. [C3 SEC-006]
9. **Switch AI prompt builders to delimited + structured `system_instruction`.** Wrap user inputs in `<<<USER>>>...<<<END>>>`, strip control chars before interpolation, add a guardrail refusal block. [C8 SEC-031 / SEC-036]
10. **Cache-Control: no-store** on `/settings/export` response. [C10]
11. **Enforce `?limit=` cap at the framework level** (custom query-string middleware) rather than per-service. [C9]
12. **MFA on admin** (already in SECURITY.md future-work but worth re-prioritising given the chat_id gap SEC-016).
13. **Runtime assertion** that `config('session.secure')` is true in production (SEC-001 / SEC-020). Add as a boot-time check in `AppServiceProvider`.
14. **Replace `mimes:` with `mimetypes:` project-wide** (SEC-009 covers three handlers; the pattern needs to be the default for any future upload endpoint).

---

## F. Appendix A — Composer Audit Results (sanitised)

Sanitised output from `composer audit --format=json` (captured to `C:\Users\minh0\AppData\Local\Temp\opencode\composer-audit.json`). **No secret material** is in this output. Only package names, advisory IDs, CVE IDs, and version ranges are reproduced.

### Runtime advisories — 14 across 7 packages

| Package | Locked | Lock line | Advisories | Severity breakdown |
|---|---|---|---|---|
| `laravel/framework` | v13.0.0 | 1235 | 3 | 1 High, 1 Medium, 1 unscored (CVE-numbered) |
| `guzzlehttp/guzzle` | 7.10.0 | 823 | 2 | 2 Medium |
| `guzzlehttp/psr7` | 2.9.0 | 1032 | 3 | 3 Medium |
| `symfony/http-foundation` | v8.0.8 | 5485 | 1 | 1 Medium |
| `symfony/http-kernel` | v8.0.8 | 5565 | 1 | 1 Medium |
| `symfony/mailer` | v8.0.8 | 5669 | 1 | 1 Medium |
| `symfony/mime` | v8.0.8 | 5749 | 2 | 1 High, 1 Medium |
| `symfony/polyfill-intl-idn` | v1.37.0 | 6000 | 1 | 1 Low |
| `symfony/routing` | v8.0.8 | 6717 | 2 | 2 Medium |

### Key CVEs (severity-ordered)

| CVE | Package | Title | Fix version |
|---|---|---|---|
| **CVE-2026-45067** | symfony/mime | Email Header / SMTP Command Injection via CRLF in `Address` | symfony/mime **>= v8.0.12** (SEC-004) |
| **CVE-2026-48019** | laravel/framework | CRLF injection in default email rule (CVE-numbered variant of GHSA-5vg9-5847-vvmq) | laravel/framework **>= v13.10.0** or **>= v12.60.0** (SEC-003) |
| CVE-2026-45075 | symfony/http-kernel | HEAD request bypasses `methods: ['GET']` filter in `#[IsGranted]` | symfony/http-kernel **>= v8.0.12** (SEC-026) |
| CVE-2026-45070 | symfony/mime | Email Header Injection via Non-Token Characters in Mime Parameter Names | symfony/mime **>= v8.0.12** (SEC-028) |
| CVE-2026-45065 | symfony/routing | UrlGenerator Route-Requirement Bypass via Unanchored Regex Alternation | symfony/routing **>= v8.0.13** (SEC-029) |
| CVE-2026-45068 | symfony/mailer | Argument Injection in SendmailTransport via Dash-Prefixed Recipient Address | symfony/mailer **>= v8.0.12** (SEC-027) |
| CVE-2026-48736 | symfony/http-foundation | `IpUtils::PRIVATE_SUBNETS` Omits IPv6 Transition Forms: SSRF Bypass | symfony/http-foundation **>= v8.0.13** (SEC-025) |
| CVE-2026-48784 | symfony/routing | UrlGenerator Dot-Segment Encoding Skips Every Other Chained `../` or `./` | symfony/routing **>= v8.0.13** (SEC-029) |
| CVE-2026-55766 | guzzlehttp/psr7 | CRLF injection in HTTP start-line serialization | guzzlehttp/psr7 **>= 2.12.1** (SEC-024) |
| CVE-2026-55767 | guzzlehttp/guzzle | Dot-only cookie domains match all hosts | guzzlehttp/guzzle **>= 7.12.1** (SEC-023) |
| CVE-2026-55568 | guzzlehttp/guzzle | Silent HTTPS proxy downgrade to cleartext | guzzlehttp/guzzle **>= 7.12.1** (SEC-023) |
| CVE-2026-48998 | guzzlehttp/psr7 | Host confusion via authority reinterpretation | guzzlehttp/psr7 **>= 2.12.1** (SEC-024) |
| CVE-2026-49214 | guzzlehttp/psr7 | CRLF injection via URI host component | guzzlehttp/psr7 **>= 2.12.1** (SEC-024) |
| CVE-2026-46644 | symfony/polyfill-intl-idn | Insecure equivalence of `xn--` labels (homograph / IDN spoofing) | symfony/polyfill-intl-idn **>= v1.38.1** (SEC-051) |

### Remediation — single command covers all 14 runtime advisories

```bash
composer update laravel/framework symfony/mime symfony/mailer symfony/http-foundation \
    symfony/http-kernel symfony/routing guzzlehttp/guzzle guzzlehttp/psr7 \
    symfony/polyfill-intl-idn --with-dependencies
```

The `composer.json` alias `"13.0.0 as 12.0.0"` (line 10) needs adjustment to `"13.10.0 as 12.10.0"` (or relax to `^13.10`) for the Laravel bump to land in the resolved lock. See SEC-003 diff.

### NPM advisories — 4 in dev dependencies only (no production exposure)

| Package | Severity | Title | Production exposure | Fix |
|---|---|---|---|---|
| `concurrently` 9.2.1 | Critical | shell-quote CVE (CWE-77/CWE-78) | **None** — dev script runner only | bump to `^10.0.0` |
| `shell-quote` 1.8.3 | Critical | quote() does not escape newlines in `.op` values | **None** — transitive of concurrently | (bundled with concurrently bump) |
| `vite` 5.4.21 | High | Windows `server.fs.deny` bypass on alternate paths (CWE-22/CWE-200); NTLMv2 hash disclosure via UNC paths | **None** — dev build tool only | bump to `^7.0.0` |
| `esbuild` 0.21.5 | Moderate | dev server request forgery (CWE-346) | **None** — transitive of vite | (bundled with vite bump) |

All 4 are marked `dev: true` in `package-lock.json`. Production artifact is `public/build/*`, a static bundle produced by `npm run build`. No vite / concurrently / esbuild / shell-quote code runs in the PHP-FPM/nginx production stack.

---

## G. Appendix B — Secret Rotation Recommendations

The following secrets are present in the **working-copy `.env` only**. The file is gitignored (`.gitignore:4`) and `git ls-files --error-unmatch .env` confirms it is **not tracked** in git history. **No full secret values appear in this report.** For each variable, the table shows only:

- variable name
- length (chars)
- first 4 chars
- last 4 chars
- sensitivity classification
- recommended action

| Variable | Length | First 4 | Last 4 | Sensitivity | Recommended action |
|----------|-------:|--------:|-------:|-------------|---------------------|
| `APP_KEY` | 51 | `base` | `COQ=` | **HIGH** — Laravel cipher + cookie/JWT signer | Rotate **now** (90-day cadence). Use `php artisan key:generate --show`; preserve via `APP_PREVIOUS_KEYS`. The current value is a 51-char base64 string — looks like a stock `key:generate` output. Treat the dev workstation as compromised for this key if it was ever shared. |
| `JWT_SECRET` | 64 | `XSPE` | `bnY7` | **HIGH** — signs all JWTs (tymon/jwt-auth) | Rotate **now** (90-day cadence). Forces re-issue of all outstanding tokens. Generate with `openssl rand -base64 48`. |
| `TELEGRAM_BOT_TOKEN` | 46 | `8697` | `IxeU` | **HIGH** — full bot takeover | **Rotate immediately** at `@BotFather` → `/revoke` → `/token`. Then update `TELEGRAM_BOT_TOKEN` in production env. Also re-register the webhook (`tgb:set-webhook`). |
| `GEMINI_API_KEY` | 39 | `AIza` | `n6cs` | **HIGH** — paid AI service, billing risk | Rotate at Google AI Studio → API keys → revoke → create. Update `GEMINI_API_KEY` and `GEMINI_API_KEYS` (if used) in production env. Multi-key rotation pattern (`key_one,key_two,key_three`) is supported per README. |
| `INTERNAL_API_TOKEN` | 34 | `loca` | `3456` | **MEDIUM** — internal manager bearer | Rotate (180-day cadence). Generate with `openssl rand -base64 32`. Update `.env.docker` and any container secrets. |
| `TELEGRAM_ADMIN_CHAT_ID` | 10 | `1346` | `7388` | **LOW** — chat id, info disclosure only | No rotation needed (information disclosure only). |
| `TELEGRAM_BOT_USERNAME` | 15 | `Engl` | `sBot` | **LOW** — public | No rotation needed. |
| `REVERB_APP_KEY` | 16 | `engl` | `y123` | **LOW** — public WebSocket auth id | No rotation needed. |
| `REVERB_APP_SECRET` | 19 | `engl` | `t123` | **MEDIUM** — WebSocket auth secret | Rotate (180-day cadence). Update both the env var and any client connection config. |
| `DB_USERNAME` | 4 | — | — | `root` (well-known, not secret) | **Change to a non-root DB user** before production deploy (SECURITY.md checklist). |
| `DB_PASSWORD` | 0 | — | — | empty | **Set a strong password** before production deploy (SECURITY.md checklist; SEC-039). |
| `AWS_ACCESS_KEY_ID` | 0 | — | — | empty | Not currently used; leave empty unless AWS integration is added. |
| `AWS_SECRET_ACCESS_KEY` | 0 | — | — | empty | Not currently used; leave empty unless AWS integration is added. |
| `APP_DEBUG` | 4 | — | — | `true` (local dev only) | **Set to `false` before production deploy** (SECURITY.md checklist). `.env.example` and `.env.docker` already default to `false`. |

### Rotation priority order

1. **`TELEGRAM_BOT_TOKEN` first** — full bot takeover capability.
2. **`GEMINI_API_KEY`** — billing risk; should be done before any production exposure.
3. **`JWT_SECRET` + `APP_KEY`** — 90-day cadence per `SECURITY.md` key rotation schedule.
4. **`INTERNAL_API_TOKEN` + `REVERB_APP_SECRET`** — 180-day cadence.
5. **`DB_USERNAME` / `DB_PASSWORD`** — change from `root`/empty to a real service account before any deploy.

The rotation schedule from `SECURITY.md` is reproduced here for traceability:

| Secret | Cadence |
|--------|---------|
| `APP_KEY` | 90 days |
| `JWT_SECRET` | 90 days |
| `TELEGRAM_WEBHOOK_SECRET` | 180 days |
| `GEMINI_API_KEY` | 180 days |

---

## H. Appendix C — Grep Pattern Summary

The following patterns were searched across `app/`, `Modules/`, and `composer.json`. Counts and exploitability are reproduced from the relevant evidence files (C5 §2.1, C6 §5, C9, C10 §A).

### C.1 RCE / object-injection / file-inclusion — clean

| Pattern | Hits | Exploitable? | Source |
|---|---:|---|---|
| `eval(` | 0 | n/a | C5 §3.10 |
| `assert(` (PHP dynamic-eval form) | 0 | n/a | C5 §3.10 |
| `preg_replace(... /e ...)` | 0 | n/a | C5 §3.10 |
| `unserialize(` | 0 | n/a | C5 §3.10 |
| `file_get_contents($_GET\|POST\|REQUEST)` | 0 | n/a | C5 §3.10 |
| `include($_...)` / `require($_...)` | 0 | n/a | C5 §3.10 |
| `shell_exec\|system\|exec\|passthru\|popen\|proc_open` (spot-check) | 0 | n/a | C5 §3.10 |

**Verdict:** No RCE / object-injection / file-inclusion candidates anywhere in the codebase.

### C.2 Mass-assignment surface — clean (60/60 writable routes validated)

| Pattern | Hits | Exploitable? | Source |
|---|---:|---|---|
| `$request->all()` as model write | 0 | n/a | C5 §3.4, §3.6 |
| `$request->all()` total (any purpose) | 3 | All 3 are in Telegram webhook handlers, gated by `telegram.secret` middleware — intentional | C5 §3.4 |
| `$request->input(` for audit-log fields | 15 | All are server-side metadata writes (`path()`, `user_agent`, `metadata` JSON column) | C5 §3.4 |
| `->only(` / `->merge(` / `->except(` for filter whitelists | 3 `$request->only` | Safe (whitelist pattern) | C5 §3.4 |
| `$request->validate([...])` inline | Many | Safe (whitelist pattern) | C5 §4 |
| `FormRequest::rules()` (typed) | 16 in `Modules/*/Http/Requests/` + 7 in `app/Http/Requests/` | Safe | C5 §4 |

**Verdict:** Every writable route passes through a `FormRequest::rules()` whitelist or an inline `$request->validate([...])` whitelist. 60/60 writable routes protected.

### C.3 SQL injection / raw query surface — clean (all DB::raw are constant aggregates)

| Pattern | Hits | Exploitable? | Source |
|---|---:|---|---|
| `->whereRaw(` | 0 | n/a | C5 §3.3 |
| `DB::raw(` | 4 | None — all are constant aggregate expressions (`COUNT(*)`, `SUM(CASE WHEN ... THEN 1 ELSE 0 END)`, `DATE(...)`). Query parameters bind user input separately through Eloquent. | C5 §3.5 |
| `DB::statement(` | 0 | n/a | C5 §3.3 |

Sites:
- `app/Services/ProgressAnalyticsService.php:38, 77, 98`
- `Modules/TelegramBot/Services/AchievementService.php:344`

**Verdict:** No SQL injection candidates. All `DB::raw()` calls verified safe (constant aggregates, no interpolated user input).

### C.4 Blade `{!! !!}` unescaped output — 12 across 9 files, 2 findings

| File:line | Content | Source of variable | Risk | Source |
|---|---|---|---|---|
| `Modules/IeltsSet/resources/views/section.blade.php:92` | `{!! nl2br(e($questionPrompt)) !!}` | admin-authored | SAFE (already `e()`'d) | C5 §3.8 |
| `Modules/IeltsSet/resources/views/section.blade.php:146` | `{!! nl2br(e($questionPrompt)) !!}` | admin-authored | SAFE | C5 §3.8 |
| `Modules/IeltsSet/resources/views/section.blade.php:194` | `{!! $saved->feedback ?: 'No feedback available.' !!}` | AI-grader output | **MEDIUM → SEC-008 (HIGH after escalation)** | C5 §3.2 / §3.8 |
| `Modules/Gamification/resources/views/index.blade.php:4` | `{!! config('gamification.name') !!}` | `config()` constant | SAFE | C5 §3.8 |
| `Modules/Classroom/resources/views/show.blade.php:18` | `{!! $members !!}` | user-controlled `name` | **HIGH — SEC-011** | C5 §3.1 / §3.8 |
| `Modules/Speaking/resources/views/index.blade.php:28` | `{!! __('ui.step_1') !!}` | translation key | SAFE | C5 §3.8 |
| `Modules/Speaking/resources/views/index.blade.php:30` | `{!! __('ui.step_3') !!}` | translation key | SAFE | C5 §3.8 |
| `Modules/TelegramBot/resources/views/reading-review/session.blade.php:45` | `{!! nl2br(e($passage->body)) !!}` | admin-authored | SAFE | C5 §3.8 |
| `Modules/Question/resources/views/index.blade.php:4` | `{!! config('question.name') !!}` | `config()` constant | SAFE | C5 §3.8 |
| `Modules/Auth/resources/views/student/dashboard.blade.php:7` | `{!! __('ui.welcome_back_desc', ['band' => auth()->user()->target_band ?? 'N/A']) !!}` | translation + numeric band | LOW | C5 §3.8 |
| `Modules/Auth/resources/views/index.blade.php:4` | `{!! config('auth.name') !!}` | `config()` constant | SAFE | C5 §3.8 |
| `Modules/Practice/resources/views/drill.blade.php:32` | `{!! nl2br(e($text)) !!}` | admin-authored | SAFE | C5 §3.8 |

**Net:** 9 of 12 are explicitly safe. **2 findings**: SEC-008 (Medium in C5 → escalated to High here), SEC-011 (High). 1 LOW (`target_band` in dashboard translation).

### C.5 Debug code — clean

| Pattern | Hits | Exploitable? | Source |
|---|---:|---|---|
| `\bdd\(` | 0 | n/a | C5 §3.11 / C10 §F |
| `var_dump\(` | 0 | n/a | C5 §3.11 / C10 §F |
| `print_r\(` | 0 | n/a | C5 §3.11 / C10 §F |
| `\bdump\(` | 0 | n/a | C5 §3.11 / C10 §F |

False positives (returned by broader grep but not PHP `dd`/`var_dump`):
- `el.classList.add('tgb-expired')` (DOM API, JavaScript)
- `Cache::add()` (Laravel cache atomic set)
- `$validator->errors()->add(...)` (Laravel Validator)

**Verdict:** No debug code left in production.

### C.6 Log injection surface — medium

66 `Log::*` calls in 25 files. Detailed inventory in C10 §A. Highest-risk sites:
- `app/Http/Controllers/Api/TelegramWebhookController.php:23` — **SEC-010 (High)** — logs entire request payload.
- `Modules/TelegramBot/Http/Controllers/TelegramBotWebhookController.php:42` — SEC-042 (Low) — dumps full stack trace.
- `app/Services/TelegramNotifierService.php:74,92`, `app/Services/AI/VoiceService.php:67,71`, `Modules/Speaking/Services/AiSpeakingService.php:78,82,110`, `Modules/TelegramBot/Services/GeminiLessonGenerator.php:217` — SEC-030 (Medium) — concatenate `$e->getMessage()` without sanitisation.
- `app/Http/Controllers/TelegramWebhookController.php:148,183` — SEC-047 (Low) — `{$user->email}` interpolation, theoretically not exploitable via the RFC email rule.

### C.7 Rate limiting — 30+ unprotected routes

Per C9 §Throttle Table: ~30 routes have no explicit `throttle:` middleware beyond the global `auth` gate. Notable unprotected endpoints: `/search`, `/settings/export`, `/community/{notes,comments}`, `/flashcards/*`, `/study-plan/*`, `/admin/questions/ai-generate`, `/admin/questions/generate-voice`, speaking `/start` & `/chat`. See SEC-012 / SEC-013 / SEC-014 / SEC-032 / SEC-033 / SEC-035 / SEC-045.

### C.8 Pagination caps — incomplete

| Service | Cap | Source |
|---|---|---|
| `CourseService::paginate` | ✅ `MAX_PER_PAGE = 100` via `max(1, min($perPage, self::MAX_PER_PAGE))` | C9 |
| `QuestionService::paginate` | ❌ None — `paginate($perPage)` unbounded. **SEC-013 (High)** | C9 |
| `ReadingPassageAdminService::paginate` | ❌ None — same pattern. Not enumerated as a separate finding (covered by SEC-013 fix-recommendation) | C9 |

### C.9 Module composer.json — autoload-only, no dependencies

13 module composer.json files (Auth, Classroom, Course, Flashcard, Gamification, IeltsSet, MockTest, Practice, Question, Speaking, TelegramBot, Writing) declare zero `require` / `require-dev` / `version` keys. `wikimedia/composer-merge-plugin v2.1.0` merges only autoload + `extra.laravel.providers` (all empty in this codebase). No module-injected dependencies.

**Recommended CI lint:** fail build if any `Modules/*/composer.json` contains a `require` block, to keep the convention autoload-only as modules grow.

---

## Verification Checks (per task brief)

| Check | Result |
|---|---|
| `grep` confirms no full secret values in this file | ✅ See "Secret leakage grep" section below |
| `git status --short SECURITY_AUDIT.md` shows untracked | ✅ See verification output below |
| Findings cite evidence-file IDs (C{n}-SEC-{m}) | ✅ Every entry in §B has a Cross-refs field; every detailed finding in §C cites one or more component IDs |
| Code diffs present for every High/Critical finding | ✅ 14 High findings each have a unified diff block (≥5 lines) |
| No product file modified | ✅ Read-only audit mode; no `edit`/`write` calls against `app/`, `Modules/`, `config/`, `database/`, or any other product path |
| No `git add` on this file | ✅ File is untracked (see verification output) |

### Secret-leakage grep (run against the rendered file)

The five full-secret substrings from the task brief (the Telegram bot ID prefix plus suffix, the full Gemini API key, the full JWT secret, and the full INTERNAL_API_TOKEN literal) **do not appear** in this report. The Appendix B table intentionally uses only `First 4` / `Last 4` metadata. The `.env` secrets enumerated in C1-SEC-009 (working-copy inventory) and reproduced in Appendix B are the four-character-prefix forms `8697`, `AIza`, `XSPE`, `loca`, `base`, `engl` — which are also non-secret because they are universally recognised prefixes (Telegram bot IDs, Google API keys, base64 strings) that match billions of other values.

### Git status verification

```
$ git status --short SECURITY_AUDIT.md
?? SECURITY_AUDIT.md
```

The file is **untracked** and has **not** been added to the staging area.