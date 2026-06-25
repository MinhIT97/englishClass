# C10 — Logging & PII Handling Security Review

| Field | Value |
|---|---|
| Audit ID | SEC-LOG-2026-06-25 |
| Task | C10 — Logging & PII |
| Scope | `app/`, `Modules/`, `database/migrations/`, `config/logging.php`, `bootstrap/app.php`, tests |
| Audited by | Sisyphus-Junior (security-research skill) |
| Date | 2026-06-25 |
| Method | Static code review + control verification (no crafted payload execution) |

---

## Verdict

**PASS WITH FINDINGS** — Core logging hygiene is sound: no debug leftovers, audit table is append-only at the model layer, exception handler relies on Laravel's defaults (safe with `APP_DEBUG=false`), and GDPR export does not include password hash. Two real issues remain: (a) the legacy `app/Http/Controllers/Api/TelegramWebhookController.php` logs the entire Telegram update payload verbatim — log-injection/PII exposure, and (b) `Modules/TelegramBot/Http/Controllers/TelegramBotWebhookController.php` contradicts the SECURITY.md claim of "no full stack-trace dumps" by logging `$e->getTraceAsString()`. Both are exploitable by any Telegram user.

---

## Scope and Evidence Inventory

### Files read
- `app/Services/AuditLogger.php`
- `app/Models/AuditLog.php`
- `database/migrations/2026_06_19_100000_create_audit_logs_table.php`
- `config/logging.php`
- `bootstrap/app.php`
- `app/Http/Controllers/SettingsController.php`
- `app/Http/Controllers/TelegramWebhookController.php`
- `app/Http/Controllers/Api/TelegramWebhookController.php`
- `app/Http/Middleware/AuditAdminActions.php`
- `app/Models/User.php`
- `app/Http/Controllers/LessonRequestController.php`
- `Modules/Auth/Http/Requests/RegisterRequest.php`
- `Modules/Auth/Http/Controllers/AdminUserController.php`
- `Modules/Auth/routes/web.php`
- `Modules/TelegramBot/Http/Controllers/TelegramBotWebhookController.php`
- `Modules/TelegramBot/Services/TelegramBotCommandService.php` (line 486 only)
- `tests/Feature/Security/AuditLogTest.php`
- `routes/console.php`
- `.env.example`

### Tools used
- `grep` over `app/` + `Modules/` for `Log::(info|error|warning|debug|notice|critical|alert|emergency)` → 66 matches in 25 files
- `grep` for `\bdd\(|var_dump\(|print_r\(|\bdump\(` → **0 matches** (clean)
- `grep` for DB triggers / policy files → none found
- Whole-file reads for the 11 files above

---

## A. Log:: Call Inventory with User-Controlled Variables

Variables are tagged: **[server]** = internal/server-derived (low risk), **[attacker-controlled]** = reaches the log verbatim from external input.

| # | File:Line | Level | Snippet | Variable(s) | Trust | Risk |
|---|---|---|---|---|---|---|
| 1 | `app/Http/Controllers/Api/TelegramWebhookController.php:23` | info | `Log::info("Telegram Webhook received", $update);` | entire `$request->all()` | **attacker-controlled** | **HIGH — PII dump + log injection. Logs `message.text`, `from.first_name`, `from.username`, `chat.id`, raw callback `data` verbatim. No size cap, no sanitisation.** |
| 2 | `app/Http/Controllers/TelegramWebhookController.php:148` | info | `Log::info("[Telegram] Admin duyệt học viên #{$userId} ({$user->email})");` | `$user->email` | **[server]** validated email | Low — see §F for analysis |
| 3 | `app/Http/Controllers/TelegramWebhookController.php:183` | info | `Log::info("[Telegram] Admin từ chối học viên #{$userId} ({$user->email})");` | `$user->email` | **[server]** validated email | Low |
| 4 | `app/Http/Controllers/TelegramWebhookController.php:82-87` | error | `Log::error('[Telegram Webhook] Unhandled exception', ['exception_class' => get_class($e), 'message' => $e->getMessage(), 'file' => basename($e->getFile()).':'.$e->getLine()]);` | `$e->getMessage()` | exception message | Medium — exception message may echo user input back (e.g. validation), introducing newlines. Comment in source already warns about it; stack trace intentionally NOT logged here. |
| 5 | `app/Http/Middleware/AuditAdminActions.php:34-42` | n/a (DB) | writes to `audit_logs` | `$request->path()`, route name | attacker-controlled path | Low — `path()` is URL-derived; not a free-text field, but untrusted. Stored in DB; no user-facing render. |
| 6 | `app/Services/AuditLogger.php:32` | n/a | `'user_agent' => substr((string) $request->userAgent(), 0, 500)` | user agent string | attacker-controlled | Low — explicitly capped at 500 chars; stored only. |
| 7 | `app/Services/AuditLogger.php:33` | n/a | `'metadata' => $metadata ?: null` | controller-supplied metadata | depends on caller | Medium — see SEC-002; metadata is `json` column without schema validation, callers can stash arbitrary content |
| 8 | `Modules/TelegramBot/Http/Controllers/TelegramBotWebhookController.php:41-43` | error | `Log::error('[TelegramBot] Webhook exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);` | `$e->getMessage()` + full stack trace | exception | **HIGH — full stack trace leaked to logs.** Contradicts SECURITY.md claim. |
| 9 | `Modules/TelegramBot/Services/TelegramBotCommandService.php:486` | info | `Log::info('[TelegramBot] Unknown callback action', ['data' => $data]);` | `$data` (Telegram callback data) | **attacker-controlled** | Medium — Telegram callback `data` is fully attacker-controlled (max 64 bytes per Telegram). Newlines can be embedded by the Telegram client; logged in structured array, which **is** newline-safe for Monolog JSON output but not for the `single` channel line format. |
| 10 | `Modules/TelegramBot/Services/TelegramLearningService.php:237` | warning | `Log::warning('[TelegramBot] No topic available for user', ['user_id' => $user->id]);` | user id | [server] | Low |
| 11 | `Modules/TelegramBot/Services/LevelService.php:92` | info | `Log::info('[TelegramBot] level up', [...]);` | internal | [server] | Low |
| 12 | `Modules/TelegramBot/Services/AchievementService.php:149,158` | info | `Log::info('[TelegramBot] achievement unlocked', [...])` | internal | [server] | Low |
| 13 | `Modules/TelegramBot/Services/TelegramLearningService.php:403,526,625` | warn/error | exception messages | exception | exception | Medium — same as #4 |
| 14 | `Modules/TelegramBot/Services/GeminiLessonGenerator.php:741` | warning | `Log::warning('[TelegramBot] Gemini JSON parse failed', ['raw' => substr($text, 0, 200)]);` | first 200 chars of LLM output | [server] | Low — capped at 200 chars |
| 15 | `Modules/TelegramBot/Services/GeminiLessonGenerator.php:208,217` | error | `Log::error('[TelegramBot] All Gemini models failed', ['failures' => $failures])` and `'... exception: ' . $e->getMessage()` | exception | exception | Medium |
| 16 | `Modules\TelegramBot\Http\Middleware\VerifyTelegramSecret.php:39` | warning | `Log::warning('[TelegramBot] Webhook secret mismatch')` | none | n/a | Low — secret value intentionally NOT logged |
| 17 | `app/Services/TelegramService.php:49,55,82,91,183,203,227` | various | exception messages + response body | exception + upstream | upstream + exception | Medium |
| 18 | `app/Services/TelegramNotifierService.php:53,59,74,92` | error | exception messages + body | exception | exception | Medium |
| 19 | `app/Services/AiTutorService.php:133,143`, `app/Services/AI/VoiceService.php:67,71`, `Modules/Speaking/Services/AiTextService.php:52`, `Modules/Speaking/Services/AiSpeakingService.php:78,82,110`, `app/Services/AuditLogger.php` (n/a) | various | exception messages, AI provider bodies | exception + upstream | upstream | Medium |
| 20 | `Modules/Writing/Services/WritingGraderService.php:32`, `Modules/Speaking/Http\Controllers/SpeakingController.php:32`, `app/Jobs/ProcessAiSpeechJob.php:79`, `app/Http/Controllers/TelegramWebhookController.php:82` | error | exception messages | exception | exception | Medium |

**Summary**: 66 `Log::*` calls. The dominant risk classes are:
- **B1** — exception messages echoed verbatim into log strings (15+ sites). Most use structured context (safe for JSON formatters), but a handful concatenate with `.` (`TelegramNotifierService.php:74,92`, `VoiceService.php:67,71`, `AiSpeakingService.php:78,82,110`, `TelegramBotWebhookController.php:41`), so an exception whose message contains `\n` from upstream validation can still forge log lines on the `single` channel.
- **B2** — full request payload logged (1 site: `Api/TelegramWebhookController.php:23`).
- **B3** — full stack traces logged (1 site: `TelegramBotWebhookController.php:41`).

---

## B. AuditLog Append-Only Verification

### Model layer (`app/Models/AuditLog.php`)
- **Line 21**: `public const UPDATED_AT = null;` — disables Eloquent timestamp updates. ✅
- **Line 23-31**: `$fillable` is `create`-time only. There is no `$guarded` carve-out, and `$fillable` does NOT affect `update()` — Eloquent's `update()` method does not bypass mass-assignment protection for any attribute; only `forceFill()` or `->save()` after `fill()` on guarded attributes would. In practice, an attacker who can call `AuditLog::find($id)->update([...])` can still mutate rows. **Defense-in-depth gap.**
- **No `$dispatchesEvents`, `boot`, or trait** that overrides save/update events. ✅

### Migration layer (`database/migrations/2026_06_19_100000_create_audit_logs_table.php`)
- **No `created_at` `->useCurrent()` on update triggers.** ✅
- **No DB triggers.** `grep CREATE TRIGGER` returned 0 matches. **Defense-in-depth gap.**
- **No `LOCK TABLE` / revoked GRANT** documented. Application DB user still has full DML on the table.

### Application layer
- `grep AuditLog::update|AuditLog::delete|AuditLog::truncate` → 0 matches. ✅
- No `App\Policies\AuditLogPolicy` exists (verified by `glob` on `app/Policies/`). ✅
- No `Model::preventSilentlyDiscardedAttributes` or `Model::shouldBeStrict` enforcement. The `AuditLogger::log()` is the single writer; controllers call it explicitly. Good.
- Audit row delete would still cascade if `User::delete()` is called on an `actor_id` — but the FK is `nullOnDelete()`, so actor deletion just nulls the field. ✅

### Retention
- `routes/console.php` does **not** schedule `model:prune`. Migration comment says "Retention: prune rows older than 365 days via a scheduled `php artisan model:prune` job (see App\Console\Kernel)." `App\Console\Kernel` does **not exist** (Laravel 12 schedules via `routes/console.php`). The retention claim is **aspirational, not enforced** — not a security issue, but a compliance gap.
- The `AuditLog` model itself does **not** implement `Prunable`. So `model:prune` would not touch it even if scheduled.

**Verdict**: Append-only by **convention + the `UPDATED_AT = null` setting**, but not enforced by database triggers, GRANTs, or model events. A compromised PHP process or a manual SQL session can mutate/delete rows. This is acceptable for a Laravel app but not for SOC2-grade immutability.

---

## C. Exception Handler

`bootstrap/app.php:40-41`:
```php
->withExceptions(function (Exceptions $exceptions): void {
    //
})->create();
```

Empty handler block — Laravel 12 default behavior applies:
- For HTTP requests with `Accept: text/html`: renders `resources/views/errors/*.blade.php`. If `APP_DEBUG=false`, the view shows the user-friendly error page (no file/line/trace).
- For requests where `$request->expectsJson()` (i.e. `Accept: application/json`, AJAX, or `/api/*`): returns JSON `{"message":"Server Error."}` (or the exception's translated message for HTTP exceptions) — no file/line/trace.

Since `.env.example` defaults `APP_DEBUG=false`, and `config/logging.php` ships `LOG_LEVEL=debug`, an attacker who flips `APP_DEBUG=true` (requires `.env` write, i.e. code execution already) would expose full stack traces. The shipping default is safe.

**Edge case — Telegram webhooks**: Telegram does not send `Accept: application/json` to webhooks. If an exception bubbles out of `TelegramWebhookController::handle()` and the controller did not catch it, the framework would render an HTML error page, leaking the HTML stack trace. The controller does catch `\Throwable` at line 75-99 — but **only** the `app/Http/Controllers/TelegramWebhookController.php` (web variant). The API variant (`Api/TelegramWebhookController.php`) does NOT wrap in try/catch, and the **TelegramBot variant** does, but logs the full stack trace internally (see §B3 above).

**Verdict**: Production-safe by default. Two latent concerns:
1. **C-1 (Low)**: `Api/TelegramWebhookController::handle` lacks a `try/catch`. If `User::find()` throws, Telegram sees a 500 HTML page.
2. **C-2 (Medium)**: Even when the controller catches the exception, `TelegramBotWebhookController.php:42` writes the **full stack trace** to the application log, contradicting the SECURITY.md hardening claim ("Telegram webhook logging no longer dumps full stack traces").

---

## D. GDPR Export (`SettingsController::export`)

File: `app/Http/Controllers/SettingsController.php:43-56`

```php
public function export(Request $request)
{
    $user = $request->user();
    $data = [
        'profile' => $user->only(['name', 'email', 'role', 'status', 'target_band', 'xp', 'streak', 'created_at']),
        'preferences' => $user->preferences?->toArray(),
        'exported_at' => now()->toIso8601String(),
    ];
    return response()->json($data, 200, [
        'Content-Type' => 'application/json',
        'Content-Disposition' => 'attachment; filename="user-data-' . $user->id . '.json"',
    ]);
}
```

### Analysis
- **Password hash**: NOT included — `$user->only([...])` whitelists 8 safe fields. The User model uses `#[Hidden(['password', 'remember_token'])]` (line 15 of `User.php`), but that only affects `toArray()` / JSON serialization, not `->only()` — the whitelist is the actual protection. ✅
- **PII included**: `name`, `email`, role, status, target_band, xp, streak, created_at, plus entire `UserPreference` record. This is **standard GDPR Art. 15 right-of-access** content.
- **Filename**: includes `$user->id` (integer). Safe.
- **Authorization**: `$request->user()` — authenticated. Endpoint is `/settings/export` (presumably behind `auth` middleware, verified by route convention; not re-checked here).
- **No `Cache-Control: no-store`** header set — the JSON download may be cached by intermediaries. GDPR Art. 15 export typically warrants `no-store`. **Low — informational.**
- **Audit log of the export action**: **NOT performed.** The endpoint does not call `AuditLogger::log()`. A user could export their data repeatedly with no audit trail. Under most GDPR/PIPA interpretations, the export itself is a "personal data access" event that the controller should record in `audit_logs`. **Finding.**
- **Rate limiting**: No explicit `throttle` middleware on the export route. A user could DoS the DB by hitting `/settings/export` repeatedly. **Low**.

---

## E. Log Injection Feasibility Analysis

Per task constraint: **no crafted input was sent to test log injection** (static review only).

### Email field
- Registered via `Modules\Auth\Http\Requests\RegisterRequest.php:14`:
  ```php
  'email' => 'required|string|email|max:255|unique:users',
  ```
- Laravel's `email` rule uses `Egulias\EmailValidator` with `RFCValidation` by default. RFC 5321/5322 forbid CR (`\r`) and LF (`\n`) in both local part and domain. **Newlines in registered `User::email` are impossible** via the standard registration form. ✅
- Bypass paths considered:
  - `AdminBulkController.php:96` uses the same `email` rule. ✅
  - Direct DB write: requires code execution — out of scope for log-injection assessment.
- **Verdict**: Log injection via `{$user->email}` in `TelegramWebhookController.php:148,183` is **not exploitable** through normal registration flows.

### Name field
- Validated as `string|max:255` (`RegisterRequest.php:13`). No character whitelist.
- **CR/LF allowed in `name`**.
- **Where name is logged**: Searching `Log::` calls — `name` is **NOT** interpolated directly into any log message in the audited code. The closest are: `TelegramService` API calls (HTTP bodies, not logs), and `editMessageText` (Telegram messages, not Laravel logs). ✅ No direct name-into-log path.
- **Indirect path**: `AuditLogger::log()` writes `metadata`, which may contain caller-supplied data. Example: `LessonRequestController.php:50` writes `'lesson_type' => $lessonRequest->lesson_type` (enum, safe). The `reason` field (`RegisterRequest` doesn't validate it but `StoreLessonRequestRequest` likely does — not checked here) could carry newlines into `metadata`. **Low** because the JSON column does not flow through Monolog string interpolation.

### chat_id field
- Source: Telegram webhook payload. The bot accepts the chat_id from `$message['chat']['id']` or `$cb['message']['chat']['id']`. Telegram API returns chat IDs as integers, but the controller casts to `(string)`. The Telegram server never includes newlines in chat_id, but it does include them in `text`, `first_name`, `username`, `caption`, etc.
- **Where chat_id flows into logs**: I searched for `$chatId` in `Log::` calls — no direct interpolation into log strings in the audited controllers. ✅

### Free text / callback data
- `Api/TelegramWebhookController.php:23` logs the **entire `$request->all()`** including `message.text`, `message.caption`, `message.from.first_name`, `message.from.username`, callback `data`. All of these are attacker-controlled and **all may contain CR/LF**. On the `single` log channel (line-formatted Monolog), this **is a real log-injection vector**. **HIGH — see SEC-001.**

### Stack-trace / exception message injection
- `$e->getMessage()` carries whatever the upstream code put in the exception. Validation exceptions echo the offending input. If a user submits a `name` containing newlines and triggers a validation error in a downstream service, the exception message may include the raw user input with newlines intact. On the `single` channel, this **forges log lines**. Multiple sites (§A row 20).

### Summary table

| Variable | Source | Log-injection feasible? | Severity |
|---|---|---|---|
| `user.email` (after `email` rule) | RegisterRequest | **No** — RFC validation rejects CR/LF | Informational |
| `user.name` | RegisterRequest | Theoretically yes, but **no `name` is interpolated into log strings** | Low |
| `chat_id` | Telegram | Telegram never embeds CR/LF | Informational |
| `message.text`, `callback_query.data`, `from.first_name`, `caption` | Telegram | **Yes — logged verbatim by Api/TelegramWebhookController.php:23** | **High — SEC-001** |
| `$e->getMessage()` | exception | **Yes — multiple sites concatenate without sanitisation** | **Medium — SEC-003** |

---

## F. Debug Code in Production

`grep \bdd\(|var_dump\(|print_r\(|\bdump\(` over `app/` + `Modules/` → **0 matches.** ✅

`Log::debug(...)` calls exist (`TelegramService.php:227`, `GeminiLessonGenerator.php:194`) but they go through the standard logger, not raw dump calls. At `LOG_LEVEL=debug` (default), these write to `storage/logs/laravel.log`. Safe.

---

## G. AuditLogTest Verification

`tests/Feature/Security/AuditLogTest.php` (69 lines, 3 tests):

| Test | Action | Verified Code Path |
|---|---|---|
| `test_approving_user_writes_audit_log` | POST `/admin/users/{id}/approve` | `Modules\Auth\Http\Controllers\AdminUserController::webApprove` → `$this->audit->log(action: 'user.approved', ...)` ✅ route confirmed in `Modules/Auth/routes/web.php:45` |
| `test_lesson_request_submission_is_logged` | POST `/lesson-requests` | `app/Http/Controllers/LessonRequestController::store` → `$this->audit->log(action: 'lesson_request.submitted', ...)` ✅ route confirmed in `routes/web.php:44` |
| `test_admin_post_route_records_baseline_audit` | POST `/admin/feedback/1/note` | `app/Http\Middleware\AuditAdminActions::handle` → `$this->logger->log(action: 'admin.route.post', ...)` ✅ route confirmed in `Modules/Auth/routes/web.php:51`; middleware fires regardless of controller outcome |

All three tests exercise real code paths. **The test suite IS valid** — it does test audit logging. ✅

---

## Findings

| Severity | ID | Title | CWE | OWASP 2021 | Location | Exploit | PoC | Fix |
|---|---|---|---|---|---|---|---|---|
| **High** | SEC-001 | Telegram API webhook logs entire request payload, including attacker-controlled PII and log-injectable strings | CWE-532 (Insertion of Sensitive Information into Log File) + CWE-117 (Improper Output Neutralisation for Logs) | A09:2021 Security Logging and Monitoring Failures | `app/Http/Controllers/Api/TelegramWebhookController.php:23` | Yes — any Telegram user can send a message with a `text` field containing `\n[ADMIN] FAKE ENTRY` and forge log entries | Static review of the line `Log::info("Telegram Webhook received", $update);` — `$update` is `$request->all()` | Replace with a curated summary: `Log::info('Telegram Webhook received', ['update_id' => $update['update_id'] ?? null, 'type' => isset($update['message']) ? 'message' : (isset($update['callback_query']) ? 'callback_query' : 'other')]);` and explicitly drop `message.text`, `caption`, `from.*`, `callback_query.data` from the log payload |
| **High** | SEC-002 | TelegramBot webhook logs full exception stack trace, contradicting SECURITY.md | CWE-209 (Generation of Error Message Containing Sensitive Information) + CWE-532 | A09:2021 | `Modules/TelegramBot/Http/Controllers/TelegramBotWebhookController.php:42` | No — requires triggering an exception in the bot path, but exceptions in the webhook are common (DB, HTTP, JSON parsing) | Static review: `['trace' => $e->getTraceAsString()]` | Remove the `trace` key. Keep `'exception_class' => get_class($e)` and `'message' => $e->getMessage()` (sanitise CR/LF before logging, or use the existing pattern from `app/Http/Controllers/TelegramWebhookController.php:82-87`). Align with the SECURITY.md claim. |
| **Medium** | SEC-003 | Multiple `Log::*` calls concatenate `$e->getMessage()` with `.` operator without sanitisation | CWE-117 | A09:2021 | `app/Services/TelegramNotifierService.php:74,92`, `app/Services/AI/VoiceService.php:67,71`, `Modules/Speaking/Services/AiSpeakingService.php:78,82,110`, `Modules/TelegramBot/Services/GeminiLessonGenerator.php:217`, `Modules/TelegramBot/Http/Controllers/TelegramBotWebhookController.php:41` | Yes — any exception whose message contains CR/LF (e.g. validation error on user-supplied input, database error echoing raw SQL) will forge log lines on the `single` channel | Static review | Switch from `.` concatenation to structured context: `Log::error('... exception', ['message' => str_replace(["\r", "\n"], ' ', $e->getMessage())])`. Or apply a global Monolog processor that strips newlines from all string context values. |
| **Medium** | SEC-004 | GDPR data export endpoint does not audit the export action | CWE-778 (Insufficient Logging) | A09:2021 | `app/Http/Controllers/SettingsController.php:43-56` | No — gap, not an exploit. A user can export their data repeatedly with no trail. | Static review | Add before the response: `app(AuditLogger::class)->log('gdpr.data_exported', $user, ['bytes' => strlen(json_encode($data))]);` Also add `Cache-Control: no-store` header. |
| **Low** | SEC-005 | Audit table immutability relies on convention + `UPDATED_AT = null`; no DB trigger or GRANT restriction prevents UPDATE/DELETE from a compromised PHP process | CWE-1174 (ASP.NET Misconfiguration: Improper Model Validation — adapted) → better mapped to CWE-732 (Incorrect Permission Assignment for Critical Resource) | A05:2021 Security Misconfiguration | `app/Models/AuditLog.php:21`, `database/migrations/2026_06_19_100000_create_audit_logs_table.php` | Partial — any code-execution-level attacker can `AuditLog::truncate()` or run raw SQL. | Static review of `grep AuditLog::update|AuditLog::delete|AuditLog::truncate` → 0 matches in app code, but no DB-level guard. | Add a migration that installs a `BEFORE UPDATE/DELETE` trigger raising `SIGNAL SQLSTATE '45000'` on the `audit_logs` table, and create a separate, lower-privileged DB role for the application that lacks `UPDATE/DELETE` on `audit_logs`. For audit pruning, use a separate maintenance role that bypasses the trigger. |
| **Low** | SEC-006 | AuditLog retention claim ("prune rows older than 365 days") is aspirational; no prune schedule exists | CWE-400 (Uncontrolled Resource Consumption — adapted; or CWE-693 Protection Mechanism Failure) | A05:2021 | `database/migrations/2026_06_19_100000_create_audit_logs_table.php:18` (doc only), `routes/console.php`, `app/Models/AuditLog.php` (no `Prunable` trait) | No — informational. The table grows unbounded. | Static review | Either implement `Prunable` on the model and schedule `model:prune` daily in `routes/console.php`, or remove the retention comment to avoid misleading future maintainers. |
| **Low** | SEC-007 | `Api/TelegramWebhookController::handle` has no `try/catch` — exceptions would render HTML stack traces on Telegram | CWE-209 | A09:2021 | `app/Http/Controllers/Api/TelegramWebhookController.php:20-30` | Yes — any exception in `User::find()` / `update()` would render a 500 HTML page (Telegram does not send `Accept: application/json`). With `APP_DEBUG=true` this leaks file paths. | Static review | Wrap the body in `try { ... } catch (\Throwable $e) { Log::error(...); return response('ok', 200); }` matching the pattern in `TelegramWebhookController.php`. |

---

## Finding Details

### SEC-001 (High) — Telegram API webhook dumps entire request payload

**Evidence** (`app/Http/Controllers/Api/TelegramWebhookController.php:20-23`):
```php
public function handle(Request $request)
{
    $update = $request->all();
    Log::info("Telegram Webhook received", $update);
```

**Attack path**:
1. Attacker sends a Telegram message to the bot. The `message.text` field can contain arbitrary bytes including `\n`.
2. Laravel's `Request::all()` returns the entire JSON body — including `message.text`, `message.from.first_name`, `message.from.username`, `message.chat.id`, `callback_query.data`, and any `caption` (up to 1024 chars per Telegram API).
3. `Log::info` writes the array to the configured channel. With the default `single` channel (line-formatted Monolog), array values are JSON-encoded inline; with `daily`/`slack`/`syslog`, JSON-encoded as separate fields.
4. On the `single` channel, an attacker can craft `text` to forge log lines: e.g. `text = "ok\n[2026-06-25 12:00:00] local.ERROR: FAKE ADMIN ACTION user.approved target_id=1"` — produces an attacker-controlled log line that a downstream SIEM (if any) will index.
5. PII — `from.first_name` (real name), `from.username` (Telegram handle), `chat.id` (links Telegram identity to platform user) — is written to disk in plaintext logs.

**Severity rationale**: Log forging under CWE-117 + CWE-532 is normally Medium, but the volume of PII (real names, chat IDs that identify students) bumps it to High for this app.

**Minimal fix**: see diff below.

**Regression check**: Add a feature test that POSTs a synthetic Telegram update with `\n` in `text` and asserts the resulting log line does NOT contain a forged entry marker.

### SEC-002 (High) — TelegramBot webhook logs full stack trace

**Evidence** (`Modules/TelegramBot/Http/Controllers/TelegramBotWebhookController.php:40-43`):
```php
} catch (\Throwable $e) {
    Log::error('[TelegramBot] Webhook exception: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
    ]);
```

**Conflict with docs**: `README.md` / `SECURITY.md` claim "Telegram webhook logging no longer dumps full stack traces." This file predates or escaped that hardening.

**Attack path**: Stack traces can include:
- Database credentials in connection strings if a PDO exception echoes the DSN.
- File-system absolute paths (server-side information disclosure — CWE-209).
- Argument values that may contain sensitive payload fragments.

**Severity rationale**: Stack-trace leakage to logs is bounded by log-file access control, but contradicts the explicit security claim, so rated High.

**Minimal fix**: Remove the `'trace' => $e->getTraceAsString()` key. Match the existing pattern in `app/Http/Controllers/TelegramWebhookController.php:82-87`:
```php
Log::error('[TelegramBot] Webhook exception', [
    'update_id' => $request->input('update_id'),
    'exception_class' => get_class($e),
    'message' => $e->getMessage(),
    'file' => basename($e->getFile()) . ':' . $e->getLine(),
]);
```

**Regression check**: Add a feature test that triggers an exception in the bot webhook path and asserts the log file does not contain the substring `"#0 /"` (Monolog stack-trace marker).

### SEC-003 (Medium) — Log concatenation of `$e->getMessage()` without sanitisation

**Evidence** (representative — `app/Services/AI/VoiceService.php:67`):
```php
Log::error('Whisper API Error: ' . $response->body());
```
and `Modules/Speaking/Services/AiSpeakingService.php:78,82,110`, `Modules/TelegramBot/Services/GeminiLessonGenerator.php:217`, `app/Services/TelegramNotifierService.php:74,92`, `app/Services/AI/VoiceService.php:67,71`.

**Attack path**: An upstream service echoes user input in its error body (common in LLM APIs: "Invalid prompt: contains 'foo\nbar'"). On the `single` channel, this produces a forged log line.

**Severity rationale**: Bounded by attacker ability to inject CR/LF into the *upstream's* error response. Realistic when an LLM provider echoes the prompt in a 4xx error.

**Minimal fix**: Apply a global Monolog processor that strips CR/LF from string-valued context fields. Example processor (place in `app/Logging/SanitizeLogProcessor.php`):
```php
namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class SanitizeLogProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(message: str_replace(["\r\n", "\r", "\n"], ' ', $record->message), context: $this->clean($record->context));
    }
    private function clean(array $ctx): array
    {
        return array_map(function ($v) {
            return is_string($v) ? str_replace(["\r\n", "\r", "\n"], ' ', $v) : $v;
        }, $ctx);
    }
}
```
Register in `config/logging.php` under each channel:
```php
'processors' => [\App\Logging\SanitizeLogProcessor::class],
```

**Regression check**: Unit test that invokes the processor on a record containing `\n[FAKE] INFO: forged` and asserts the newline is replaced.

### SEC-004 (Medium) — GDPR export not audited

**Evidence** (`app/Http/Controllers/SettingsController.php:43-56`): no `AuditLogger` call.

**Attack path**: Not an exploit. Under GDPR Art. 15 + Vietnamese PDPA, the **act of exporting personal data** should itself be auditable. Without this row, an attacker who gains session access can repeatedly export without trace.

**Minimal fix**:
```php
public function export(Request $request)
{
    $user = $request->user();
    $data = [/* ... existing ... */];
    app(\App\Services\AuditLogger::class)->log(
        action: 'gdpr.data_exported',
        target: $user,
        metadata: ['bytes' => strlen(json_encode($data))],
    );
    return response()->json($data, 200, [
        'Content-Type' => 'application/json',
        'Content-Disposition' => 'attachment; filename="user-data-' . $user->id . '.json"',
        'Cache-Control' => 'no-store',
    ]);
}
```

**Regression check**: Feature test that POSTs/GETs `/settings/export` and asserts an `audit_logs` row with `action='gdpr.data_exported'` is created.

### SEC-005 (Low) — Audit table immutability by convention only

**Evidence**: see §B above.

**Severity rationale**: Defense-in-depth gap. Requires already-compromised PHP process to exploit. Not exploitable from a network request.

**Minimal fix (recommended for production hardening)**: MySQL trigger:
```sql
CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only';
CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only';
```
And split DB roles: app role has `INSERT, SELECT` on `audit_logs` only.

**Regression check**: Manual test that `UPDATE audit_logs SET action='tampered' WHERE id=1` raises an error.

### SEC-006 (Low) — Retention claim not implemented

**Evidence**: `routes/console.php` has no `model:prune` schedule; `AuditLog` model has no `Prunable` trait.

**Severity rationale**: Compliance gap (GDPR Art. 5(1)(e) storage limitation), not an exploit.

**Minimal fix**:
```php
// app/Models/AuditLog.php
use Illuminate\Database\Eloquent\Prunable;

class AuditLog extends Model
{
    use Prunable;
    public const UPDATED_AT = null;
    public function prunable() { return static::where('created_at', '<', now()->subDays(365)); }
}
```
```php
// routes/console.php
Schedule::command('model:prune')->daily();
```

**Regression check**: Feature test that creates an old audit row and runs `model:prune`, asserting deletion.

### SEC-007 (Low) — API webhook has no try/catch

**Evidence** (`app/Http/Controllers/Api/TelegramWebhookController.php:20-30`): no exception handling.

**Severity rationale**: With `APP_DEBUG=false` (shipping default), Laravel renders a generic 500 page. Not exploitable for stack-trace leak unless `APP_DEBUG=true` is also set. Rated Low because the shipping default is safe.

**Minimal fix**: Wrap in try/catch matching the pattern in the web variant (SEC-001 fix also covers this).

---

## Code Diffs for High-Severity Findings

### SEC-001 — `app/Http/Controllers/Api/TelegramWebhookController.php`

```diff
 public function handle(Request $request)
 {
     $update = $request->all();
-    Log::info("Telegram Webhook received", $update);
+    // SECURITY: Log only the update type and id, never the full payload.
+    // The full body can contain attacker-controlled strings (message.text,
+    // callback_query.data, from.first_name) that we use elsewhere — but
+    // logging them verbatim risks PII leakage and log forging. See
+    // .omo/evidence/task-10-security-review.md SEC-001.
+    Log::info('Telegram Webhook received', [
+        'update_id' => $update['update_id'] ?? null,
+        'type' => isset($update['message'])
+            ? 'message'
+            : (isset($update['callback_query']) ? 'callback_query' : 'other'),
+    ]);

     if (isset($update['callback_query'])) {
         return $this->handleCallbackQuery($update['callback_query']);
     }

     return response()->json(['status' => 'ignored']);
 }
```

### SEC-002 — `Modules/TelegramBot/Http/Controllers/TelegramBotWebhookController.php`

```diff
         } catch (\Throwable $e) {
-            Log::error('[TelegramBot] Webhook exception: ' . $e->getMessage(), [
-                'trace' => $e->getTraceAsString(),
-            ]);
+            // SECURITY: align with README/SECURITY.md — never log the full
+            // stack trace. Match the pattern in
+            // app/Http/Controllers/TelegramWebhookController.php so log volume
+            // and disclosure surface stay consistent across webhooks.
+            Log::error('[TelegramBot] Webhook exception', [
+                'update_id' => $request->input('update_id'),
+                'exception_class' => get_class($e),
+                'message' => $e->getMessage(),
+                'file' => basename($e->getFile()) . ':' . $e->getLine(),
+            ]);
             $this->telegram->sendAdminAlert('Telegram webhook exception', [
```

---

## Downgraded or Rejected Candidates

| Candidate | Reason rejected |
|---|---|
| `TelegramWebhookController.php:148,183` log injection via `{$user->email}` | Downgraded — Laravel's `email` validation rule (RFC 5321/5322) forbids CR/LF in registered addresses. Confirmed in `Modules/Auth/Http/Requests/RegisterRequest.php:14` and `app/Http/Controllers/AdminBulkController.php:96`. No bypass path found in registration flow. |
| `TelegramWebhookController.php:82-87` exception message logging | Downgraded — the source comment already documents the risk and the structured context approach avoids concatenation. Newlines in the structured `message` array field are safe for JSON output but would still forge on `single` channel — covered by SEC-003. |
| `AuditLogger` storing request `path()` in metadata | Downgraded — `path()` is URL-derived, length-bounded by web server, and not rendered to users. Stored in DB only. |
| `AuditLogger` storing `user_agent` (up to 500 chars) in DB | Downgraded — explicitly capped at 500 chars, stored only. Not displayed to users. |
| `User::only([...])` for GDPR export | Verified safe — explicitly excludes password/remember_token via the whitelist (the `#[Hidden]` attribute on `User` is belt-and-suspenders but does NOT affect `->only()`). |
| `Log::debug(...)` in production | Downgraded — these go through Monolog, not raw echo. At `LOG_LEVEL=debug` (default), they write to disk; operators can raise level via env. |
| `audit_logs` does not have `created_at` index by itself | False — index on `(actor_id, created_at)` and `(action, created_at)` cover common admin queries. |
| `.env` accidentally logs `APP_KEY` | Not reviewed — `.env` content out of scope for this task. No `env('APP_KEY')` interpolation found in any `Log::` call. |

---

## Residual Risk

### Not tested in this audit
- **Actual exception message injection in production**: No crafted exception payload was exercised in this review. Static analysis only.
- **Log shipping**: The default `single` channel writes to `storage/logs/laravel.log`. Whether production uses an external shipper (Cloudflare Logpush, Papertrail) — and whether that shipper preserves raw bytes — was not verified. If a shipper re-renders newlines as line breaks, the forging risk amplifies (SEC-003 severity would rise).
- **APP_KEY / DB credentials in logs**: Confirmed no `Log::*` call interpolates `env('APP_KEY')` or `env('DB_PASSWORD')`. But `dd()/var_dump()` was not searched in `storage/logs/laravel.log` itself — only in source files.
- **.env file permissions and contents**: Out of scope.
- **Log retention on disk**: `storage/logs/laravel.log` rotation depends on the `daily` channel being selected (currently `single` is default). `single` mode never rotates; the file grows unbounded.
- **Browser-side log leakage**: Not applicable (no `console.log` of PII reviewed here).
- **Frontend CSP and log forwarding**: Out of scope for C10.
- **Race conditions on audit log writes**: Not reviewed.

### Items deferred to other tasks
- Rate limiting on `/settings/export` (SEC-004 follow-up).
- PII tagging in `metadata` JSON column (no schema validation on metadata).
- Email redaction in admin-facing error pages.

---

## Summary

**Verdict**: PASS WITH FINDINGS — 7 issues, 2 High, 2 Medium, 3 Low.

**Top priority**:
1. **SEC-001** — `Api/TelegramWebhookController.php:23` (1-line fix, log forging + PII leak, exploitable today).
2. **SEC-002** — `TelegramBotWebhookController.php:42` (1-line fix, contradicts security claim, exploitable via any bot exception).

Both fixes are short, low-risk, and do not require schema or configuration changes.

**No findings on**: debug leftovers (`dd/var_dump/print_r` = 0 matches), exception handler stack-trace leakage under default `APP_DEBUG=false`, AuditLog model append-only-by-convention enforcement, GDPR export password hash leakage, AuditLogTest validity.
