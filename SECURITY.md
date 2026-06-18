# Security Policy

**Last reviewed**: 2026-06-19
**Scope**: englishClass Laravel application (master branch)

---

## Reporting a Vulnerability

If you discover a security issue, please email **security@example.com**
(or open a private GitHub security advisory). Do **not** file a public
issue until we've had a chance to triage.

We aim to acknowledge reports within 48 hours and ship a fix within 14
days for critical issues.

---

## Security Architecture (2026-06 hardening pass)

### Authentication

- Web session auth via Laravel's default guard (`web`).
- API auth via JWT (`tymon/jwt-auth`). Tokens carry `role` and `status`
  claims for quick authorization checks.
- Passwords hashed with bcrypt (12 rounds by default; see
  `BCRYPT_ROUNDS`).
- Login & register endpoints are throttled (5/min for login, 3/hour for
  register) per IP.

### Authorization

- Role-based with three values: `admin`, `teacher`, `student` (see
  `App\Enums\UserRole`).
- Gates defined in `App\Providers\AppServiceProvider`:
  - `admin-access` — role === admin
  - `active-user` — status === active
- FormRequests enforce role checks at the validation layer
  (e.g. `CourseRequest::authorize()` rejects students).
- Controllers do a defense-in-depth role check after `authorize()`
  passes.

### Lesson Quota

See `App\Services\LessonQuotaService`:

- Admins (`role=admin`) and users with `is_unlimited=true` bypass all
  limits.
- Other users are capped at `users.lesson_limit` lessons/day per type
  (course, classroom, daily lesson).
- Users can request quota bumps via `LessonRequest`; admins approve via
  `/admin/lesson-requests`.

### Audit Logging

See `App\Services\AuditLogger` and `App\Models\AuditLog`:

- Every mutating admin route (`/admin/*`) is auto-logged via the
  `audit.admin` middleware with `action='admin.route.{verb}'`.
- Controllers call `AuditLogger::log()` for sensitive actions
  (user approval, lesson quota approval/rejection).
- Rows are append-only (`UPDATED_AT = null` on the model).
- Retention: prune rows older than 365 days via scheduled
  `model:prune`.

### Rate Limiting

Centralised in `App\Providers\AppServiceProvider::registerRateLimiters()`:

| Limiter name     | Limit         | Use                       |
|------------------|---------------|---------------------------|
| (default)        | 5/min (login) | /login POST               |
| (default)        | 3/hour        | /register POST            |
| `ai`             | 20/min/user   | /ai/chat                  |
| `ai-speaking`    | 10/min/user   | (reserved)                |
| `lesson-requests`| 3/hour/user   | POST /lesson-requests     |
| `webhook`        | 120/min/IP    | (reserved)                |

### Telegram Webhook Security

`Modules\TelegramBot\Http\Middleware\VerifyTelegramSecret`:

- Verifies the `X-Telegram-Bot-Api-Secret-Token` header using
  `hash_equals()` (timing-safe).
- Rejects with **503** in production when `TELEGRAM_WEBHOOK_SECRET` is
  empty (prevents accidental misconfiguration).
- Logs secret mismatch attempts.

### Security Headers

`App\Http\Middleware\SecurityHeaders` (applied globally to web + API):

- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: same-origin`
- `Permissions-Policy` (geolocation/microphone/camera restrictions)
- `Content-Security-Policy` baseline

### File Uploads

- `StoreClassroomPostRequest` restricts MIME types to safe extensions
  (jpg, png, pdf, doc, zip, mp4...) with a 50 MB cap.
- Files stored under `classroom_attachments/{classroom_id}/...` on the
  `public` disk (not webroot).

### Session & Cookies

- `SESSION_DRIVER=database` (rows in `sessions` table).
- `SESSION_HTTP_ONLY=true` and `SESSION_SAME_SITE=lax` are defaults.
- `SESSION_SECURE_COOKIE` should be `true` in production.

---

## Production Deployment Checklist

Before going live, verify each of these is correctly set:

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` set (run `php artisan key:generate`)
- [ ] `APP_URL` uses `https://`
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `SESSION_SAME_SITE=lax` (or `strict` for high-sensitivity apps)
- [ ] `TELEGRAM_WEBHOOK_SECRET` is a random 32+ char string
- [ ] `GEMINI_API_KEY` is the production key, rotation list configured
- [ ] Database credentials are NOT `root`/empty
- [ ] `JWT_SECRET` distinct from `APP_KEY`
- [ ] Telescope is **disabled** in production (it's only registered when
      `APP_ENV=local`)
- [ ] File storage disk for uploads is NOT the webroot
- [ ] `composer audit` reports zero known vulnerabilities
- [ ] Firewall blocks MySQL/Redis ports from public internet
- [ ] HTTPS-only with HSTS preload
- [ ] Backups verified (DB + storage)

---

## Key Rotation Schedule

| Secret                  | Cadence   | Notes                       |
|-------------------------|-----------|-----------------------------|
| `APP_KEY`               | 90 days   | Use `key:generate --show` and `APP_PREVIOUS_KEYS` to preserve encrypted data |
| `JWT_SECRET`            | 90 days   | Forces re-issue of all tokens |
| `TELEGRAM_WEBHOOK_SECRET` | 180 days | Update both in env AND on Telegram webhook config |
| `GEMINI_API_KEY`        | 180 days | Rotate via Google AI Studio console |

---

## Vulnerability History

| Date       | Finding                                | Fix                              |
|------------|----------------------------------------|----------------------------------|
| 2026-06-19 | CourseRequest::authorize()=true        | Role-based check                 |
| 2026-06-19 | Telegram webhook silently disabled     | 503 on production misconfig      |
| 2026-06-19 | Login/register unprotected             | throttle middleware              |
| 2026-06-19 | No audit trail for admin actions       | AuditLogger + middleware         |
| 2026-06-19 | No MIME validation on uploads          | mimes:jpg,png,pdf,... rule       |
| 2026-06-19 | Unbounded ?limit= pagination           | CourseService::MAX_PER_PAGE=100  |

---

## Out-of-Scope (Future Hardening)

These are **not** in the current build but should be tackled in the next
quarter:

- [ ] Replace `tymon/jwt-auth` with Laravel Sanctum (web SPA) + Passport
      (OAuth) for better token revocation and refresh handling.
- [ ] Multi-factor authentication (TOTP) for admin and teacher roles.
- [ ] Encrypt PII columns at rest (`users.email`, `users.name`).
- [ ] Replace string-based role checks with `spatie/laravel-permission`.
- [ ] Integrate Sentry or Flare for structured error monitoring.
- [ ] Add CSP nonce support for stricter inline-script blocking.
- [ ] Audit dependency tree quarterly with `composer audit` + Dependabot.
- [ ] Penetration test by third party (annual).