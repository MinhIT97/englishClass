# Task 7 — Telegram & Webhook Security Audit (C7)

| Field | Value |
|---|---|
| Audit type | Read-only security audit |
| Audit date | 2026-06-25 |
| Auditor | Sisyphus-Junior (security-research skill) |
| Target scope | Telegram webhook controllers, secret middleware, route registration, dead-code risk |
| Branch / diff | Current working tree (`git status` clean at audit time) — full audit, no diff supplied |
| Files reviewed (read) | `app/Http/Controllers/TelegramWebhookController.php`, `app/Http/Controllers/Api/TelegramWebhookController.php`, `Modules/TelegramBot/Http/Middleware/VerifyTelegramSecret.php`, `Modules/TelegramBot/Services/TelegramBotCommandService.php`, `Modules/TelegramBot/Http/Controllers/TelegramBotWebhookController.php`, `app/Services/TelegramService.php`, `config/telegram.php`, `routes/web.php`, `routes/api.php`, `bootstrap/app.php`, `tests/Feature/Security/TelegramWebhookSecurityTest.php`, `tests/Feature/CoreLogicRegressionTest.php` |
| Tests run | None (read-only — no test execution) |

---

## Verdict

**PASS WITH FINDINGS** — webhook secret verification is correctly implemented (timing-safe, 503 on production misconfiguration), the legacy unrouted API controller is correctly not reachable, and existing tests cover the happy/misconfiguration paths. Five findings documented: 0 Critical, 0 High, **2 Medium**, **2 Low**, **1 Informational**. None require blocking release; SEC-001 and SEC-002 should be addressed before public launch if Telegram admin-approval is a security boundary.

---

## Findings Summary

| ID | Severity | Title | CWE | OWASP 2021 | Exploitability | Impact | Status |
|---|---|---|---|---|---|---|---|
| SEC-001 | Medium | Log injection via `{$user->email}` / `{$adminName}` interpolation | CWE-117 | A09:2021 Security Logging Failures | Low–Medium (depends on registration path) | Audit-trail forgery | Documented |
| SEC-002 | Medium | `dispatchAdminCallback` does not verify `chat_id === TELEGRAM_ADMIN_CHAT_ID` (single-layer trust on secret) | CWE-285 | A01:2021 Broken Access Control | Conditional (requires secret leak) | Admin impersonation if secret leaks | Documented |
| SEC-003 | Low | No `update_id` idempotency / replay protection | CWE-294 | A04:2021 Insecure Design | Low (mitigated by status check) | Duplicate side effects (e.g., 2 lesson generations) | Documented |
| SEC-004 | Low | Default webhook secret literal `englishclass_webhook_secret` if env var unset | CWE-798 | A07:2021 Identification & Auth Failures | Medium if operator forgets env var | Full webhook forgery | Documented |
| SEC-005 | Informational | Dead code: `Api/TelegramWebhookController.php` is unrouted but CSRF-exempt; logging dumps full payload | CWE-1188 (Initialization of a Resource with an Insecure Default) | A05:2021 Security Misconfiguration | None today; latent regression risk | Sensitive payload leak if re-wired | Documented |

**No Critical or High findings.**

---

## Finding Details

### SEC-001 — Log injection via user-controlled fields

- **Severity:** Medium
- **CWE:** CWE-117 (Improper Output Neutralization for Logs)
- **OWASP Top 10 2021:** A09:2021 — Security Logging and Monitoring Failures
- **Location:**
  - `app/Http/Controllers/TelegramWebhookController.php:148` — `Log::info("[Telegram] Admin duyệt học viên #{$userId} ({$user->email})");`
  - `app/Http/Controllers/TelegramWebhookController.php:183` — `Log::info("[Telegram] Admin từ chối học viên #{$userId} ({$user->email})");`

**Evidence (line 148):**

```php
148:         Log::info("[Telegram] Admin duyệt học viên #{$userId} ({$user->email})");
```

**Attack path:**

1. Attacker registers a user with a name/email field that contains a newline + a forged log line, e.g. (name): `"hacker\n[2026-06-25 00:00:00] production.ERROR: forged admin alert"`.
2. On the controller's next call to `approveUser`/`rejectUser`, the interpolated `{$user->email}` lands in the log file, prepended with the legitimate log line.
3. SIEM / log monitor parses the forged line as if it came from the legitimate logging pipeline. Depending on the parser, this can mask an attacker's actual activity, forge incident-response timestamps, or trigger false-positive alerts.

**Bounded by:** the registration path (need to find where `email`/`name` is validated). Laravel's default `email` rule rejects newlines for most practical attacker payloads. However:
- `name` is not always constrained (see `RegisterController` mass-assignment).
- Database-level seeds, factories (`User::factory()`), or admin-created accounts bypass validation.
- The same interpolation is also in `editMessageText` (lines 138–143 and 174–178) which sends to the admin's Telegram — but that channel is *output* not log, and is the documented threat model (admin sees user data). The log channel is the actual finding.

**Severity rationale:** Medium — log forgery rarely yields direct RCE on Laravel (file-based logs, no eval), but corrupts the audit trail for the admin-approval flow, which is exactly the sensitive action being audited. Coupled with SEC-002, a forged log entry claiming a different admin approved/rejected the user undermines non-repudiation.

**Minimal fix:** cast and truncate, or use Laravel's `Log::info` context array (which is JSON-encoded and not vulnerable to newline injection in a single line):

```php
// Replace
Log::info("[Telegram] Admin duyệt học viên #{$userId} ({$user->email})");
// With
Log::info('[Telegram] Admin duyệt học viên', [
    'user_id' => $userId,
    'email'   => $user->email,         // JSON-encoded, no newline injection
    'email_safe' => preg_replace('/[\x00-\x1F\x7F]/', '', $user->email ?? ''), // belt-and-braces
]);
```

Apply identical fix at line 183.

**Regression check:** add a test that constructs a user with email `"a@b.com\nFAKE LOG ENTRY"` and verifies the log entry written via `Log::shouldReceive('info')->withArgs(function ($msg, $ctx) { ... })` contains no raw newline in `$msg` (only inside the context array).

---

### SEC-002 — `dispatchAdminCallback` does not verify `chat_id === TELEGRAM_ADMIN_CHAT_ID`

- **Severity:** Medium
- **CWE:** CWE-285 (Improper Authorization)
- **OWASP Top 10 2021:** A01:2021 — Broken Access Control
- **Location:**
  - `app/Http/Controllers/TelegramWebhookController.php:102-114` (`dispatchAdminCallback`)
  - `app/Http/Controllers/TelegramWebhookController.php:116-149` (`approveUser`)
  - `app/Http/Controllers/TelegramWebhookController.php:151-184` (`rejectUser`)

**Evidence (line 107):**

```php
102:     private function dispatchAdminCallback(string $action, int $userId, array $cb): void
103:     {
104:         $callbackId = (string) ($cb['id'] ?? '');
105:         $chatId = isset($cb['message']['chat']['id']) ? (string) $cb['message']['chat']['id'] : null;
106:         $messageId = $cb['message']['message_id'] ?? null;
107:         $adminName = $cb['from']['first_name'] ?? 'Admin';
```

`$chatId` is read but **never compared** against `config('telegram.admin_chat_id')` before mutating `$user->status`. The only auth check is the route-level `telegram.secret` middleware.

**Attack path:**

1. The `TELEGRAM_WEBHOOK_SECRET` leaks (e.g., from a CI log, an old staging env var copied to prod, an ex-employee, or compromise of the .env in a backup). Combined with SEC-004 the default secret `englishclass_webhook_secret` is documented publicly, so an attacker who exploits SEC-004 already wins this attack.
2. Attacker POSTs a forged callback to `/telegram/webhook`:
   ```http
   POST /telegram/webhook HTTP/1.1
   X-Telegram-Bot-Api-Secret-Token: englishclass_webhook_secret
   Content-Type: application/json

   {"callback_query":{"id":"x","from":{"first_name":"Attacker"},"message":{"chat":{"id":1},"message_id":1,"text":"x"},"data":"approve_user_42"}}
   ```
3. Controller validates secret (✓), parses `approve_user_42`, calls `approveUser(42, ...)`. User 42 is promoted to `active`. `Log::info` records the forgery with `$adminName = "Attacker"`. Admin receives a Telegram edit to chat `1` (probably fails silently because it's not the admin's chat) — but the user status is already mutated.

**Severity rationale:** Medium. The attack requires the webhook secret to be compromised, which is itself a separate precondition. But the defense-in-depth expectation for any admin-mutating webhook is to verify the originating `chat.id` matches `TELEGRAM_ADMIN_CHAT_ID` so that a stolen secret alone (without an additional channel such as exfiltrating the admin's Telegram session) cannot drive admin actions. Note that Telegram only delivers callbacks to the bot's webhook when a user actually clicks the button — but a forged POST bypasses Telegram entirely, which is the threat model here.

**Minimal fix:** add a single comparison in `dispatchAdminCallback` *before* dispatching to `approveUser`/`rejectUser`:

```php
private function dispatchAdminCallback(string $action, int $userId, array $cb): void
{
    $callbackId = (string) ($cb['id'] ?? '');
    $chatId     = isset($cb['message']['chat']['id']) ? (string) $cb['message']['chat']['id'] : null;
    $messageId  = $cb['message']['message_id'] ?? null;
    $adminName  = $cb['from']['first_name'] ?? 'Admin';

    // Defense in depth: even with the secret middleware passed,
    // the callback MUST originate from the configured admin chat.
    // Stops a leaked secret from being sufficient to forge admin actions.
    $expectedAdminChat = (string) config('telegram.admin_chat_id', '');
    if ($expectedAdminChat === '' || $chatId === null || ! hash_equals($expectedAdminChat, (string) $chatId)) {
        Log::warning('[Telegram] Admin callback rejected — chat_id mismatch', [
            'expected' => $expectedAdminChat,
            'received' => $chatId,
            'action'   => $action,
            'user_id'  => $userId,
        ]);
        $this->telegram->answerCallbackQuery($callbackId, '❌ Unauthorized.');
        return;
    }

    if ($action === 'approve') {
        $this->approveUser($userId, $callbackId, $chatId, $messageId, $adminName);
    } else {
        $this->rejectUser($userId, $callbackId, $chatId, $messageId, $adminName);
    }
}
```

Note: use `hash_equals` (timing-safe) for the comparison even though chat_id is integer — keeps the codebase consistent with the middleware's timing-safe discipline.

**Regression check:** add to `tests/Feature/Security/TelegramWebhookSecurityTest.php`:

```php
public function test_admin_callback_with_wrong_chat_id_is_rejected(): void
{
    config(['telegram.webhook_secret' => 'expected-secret-value', 'telegram.admin_chat_id' => '999']);
    $user = User::factory()->create(['status' => 'pending']);

    $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'expected-secret-value'])
        ->postJson('/telegram/webhook', [
            'update_id' => 7,
            'callback_query' => [
                'id' => 'cb-x',
                'from' => ['first_name' => 'Impersonator'],
                'message' => [
                    'chat' => ['id' => 1],            // ≠ 999
                    'message_id' => 1,
                ],
                'data' => "approve_user_{$user->id}",
            ],
        ])->assertOk();

    $this->assertSame('pending', $user->fresh()->status);  // unchanged
}
```

---

### SEC-003 — No `update_id` idempotency / replay protection

- **Severity:** Low
- **CWE:** CWE-294 (Authentication Bypass by Capture-Replay)
- **OWASP Top 10 2021:** A04:2021 — Insecure Design
- **Location:** `app/Http/Controllers/TelegramWebhookController.php` (entire `handle()` method, lines 19-100)

**Evidence:** `update_id` is logged in the exception handler (lines 83, 90) but **not deduplicated**. There is no `Cache::add("tg:update:{$id}", ...)` and no DB table of seen updates.

**Attack path (limited):**

1. Legitimate Telegram delivery is acknowledged with HTTP 200 but the network drops the response. Telegram retries with the *same* `update_id` within minutes (typical: 3 retries with backoff).
2. The handler re-runs. For `approve_user_N`, the status guard at line 125 (`if ($user->status === 'active')`) prevents a second flip — the second call short-circuits with "ℹ️ đã được duyệt trước đó". Good.
3. For free-text and command paths (e.g., `tgb:extra` / `/extra`), there is **no idempotency**. A retried `/extra` command causes `TelegramLearningService::sendExtraLesson()` to run twice, which: (a) burns 2 Gemini API calls (cost amplification), (b) likely violates the daily quota check *twice* but the daily-limit guard may be after the lesson is generated, and (c) sends duplicate lesson messages to the user's Telegram chat.

**Severity rationale:** Low. The most sensitive mutation (admin approval) is already idempotent. The residual risk is duplicate side-effects on the learning bot's lesson/quota flow. Telegram retries are bounded and short-window.

**Minimal fix (optional, defense-in-depth):** add at the top of `handle()`:

```php
$updateId = $request->input('update_id');
if (is_int($updateId) || ctype_digit((string) $updateId)) {
    $seenKey = "telegram:webhook:seen:{$updateId}";
    if (! Cache::add($seenKey, 1, now()->addHours(24))) {
        // Already processed within the last 24h — silently ack to stop Telegram retrying.
        return response('OK', 200);
    }
}
```

This is a 5-line change that eliminates the entire replay window for all current and future handlers.

**Regression check:** add test that POSTs the same payload twice with the same `update_id` and asserts the second call short-circuits without invoking `approveUser`.

---

### SEC-004 — Default webhook secret literal in config fallback

- **Severity:** Low
- **CWE:** CWE-798 (Use of Hard-coded Credentials)
- **OWASP Top 10 2021:** A07:2021 — Identification and Authentication Failures
- **Location:** `config/telegram.php:26`

**Evidence:**

```php
26:     'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', 'englishclass_webhook_secret'),
```

The literal `englishclass_webhook_secret` is used as the fallback default if `TELEGRAM_WEBHOOK_SECRET` env var is missing. The README at `README.md` documents this exact string verbatim ("`TELEGRAM_WEBHOOK_SECRET=englishclass_webhook_secret`"), so the secret is public knowledge.

**Attack path:**

1. Operator fails to set `TELEGRAM_WEBHOOK_SECRET` in production (e.g., env file lost during container rebuild, .env.production regenerated from .env.example which does not include it — verified at `.env.example` defaults).
2. Middleware loads `expected = 'englishclass_webhook_secret'`. Attacker reads this from README or the public repo. Forges callbacks with `X-Telegram-Bot-Api-Secret-Token: englishclass_webhook_secret`.
3. Without SEC-002's chat_id check, attacker is now the admin (full SEC-002 exploit path unlocked).

**The VerifyTelegramSecret middleware's `app->environment('production')` 503 guard partially mitigates** this — empty config returns 503 in prod, but a non-empty *known-default* secret passes middleware. The 503 only fires when env is *unset*, not when it's set to the default. So this is genuinely a misconfiguration risk.

**Severity rationale:** Low because (a) the secret check still applies, (b) the 503-on-empty-config catches the most common mistake, (c) README explicitly tells operators to override. But it is a foot-gun.

**Minimal fix:** refuse to start with the default literal in production:

```php
// config/telegram.php
'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),   // no default
```

```php
// VerifyTelegramSecret.php — extend the empty-check
if ($expected === '' || $expected === 'englishclass_webhook_secret') {
    if (app()->environment('production')) {
        Log::error('[TelegramBot] Webhook secret is default/empty in production — rejecting all requests');
        return response('service misconfigured', 503);
    }
    // ... rest unchanged
}
```

**Regression check:** test that asserts 503 when `TELEGRAM_WEBHOOK_SECRET` is the default literal AND `app->environment === 'production'`.

---

### SEC-005 — Dead-code risk: `Api/TelegramWebhookController.php` is unrouted but CSRF-exempt

- **Severity:** Informational (latent regression risk)
- **CWE:** CWE-1188 (Initialization of a Resource with an Insecure Default)
- **OWASP Top 10 2021:** A05:2021 — Security Misconfiguration
- **Location:**
  - `app/Http/Controllers/Api/TelegramWebhookController.php` (entire file, 69 lines)
  - `bootstrap/app.php:37` — CSRF exemption for `api/telegram/webhook`

**Evidence:**

`app/Http/Controllers/Api/TelegramWebhookController.php:23` dumps the entire payload to logs:

```php
23:         Log::info("Telegram Webhook received", $update);
```

The controller has **no** route registration. Verification:
- `routes/web.php` — only `Route::post('telegram/webhook', ...)` registered (line 21).
- `routes/api.php` — only `Route::post('/deploy/notify', ...)` registered.
- `bootstrap/app.php:37` exempts `api/telegram/webhook` from CSRF.
- Test `tests/Feature/CoreLogicRegressionTest.php::test_legacy_unsecured_telegram_webhook_is_not_routable` explicitly asserts `404` for the path.

**Attack path:** None today. Future-developer risk: someone reads the CSRF exemption, sees the controller, and assumes it's a complete-but-not-yet-routed legacy controller. They add `Route::post('api/telegram/webhook', [Api\TelegramWebhookController::class, 'handle'])` without `telegram.secret` middleware, and the controller:
1. Accepts unauthenticated POSTs.
2. Dumps the entire payload (including any user-controlled `text` field — which could be a SQL/XSS payload the attacker is testing for log-filtering bypasses) into `Log::info`.
3. Calls `User::find($userId)` and `User::update(['status' => $newStatus])` for *any* `approve_user_N` callback — no secret, no chat_id check.

This becomes a Critical if re-wired. Severity now is Informational because the path is provably 404.

**Minimal fix:** delete the file. The CSRF exemption on `api/telegram/webhook` in `bootstrap/app.php:37` should also be removed to keep the CSRF allow-list as small as possible (defense in depth — least privilege).

Alternatively, if the controller is kept for historical reference, add a `// DEAD CODE — DO NOT WIRE UP; USE App\Http\Controllers\TelegramWebhookController INSTEAD` header and add a code-search ban in CI.

**Regression check:** the existing `test_legacy_unsecured_telegram_webhook_is_not_routable` already protects this — keep it. Add a grep-based lint in CI:

```bash
! grep -rn "Route::post.*api/telegram/webhook" app/ config/ routes/ bootstrap/
```

---

## Verified Secure Controls (no findings needed)

| Check | Result | Evidence |
|---|---|---|
| (a) Secret check uses `hash_equals` (timing-safe) | ✅ Pass | `Modules/TelegramBot/Http/Middleware/VerifyTelegramSecret.php:38` — `if (! hash_equals($expected, $provided))` |
| (a) No timing-unsafe `===`/`!==` in middleware | ✅ Pass | Only `(string)` cast and `=== ''` empty-check (non-secret comparison) |
| Production misconfiguration → 503 | ✅ Pass | `VerifyTelegramSecret.php:26-30` |
| CSRF properly exempted for legitimate webhook only | ✅ Pass | `bootstrap/app.php:36` — `telegram/webhook` only (api/telegram/webhook exemption is informational per SEC-005) |
| (d) `approveUser` checks user exists and status | ✅ Pass | `TelegramWebhookController.php:118-128` |
| (d) `rejectUser` checks user exists and status | ✅ Pass | `TelegramWebhookController.php:153-163` |
| (e) `handleCallback` parses callback data with strict casts | ✅ Pass | `TelegramBotCommandService.php:248-252` — `explode(':', $data)`, then `(int) ($parts[N] ?? 0)` for all numeric args. No string SQL/XSS sinks reachable from `$parts`. |
| (e) Outgoing Telegram HTML is escaped | ✅ Pass | `TelegramService.php:272-275` — `escapeHtml()` with `ENT_QUOTES \| ENT_SUBSTITUTE`, used on all admin-alert fields |
| (g) Existing webhook security test coverage | ✅ Pass | `tests/Feature/Security/TelegramWebhookSecurityTest.php` covers missing/wrong/empty/production/dev paths (5 tests) |
| Legacy unrouted controller confirmed dead | ✅ Pass | `tests/Feature/CoreLogicRegressionTest.php:17-22` asserts 404 |
| `dispatchAdminCallback` reads chat_id (even though doesn't enforce) | ⚠ Partial | Line 105 — chat_id captured but unused for auth (SEC-002) |

---

## Downgraded or Rejected Candidates

| Candidate | Reason for downgrade/rejection |
|---|---|
| **`App\Http\Controllers\Api\TelegramWebhookController.php` — exposed if re-wired** | Documented as SEC-005 (Informational, latent). Today: 404 — no exposure. Re-classified as regression-risk not vulnerability. |
| **Telegram HTML injection via user `name`/`email` in admin Telegram messages** | Reviewed `TelegramService.php:234-269` — `$user->name` and `$user->email` are interpolated raw into HTML. Telegram's Bot API with `parse_mode=HTML` would render HTML-injected payloads as broken markup but **not execute scripts**. Telegram does not support `<script>`. However, malformed HTML can break the layout, hide the Approve/Reject buttons, or impersonate UI elements visually. Bounded by registration validation. **Downgraded** — not included as a finding because it requires the user to first be registered and approved (admin already sees the user in the original notification at line 247-251 which has the same flaw), and the attack only modifies visual rendering, not state. Worth a follow-up hygiene note but not a security boundary violation. |
| **Command injection via `/command args` text** | Reviewed `TelegramWebhookController.php:62-66` and `TelegramBotCommandService.php:41-120` — the args are passed as `$args` to specific service methods (no `eval`, no `shell_exec`, no DB::raw with the args). All switch cases route to service classes with their own parameter handling. **No finding.** |
| **Webhook route has no `throttle:` middleware** | Reviewed `routes/web.php:21-23` — only `telegram.secret` applied. Telegram itself rate-limits webhook deliveries per bot. The secret check returns 403 (not 429), so the server itself provides no backoff. However, brute-forcing a 27-char secret with timing-safe `hash_equals` is computationally infeasible. **No finding** beyond SEC-004 (the default-secret concern). |
| **`Log::info` of `exception_class` + sanitised message only (line 82-87)** | The exception class + `basename($e->getFile())` is a deliberate sanitisation choice (documented in the inline comment) — full stack traces removed to prevent leaking secrets from the trace frames. **Verified secure** — not a finding. |

---

## Residual Risk

- **Test coverage gap:** no test asserts that `approveUser` is called only for `chat_id === TELEGRAM_ADMIN_CHAT_ID`. If SEC-002 fix is applied, that test must be added (see Regression check under SEC-002).
- **Operational:** the bot's webhook is registered via the `tgb:set-webhook` artisan command (`Modules/TelegramBot/Console/Commands/SetWebhookCommand.php`). I did not execute it. Risk: if the operator runs `tgb:set-webhook --drop` and forgets to re-register, real Telegram traffic stops but no alert fires. Out of scope for security audit.
- **Defense-in-depth not yet tested:** webhook URL is served via Cloudflare Tunnel per README. TLS termination at Cloudflare means the controller sees Cloudflare's edge IP, not Telegram's. The `Log::warning` in the middleware logs `$request->ip()` (line 40), which would show Cloudflare's IP — limited forensic value. Out of scope for this audit but worth noting.
- **No code execution tested.** Per task MUST-NOT rule, I did not POST to `/telegram/webhook` and did not call the controllers in a Laravel test runner. Findings are based on static reading of source + existing test assertions. Each finding's "Attack path" section is theoretical; exploitability is rated conservatively. SEC-002 is the highest-confidence finding (defense-in-depth gap, well-known pattern).

---

## Files Created

- `C:\laragon\www\englishClass\.omo\evidence\task-7-security-review.md` (this file)
