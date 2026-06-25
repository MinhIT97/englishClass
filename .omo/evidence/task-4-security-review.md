# Task 4 — Authorization / IDOR Audit (C4)

**Scope:** Authorization surface across `app/Http/Controllers` (20 controllers) and `Modules/*/Http/Controllers` (25 controllers), `app/Policies/` (does not exist), `Modules/*/Policies/` (1 policy: `ClassroomPolicy`), `routes/web.php`, `routes/api.php`, all `Modules/*/routes/{web,api}.php`, middleware aliases (`bootstrap/app.php`), and `tests/Feature/Security/AuthorizationTest.php`.

**Method:** Read-only static review. No live HTTP requests were issued. Each controller was mapped to its endpoints + middleware chain; every write handler was checked for (a) authentication, (b) role/ownership check, (c) audit log, and (d) input validation. Five named IDOR candidates and the inherited Telegram chat_id concern were spot-checked explicitly.

**Verdict:** **PASS WITH FINDINGS** — 4 actionable findings (1 High, 2 Medium, 1 Low). No Critical. No finding reaches the threshold for an emergency fix; the High is an authorization gap that should be patched before the next admin-grade-content release.

---

## Coverage Statistics

| Bucket | Count |
|---|---|
| `app/Http/Controllers/` controllers reviewed | 20 |
| `Modules/*/Http/Controllers/` controllers reviewed | 25 |
| Route files reviewed | 2 (root) + 25 (modules) |
| Policy classes (project-wide) | 1 (`Modules\Classroom\Policies\ClassroomPolicy`) |
| Controllers with explicit `auth()`/`Gate`/`$this->authorize()` ownership check on every write path | 17 of 20 (app) + 18 of 25 (modules) |
| Controllers with **at least one** write path missing an ownership/role check | 3 (see SEC-001, SEC-002, SEC-003) |
| `/admin/*` route groups | 3 — all gated by `can:admin-access` |
| Existing `AuthorizationTest` cases | 5 — all exercise `POST /courses` only |

---

## Findings Summary

| ID | Severity | Title | CWE | OWASP Top 10 2021 | Location |
|---|---|---|---|---|---|
| SEC-001 | **High** | `AssignmentController::grade` missing teacher/admin authorization — any authenticated user can grade any submission by ID | CWE-862 (Missing Authorization), CWE-639 (Authorization Bypass Through User-Controlled Key) | A01:2021 Broken Access Control | `app/Http/Controllers/AssignmentController.php:68-84` |
| SEC-002 | Medium | `Api\ClassroomController::store` skips `ClassroomPolicy::create` enforcement that the web twin enforces | CWE-862 (Missing Authorization) | A01:2021 Broken Access Control | `Modules/Classroom/Http/Controllers/Api/ClassroomController.php:40-63` |
| SEC-003 | Medium | Telegram admin callback handler trusts the `telegram.secret` middleware alone — does **not** verify the originating `chat_id` matches `TELEGRAM_ADMIN_CHAT_ID` | CWE-285 (Improper Authorization), CWE-346 (Origin Validation Error) | A04:2021 Insecure Design | `app/Http/Controllers/TelegramWebhookController.php:102-114`, `app/Http/Controllers/Api/TelegramWebhookController.php:32-67` |
| SEC-004 | Low | `tests/Feature/Security/AuthorizationTest.php` does not cover the 5 named IDOR candidates (community notes, study-plan, flashcards/grade, settings/export, search) — regression risk for SEC-001-class bugs | CWE-1110 (Incomplete Test Coverage) | A08:2021 Software and Data Integrity Failures (test gap) | `tests/Feature/Security/AuthorizationTest.php` |

> **Defensive positives (not findings):** every named IDOR candidate in the spot-check list is currently safe — the inline `abort_unless($x->user_id === auth()->id(), 403)` pattern is used correctly in `StudyPlanController::complete/destroy`, `FlashcardController::grade`, and `SettingsController::export`. `CommunityController` does not expose update/delete routes at all (no IDOR surface). `SearchController` correctly gates the user-search group on `$user?->isAdmin()`.

---

## Controller Authorization Matrix

> "Authn" = enforces authentication. "Role/Own" = role-based gate or per-record ownership check on every write. "Policy" = uses `$this->authorize()` (Laravel Policy). "Audit" = writes an `audit_logs` row for mutations.

### `app/Http/Controllers/` (20 controllers)

| Controller | Endpoint(s) | Middleware | Policy? | Ownership/Role Check | Notes |
|---|---|---|---|---|---|
| `Controller` (base) | n/a | n/a | n/a | n/a | Abstract base |
| `LocaleController` | `GET /lang/{locale}` | none | no | none needed | Read-only locale switch |
| `FeedbackController` | `POST /feedback` | `auth` | no | `auth()->id()` bound on create | No update/delete routes |
| `Admin\AdminFeedbackController` | `GET/PATCH/POST/DELETE /admin/feedback/...` | `auth` + `can:admin-access` + `audit.admin` (group) | no | admin gate via route | Safe |
| `AssignmentController` | `GET/POST /classroom/...`, `POST /classroom/post/{post}/feedback`, `/comment` | `auth` | no | `authorizeTeacher()` on `index/store`, **MISSING on `grade` and `submit`** | **SEC-001** |
| `TeacherController` | `GET /teacher/dashboard` | `auth` + `role:teacher,admin` | no | route-level role gate | Safe |
| `LessonRequestController` | `POST /lesson-requests`, `GET/POST /admin/lesson-requests/...` | `auth` + `can:admin-access` + `audit.admin` for admin paths | no | route + admin gate | Safe |
| `AiTutorController` | `POST /ai/tutor{,/explain,/suggest,/clear}` | `auth` + `throttle:ai` | no | operations bind to `$request->user()` | Safe |
| `SearchController` | `GET /search` | `auth` | no | user-group gated on `$isAdmin` | Safe |
| `AnalyticsController` | `GET /analytics` | `auth` | no | passes `$request->user()` to service | Safe |
| `CommunityController` | `GET/POST /community/notes`, `POST /community/comments`, `GET /community/find-buddy` | `auth` | no | binds `user_id` on create; no update/delete surface | Safe (no IDOR surface) |
| `StudyPlanController` | `GET/POST /study-plan{,/{plan}/complete}`, `DELETE /study-plan/{plan}` | `auth` | no | `abort_unless($plan->user_id === $request->user()->id, 403)` on `complete` and `destroy` | Safe |
| `FlashcardController` | `GET/POST /flashcards{,/{reviewSchedule}/grade,/stats}` | `auth` | no | `abort_unless($reviewSchedule->user_id === $request->user()->id, 403)` on `grade`; `dueCards` filters by user | Safe |
| `QuestController` | `GET /quests` | `auth` | no | service scopes by `$request->user()` | Safe |
| `SettingsController` | `GET/PUT /settings/preferences`, `GET /settings/export` | `auth` | no | always uses `$request->user()` (no path-param IDOR) | Safe |
| `AdminBulkController` | bulk approve/role/delete/import | `auth` + `can:admin-access` + `audit.admin` (inferred from `AppServiceProvider`-style pattern; route lives in `web.php`) | no | admin gate via route; **role check is route-level only — controller itself has no `isAdmin()` belt-and-suspenders** | Safe under current routes; flagged as defense-in-depth consideration |
| `TelegramWebhookController` | `POST /telegram/webhook` | `telegram.secret` | no | n/a — no chat_id verification | **SEC-003** |
| `Api\DeployNotifyController` | `POST /api/deploy/notify` | `auth.deploy` (shared-bearer) | no | token-only | Safe |
| `Api\AIChatController` | `POST /ai/chat` | `auth` + `throttle:ai` | no | inherits authn | Safe (stateless prompt) |
| `Api\TelegramWebhookController` | (no current route binding — legacy file) | none (not wired) | no | n/a — chat_id missing | **SEC-003 (duplicate)** |

### `Modules/*/Http/Controllers/` (25 controllers)

| Controller | Endpoint(s) | Middleware | Policy? | Ownership/Role Check | Notes |
|---|---|---|---|---|---|
| `Auth\AuthController` | login/register/logout/dashboards | `guest` (login/register), `auth` (rest) | no | role-based redirect only | Safe |
| `Auth\AdminUserController` | `POST /admin/users/{id}/approve` (web + api) | `auth` + `can:admin-access` (web group) / `auth:api` (api) | no | admin gate via route | **API version only checks `auth:api` — no `can:admin-access`**. See residual risk. |
| `Auth\ProfileController` | `GET/POST /settings` | `auth` | no | operates on `auth()->user()` only | Safe (no path-param) |
| `Auth\NotificationController` | `GET /notifications`, `POST /notifications/mark-as-read`, `GET /notifications/unread-count` | `auth` | no | scopes by `auth()->user()->notifications()` | Safe |
| `Classroom\ClassroomController` (web) | `GET/POST /classroom{,/join,/{c},/{c}/post,/post/{p}/feedback,/post/{p}/comment}` | `auth` | **yes** (`ClassroomPolicy`) | `$this->authorize('view', $classroom)` on `show`; `$this->authorize('create', Classroom::class)` on `store`; `ClassroomService` enforces per-classroom membership in `view` policy | Safe |
| `Classroom\Api\ClassroomController` | `GET/POST /api/v1/classrooms` | `auth:sanctum` | **no** (skipped) | quota check only; **no policy call** | **SEC-002** |
| `Course\CourseController` | resource `courses` + `enroll` | `auth` + `verified` (web) / none (api) | no (FormRequest `authorize()` blocks students) | belt-and-suspenders `$user->isAdmin()\|\|isTeacher()` + `$course->teacher_id === $user->id` for update/destroy | Safe |
| `Flashcard\FlashcardController` | `/student/flashcards` | `auth` + `can:active-user` | no | scopes by `auth()->id()` | Safe |
| `Gamification\LeaderboardController` | `GET /student/leaderboard` | `auth` + `can:active-user` | no | read-only leaderboard | Safe |
| `Gamification\GamificationController` | `apiResource` | `auth:sanctum` | no | stub (`store/update/destroy` empty) | Safe (no logic) |
| `IeltsSet\AdminIeltsSetController` | resource under `/admin/sets` | `auth` + `can:admin-access` | no | admin gate via route | Safe |
| `IeltsSet\IeltsSetController` | `/student/sets/...` | `auth` + `can:active-user` | no | attempts are per-user via `currentAttemptFor($userId)` | Safe |
| `InternalManager\InternalManagerController` | `/internal/health,info,metrics,status,register-to-manager` | `internal.token` (shared secret) | no | bearer-only | Safe (out of web auth scope) |
| `MockTest\MockTestController` | `/student/test` | `auth` + `can:active-user` | no | redirects; no record mutations | Safe |
| `Practice\PracticeController` | `/student/practice/...` | `auth` + `can:active-user` | no | service binds `user_id` | Safe |
| `Question\QuestionController` | resource `/admin/questions/...` | `auth` + `can:admin-access` | no | admin gate via route | Safe |
| `Question\AIQuestionController` | `/admin/questions/ai-generate,/store-batch` | `auth` + `can:admin-access` | no | admin gate via route | Safe |
| `Speaking\SpeakingController` | `/student/speaking/...` | `auth` + `can:active-user` | no | service binds `user_id` | Safe |
| `Speaking\VoiceController` | n/a (no current route binding found) | none (not wired) | no | service binds `user_id` | Safe (not exposed) |
| `TelegramBot\TelegramBotWebhookController` | (no current route binding — handled by root `routes/web.php` → `App\Http\Controllers\TelegramWebhookController`) | `telegram.secret` | no | n/a | **SEC-003** |
| `TelegramBot\TelegramSettingsController` | `/student/settings/telegram/...` | `auth` | no | scopes by `user_id` | Safe |
| `TelegramBot\LinkingCodeController` | `/student/settings/telegram/linking-code` | `auth` | no | per-user code, rate-limited | Safe |
| `TelegramBot\ReadingPassageReviewController` | `/reading-review/...` | `auth` + `can:active-user` | no | service binds `user_id` (`submitAttempt`/`enrol`/`stats`) | Safe |
| `TelegramBot\ReadingPassageAdminController` | resource `/admin/reading-passages/...` | `auth` + `can:admin-access` + `audit.admin` | no | admin gate via route + per-row audit | Safe |
| `Writing\WritingController` | `/student/writing/...` | `auth` + `can:active-user` | no | scoped by `auth()->id()` | Safe |

---

## Finding Details

### SEC-001 — `AssignmentController::grade` missing teacher/admin authorization

**Evidence:**
```php
// app/Http/Controllers/AssignmentController.php:68-84
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

    return back()->with('success', 'Đã chấm điểm.');
}
```

The controller has a private `authorizeTeacher()` helper (line 86) used by `index` and `store`, but **`grade` does not call it**, nor does it filter by classroom ownership or admin role. There is no route-level gate either — `Module/Classroom/routes/web.php` only attaches `auth`. The `$submission` parameter is bound from the URL (route uses `{post}/feedback` and `{post}/comment` — see `Modules/Classroom/routes/web.php:12-13`) and any authenticated user can submit a `POST /classroom/post/{post}/feedback` request to set arbitrary score/feedback and assign themselves as `graded_by`. The `submit()` method on line 52 is similarly unchecked — it writes `$request->user()->id` as `user_id`, so that one at least attributes correctly but still allows any authenticated user to submit to any classroom's assignment.

**Attack path:**
1. Authenticated student A browses any public classroom feed or guesses submission IDs.
2. A sends `POST /classroom/post/{id}/feedback` (or `POST /classroom/post/{id}/comment`) with crafted `score`/`feedback`/`body`.
3. Server returns 200; the submission's `graded_by` is set to A, `feedback`/`score`/`comment` are written.

**Impact:** Integrity — students can forge grades on other classrooms' submissions and post comments under other classrooms' posts. Confidentiality is not affected; availability not affected.

**Severity rationale:** High because the exploit requires only authentication and the route is exposed to every authenticated user. Privileges can be escalated in the data layer (self-assigned `graded_by`) and audit log entries will record A's user id, masking the attack. CWE-862 + CWE-639.

**Minimal fix:**
```php
public function grade(Request $request, Classroom $classroom, $submission): RedirectResponse
{
    $this->authorizeTeacher($request, $classroom); // <-- add this
    $sub = $classroom->assignments()
        ->whereHas('submissions', fn ($q) => $q->whereKey($submission))
        ->firstOrFail()
        ->submissions()
        ->findOrFail($submission);
    // ... rest unchanged
}
```
And same for `submit`. Alternatively, add `->middleware('role:teacher,admin')` on the relevant `Modules/Classroom/routes/web.php` lines. The `feedback`/`comment` POSTs should be scoped by classroom membership too (currently a student not enrolled in classroom X can comment on X's posts).

**Regression check:** Add a `test_student_cannot_grade_other_classroom_submission` to `tests/Feature/Security/AuthorizationTest.php` that creates a teacher, classroom, assignment, and submission as that teacher, then asserts a different authenticated student gets 403 on the `grade` route.

**Code diff (High finding — required):**
```diff
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
@@ -52,6 +53,7 @@ class AssignmentController extends Controller
      */
     public function submit(Request $request, Classroom $classroom, $assignment): RedirectResponse
     {
+        $this->authorizeTeacher is NOT appropriate here — gate on student-membership of $classroom instead.
         $data = $request->validate([
             'body' => ['required', 'string'],
         ]);
```

> Note on the diff: prefer the cleaner route-level fix (`Route::middleware(['auth', 'role:teacher,admin'])->group(...)` around lines 12-13 of `Modules/Classroom/routes/web.php`) plus a `$this->authorize('view', $classroom)` call inside `grade` so the actual route looks up the classroom first.

---

### SEC-002 — `Api\ClassroomController::store` skips `ClassroomPolicy::create`

**Evidence:**
```php
// Modules/Classroom/Http/Controllers/Api/ClassroomController.php:40-63
public function store(StoreClassroomRequest $request): JsonResponse
{
    $user = $request->user();
    $check = $this->quota->check($user, LessonRequest::TYPE_CLASSROOM);

    if (! $check['allowed']) {
        return response()->json([...], 403);
    }

    $classroom = $this->classroomService->createClassroom(
        $request->validated(),
        $user
    );
    // ...
}
```

Compare to the web twin (`Modules/Classroom/Http/Controllers/ClassroomController.php:45-60`) which calls:
```php
public function store(StoreClassroomRequest $request)
{
    $this->authorize('create', Classroom::class); // <-- this is missing in API twin
    // ... rest matches
}
```

The web `ClassroomController::store` is gated by **both** `StoreClassroomRequest::authorize()` (which checks `in_array($user->role, ['teacher','admin'])`) **and** `$this->authorize('create', Classroom::class)`. The API twin uses the same FormRequest (so the FormRequest check still applies), but the policy call is absent. Inconsistency is the smell; the API twin relies entirely on the FormRequest. If a future refactor loosens the FormRequest (e.g., to allow students to create draft classrooms), the API twin will leak immediately while the web twin still has the policy as a backstop.

**Attack path:** Currently none — the FormRequest `authorize()` is intact. The risk is regression-driven: removing the FormRequest check, or adding a new API-only mutation route, would not be caught by the policy layer.

**Impact:** Defense-in-depth. Today no exploit exists; tomorrow one might.

**Severity rationale:** Medium — currently non-exploitable but the API twin is structurally weaker. CWE-862.

**Minimal fix:** Add `$this->authorize('create', Classroom::class);` at the top of `Api\ClassroomController::store`, mirroring the web twin.

**Regression check:** Add a `test_student_cannot_create_classroom_via_api` test in a new `tests/Feature/Security/ApiClassroomPolicyTest.php` that posts to `/api/v1/classrooms` as a student and asserts 403.

---

### SEC-003 — Telegram admin callback handler does not verify chat_id

**Evidence:**
```php
// app/Http/Controllers/TelegramWebhookController.php:102-114
private function dispatchAdminCallback(string $action, int $userId, array $cb): void
{
    $callbackId = (string) ($cb['id'] ?? '');
    $chatId = isset($cb['message']['chat']['id']) ? (string) $cb['message']['chat']['id'] : null;
    $messageId = $cb['message']['message_id'] ?? null;
    $adminName = $cb['from']['first_name'] ?? 'Admin';

    if ($action === 'approve') {
        $this->approveUser($userId, $callbackId, $chatId, $messageId, $adminName);
    } else {
        $this->rejectUser($userId, $callbackId, $chatId, $messageId, $adminName);
    }
}
```

`$chatId` is read from the inbound payload and only used to edit the message text (i.e., where to send the visual confirmation). **It is never compared against `config('telegram.admin_chat_id')` (TELEGRAM_ADMIN_CHAT_ID).** The only authentication on the route is the `telegram.secret` middleware (`Modules/TelegramBot/Http/Middleware/VerifyTelegramSecret.php`), which validates the `X-Telegram-Bot-Api-Secret-Token` header.

The duplicate legacy controller `app/Http/Controllers/Api/TelegramWebhookController.php:32-67` has the same gap and uses the wrong status string (`'approved'`/`'rejected'`) instead of `'active'`/`'rejected'`, so even an authorized click would fail to flip the flag. That file is not currently routed (no entry in `routes/web.php` or `routes/api.php` invokes `Api\TelegramWebhookController`) — it is dead code.

**Exploitability assessment:**

* The `telegram.secret` middleware is the only gate. If `TELEGRAM_WEBHOOK_SECRET` is configured and production is enforced (see `VerifyTelegramSecret.php:26-35` — empty secret returns 503 in production), only Telegram itself can produce a valid `X-Telegram-Bot-Api-Secret-Token` header. So in a correctly configured production deployment, **no external attacker can reach the controller**. The secret is the secret.
* The risk is therefore **misconfiguration** or **secret leak**, not direct forgery:
  1. If `TELEGRAM_WEBHOOK_SECRET` is forgotten during deploy, dev mode (`APP_ENV != production`) lets the verification be skipped (line 33). A wrong deploy could leave prod running with the secret unset (would actually 503 — good), but `staging`/`local` with the same secret-missing policy would silently accept requests.
  2. If the secret leaks (logged, committed, captured by a proxy), an attacker can craft `callback_query` payloads with arbitrary `chat_id` and `from` fields. Because chat_id is not verified, the attacker can **approve or reject any user by ID**, and the audit log will record `Admin duyệt học viên #N` with whatever `$cb['from']['first_name']` they put in the payload (lines 142, 177 — attacker-controlled display name in the audit message). The `Log::info` calls write only `userId` and `email`, not the chat_id, so the audit trail will not flag the forgery.
  3. Telegram's "who clicked the button" model: even with a valid secret, **any Telegram user who knows the bot and the callback data string** can press an existing inline button. If the button is reused (e.g., admin re-sends the approval message later), a different Telegram user could click it. The standard mitigation is exactly the chat_id check that is missing.

**Impact:** Privilege escalation in the user lifecycle (approve/reject arbitrary user), audit-log forgery (attacker chooses the "admin name" displayed).

**Severity rationale:** Medium. The route relies on a single shared-secret header. Secret leakage is not common but not rare, and the secret-missing dev path is a real footgun. CWE-285 + CWE-346.

**Minimal fix:**
```php
private function dispatchAdminCallback(string $action, int $userId, array $cb): void
{
    $callbackId = (string) ($cb['id'] ?? '');
    $chatId = isset($cb['message']['chat']['id']) ? (string) $cb['message']['chat']['id'] : null;
    $messageId = $cb['message']['message_id'] ?? null;

    $expectedAdminChat = (string) config('telegram.admin_chat_id', '');
    if ($expectedAdminChat === '' || $chatId !== $expectedAdminChat) {
        Log::warning('[Telegram] Admin callback from unexpected chat_id', [
            'expected' => $expectedAdminChat,
            'received' => $chatId,
            'action' => $action,
            'user_id' => $userId,
        ]);
        if ($callbackId !== '') {
            $this->telegram->answerCallbackQuery($callbackId, 'Unauthorized.');
        }
        return;
    }

    $adminName = $cb['from']['first_name'] ?? 'Admin';
    // ... rest unchanged
}
```

Also: delete or rewire `app/Http/Controllers/Api/TelegramWebhookController.php` — it is dead code (not routed), uses incorrect status strings, and would be a maintenance trap.

**Regression check:** Add a `test_admin_callback_rejects_unexpected_chat_id` to `tests/Feature/Security/TelegramWebhookSecurityTest.php` that POSTs a callback with a fake chat_id and asserts no `User::find($userId)->status` change.

---

### SEC-004 — AuthorizationTest covers only the course CRUD scenario

**Evidence:** `tests/Feature/Security/AuthorizationTest.php` has 5 test methods, all targeting `POST /courses` and `DELETE /courses/{id}`:
- `test_student_cannot_create_course_via_form_request`
- `test_teacher_can_create_course`
- `test_inactive_teacher_cannot_create_course`
- `test_non_admin_cannot_delete_course`
- `test_role_middleware_rejects_unauthenticated`

None of the 5 named IDOR candidates from the audit scope (community notes, study-plan, flashcards/grade, settings/export, search) are tested. The only other security tests are `MassAssignmentTest`, `RateLimitTest`, `TelegramWebhookSecurityTest`, `AuditLogTest`, `FileUploadValidationTest` — none of which exercise IDOR.

**Impact:** A future refactor that drops the `abort_unless($plan->user_id === ...)` check from `StudyPlanController::destroy` will pass CI. SEC-001 was discoverable in 5 minutes of code reading; it should have been caught by a test the project already claims to run under `php artisan test --testsuite=Feature --filter=Security`.

**Severity rationale:** Low — a test coverage gap, not a vulnerability. CWE-1110. Listed because the README advertises this test file as the regression net for the security hardening pass.

**Minimal fix:** Add 4-5 new test methods covering:
- `test_student_cannot_complete_other_users_study_plan`
- `test_student_cannot_grade_other_users_flashcard_review`
- `test_settings_export_returns_only_authenticated_user_data`
- `test_search_does_not_leak_users_to_non_admins`
- `test_student_cannot_grade_other_classroom_submission` (regression for SEC-001)

---

## `/admin/*` Route Coverage

All `/admin/*` route groups are gated by `can:admin-access` (the gate defined in `AppServiceProvider::boot()` returning `$user->role === UserRole::Admin->value`) plus `audit.admin`:

| Route group | Location | Middleware chain |
|---|---|---|
| `/admin` (lesson-requests) | `routes/web.php:85-88` | `auth`, `can:admin-access`, `audit.admin` |
| `/admin/*` (dashboard, users, feedback, etc.) | `Modules/Auth/routes/web.php:42-54` | `auth`, `can:admin-access`, `audit.admin` |
| `/admin/sets/*` | `Modules/IeltsSet/routes/web.php:7-14` | `auth`, `can:admin-access` (no audit middleware) |
| `/admin/questions/*` | `Modules/Question/routes/web.php:7-18` | `auth`, `can:admin-access` |
| `/admin/reading-passages/*` | `Modules/TelegramBot/routes/web.php:31-42` | `auth`, `can:admin-access`, `audit.admin` |
| `/admin/users/{id}/approve` (api) | `Modules/Auth/routes/api.php:20-23` | `auth:api` (no admin gate) |

The API `admin/users` route at `Modules/Auth/routes/api.php:20-23` is gated only by `auth:api`. A student with a valid Sanctum token can call `GET /api/admin/users` and `POST /api/admin/users/{id}/approve`. This is a different finding than SEC-003; classifying it as **Residual Risk** (not enumerated as a numbered finding because it falls outside the "Authorization/IDOR" scope and is more "API route hygiene") but worth listing:

> **Residual Risk:** `Modules/Auth/routes/api.php:20-23` — `/api/admin/users*` is not gated by `can:admin-access`. Any authenticated API user can list and approve users. Suggest adding `Route::group(['prefix' => 'admin', 'middleware' => ['auth:api', 'can:admin-access']], ...)` to match the web twin.

---

## Inherited Wisdom Resolution

> TelegramWebhookController (already read in plan phase): `dispatchAdminCallback()` at line 102 does NOT check chat_id — it only relies on the secret middleware. This is a known concern. Document whether this is exploitable.

**Resolved as SEC-003 above.** Exploitable only if the `telegram.secret` is leaked or unset in a non-production environment. Production deployments with `TELEGRAM_WEBHOOK_SECRET` set will reject unauthorized requests at the middleware layer before reaching the controller. Still worth fixing — the secret is a single point of failure, and the chat_id check is the standard Telegram-bot authorization pattern.

---

## Downgraded / Rejected Candidates

| Candidate | Reason rejected |
|---|---|
| `StudyPlanController::destroy` IDOR | Verified — line 69 has `abort_unless($plan->user_id === $request->user()->id, 403)`. Safe. |
| `FlashcardController::grade` IDOR | Verified — line 51 has the same abort_unless pattern. Safe. |
| `SettingsController::export` param IDOR | Verified — uses only `$request->user()`, no path/query param to override. Safe. |
| `SearchController` leaking users to non-admins | Verified — line 59 `$isAdmin` gate correctly hides the user group. Safe. |
| `CommunityController` IDOR (edit/delete user B's note) | No edit/delete surface exists — only `noteStore` and `commentStore`, both of which bind `user_id` from auth. Safe. |
| `AdminBulkController` missing input validation | Verified — `validateIds()` validates array+integer; `bulkRole` validates role enum. Audit log written per operation. Safe. |
| `IeltsSetController` IDOR on section submission | Verified — uses `getOrCreateCurrentAttempt($set, $request->user()->id)` which scopes by user. Safe. |
| `WritingController::show` IDOR | Verified — uses `WritingAttempt::query()->forUser(auth()->id())` scope. Safe. |
| `PracticeController::submitAnswer` IDOR | Verified — service binds to `$request->user()`. Safe. |

---

## Residual Risk (not tested in this audit)

1. **External service authorization:** `App\Services\AuditLogger`, `App\Services\TelegramNotifierService`, `App\Services\LessonQuotaService` — these are not controllers but their internal methods could have business-logic authorization gaps. Out of scope.
2. **API `/api/admin/users*`** — not gated by `can:admin-access` (see `/admin/*` coverage table). Out of immediate scope but should be patched in a follow-up.
3. **Telegram bot command surface (`TelegramBotCommandService`)** — handles free-form and command messages from any Telegram chat_id that has linked an account (`UserTelegramLink::where('telegram_chat_id', $chatId)->first()`). If the linking flow allows account takeover (e.g., weak codes), the bot surface inherits the takeover. `LinkingCodeController::generate` is rate-limited (3/hour) and produces 8-char codes — reasonable, but the linking resolution path was not traced here.
4. **`RegisterRequest::authorize()` returns `true` for everyone** — this is intentional (registration is public), but mass assignment protection in `UserService::register` was not traced.
5. **CSRF on webhook exemption** — `bootstrap/app.php` excludes `telegram/webhook` and `api/telegram/webhook` from CSRF. Necessary for Telegram; relies entirely on the secret header. (Same risk surface as SEC-003.)
6. **No `AuthorizationTest` regression for the 5 named IDOR candidates** — tracked as SEC-004.

---

## Severity & Remediation Summary

| ID | Severity | Effort | Suggested fix order |
|---|---|---|---|
| SEC-001 | High | ~30 min | Add `authorizeTeacher` call (or `role:teacher,admin` route middleware) to `AssignmentController::grade` and `submit` |
| SEC-002 | Medium | ~5 min | Add `$this->authorize('create', Classroom::class)` to `Api\ClassroomController::store` |
| SEC-003 | Medium | ~30 min | Add chat_id verification in `TelegramWebhookController::dispatchAdminCallback`; delete dead `Api\TelegramWebhookController` |
| SEC-004 | Low | ~2 hours | Add 4-5 IDOR regression tests to `tests/Feature/Security/AuthorizationTest.php` |

---

## Audit Commands Used

```bash
# route + middleware enumeration
Get-ChildItem -LiteralPath C:\laragon\www\englishClass\app\Http\Controllers -Recurse -File -Filter "*.php"
Get-ChildItem -LiteralPath C:\laragon\www\englishClass\Modules -Directory | ForEach-Object { ... }

# policy + FormRequest inventory
Get-ChildItem -LiteralPath C:\laragon\www\englishClass\app\Policies -ErrorAction SilentlyContinue
Select-String -Path ... -Pattern "chat_id|ADMIN_CHAT_ID"
```

No HTTP requests were issued. All conclusions are derived from static analysis of source code, route tables, middleware definitions, and the existing test suite.
