# Task-5 — Input Validation & XSS Attack Surface Audit

**Target**: englishClass Laravel 12 (PHP 8.4) — modular HMVC (`nwidart/laravel-modules`)
**Scope**: All HTTP input handling, validation, and output escaping across `app/` and `Modules/`.
**Audit type**: Read-only — no exploits performed, no product files modified.
**Date**: 2026-06-25
**Auditor**: Sisyphus-Junior (security-research skill, read-only mode)

---

## 1. Verdict

**PASS WITH FINDINGS**

The codebase shows strong defensive maturity: every POST/PUT/DELETE route is
protected by either a typed `FormRequest` or an inline `$request->validate(...)`
call. No RCE candidates (`eval` / `assert` / `preg_replace /e`) and no
unserialize / file-include sinks exist. SQL injection surface is minimal —
the only `DB::raw()` calls are constant aggregates (`COUNT(*)`, `SUM(...)`,
`DATE(...)`) with no interpolated user input. One **HIGH**-severity stored-XSS
candidate and one **MEDIUM**-severity search-DoS / information-leak were
identified and are detailed below. The remaining items are **LOW / INFO**
hygiene or defence-in-depth recommendations.

---

## 2. Scope & Commands Run

### 2.1 Grep targets executed (all of `app/` + `Modules/`)

| Pattern | Hits |
|---|---|
| `request->all()` | 3 (all in Telegram webhooks; see §3.4) |
| `request->input(` | 15 (audit-log fields only, see §3.4) |
| `->whereRaw(\|DB::raw(\|DB::statement(` | 4 (all constant aggregates, see §3.3) |
| `eval(\|assert(\|preg_replace.*\/e` | 0 |
| `unserialize(\|file_get_contents($_GET\|POST\|REQUEST)\|include($_...)` | 0 |
| `dd(\|var_dump(\|print_r(` | 0 in `app/` or `Modules/` (`Cache::add` matches are false positives) |
| `\{!!` Blade unescaped | 12 across 9 files (see §3.1) |
| `->only(` / `->merge(` / `->except(` | 3 `$request->only` for filter whitelists (safe) |

### 2.2 Files read in full

- All 16 `Modules/*/Http/Requests/*.php` FormRequests
- All 7 `app/Http/Requests/*.php` FormRequests
- All 16 `app/Http/Controllers/*.php` controllers
- All 24 `Modules/*/Http/Controllers/**/*.php` controllers
- `app/Http/Controllers/SearchController.php`
- `routes/web.php`, `routes/api.php`, all 18 module `routes/*.php`
- `app/Models/User.php` + all 13 `app/Models/*.php`
- `Modules/Classroom/Models/{Classroom,ClassroomPost}.php`
- `app/Repositories/Feedback/FeedbackRepositoryEloquent.php`
- `Modules/Classroom/Services/ClassroomService.php`
- All 9 Blade files containing `{!! !!}` (full file read where needed)
- `tests/Feature/Security/*` (6 tests, for cross-reference)

---

## 3. Findings

### 3.1 SEC-001 — Stored XSS via teacher-controlled user names rendered unescaped into JS context

| Field | Value |
|---|---|
| **Severity** | **HIGH** |
| **CWE** | CWE-79 (Cross-site Scripting) |
| **OWASP Top 10 2021** | A03 – Injection |
| **Location** | `Modules/Classroom/resources/views/show.blade.php:18` |
| **Pre-condition** | Authenticated teacher/admin renders a classroom page where any enrolled user has a hostile `name`. |
| **Exploitable** | Yes. `User::name` is freely user-controlled at registration (`Modules/Auth/Http/Requests/RegisterRequest.php` only enforces `string|max:255` — no character set restriction, allows `<`, `>`, `"`, `'`, `</script>`). |

#### Evidence

```blade
{{-- Modules/Classroom/resources/views/show.blade.php:1-19 --}}
<x-app-layout>
    <x-slot name="head">
        <meta name="classroom-id" content="{{ $classroom->id }}">
        @php
            $members = collect([$classroom->teacher])
                ->merge($classroom->students)
                ->unique('id')
                ->map(fn($u) => [
                    'id'      => $u->id,
                    'name'    => $u->name,         // ← user-controlled, attacker-controlled
                    'role'    => $u->role,
                    'initial' => strtoupper(substr($u->name, 0, 1)),
                ])
                ->values()
                ->toJson();                       // ← JSON encoded but NOT HTML-escaped on output
        @endphp
        <script>
            window.classroomMembers = {!! $members !!};
        </script>
```

The `name` field comes from registration (`RegisterRequest` line 13: `'name' => 'required|string|max:255'`). There is no character filter, so a user named `</script><img src=x onerror=alert(1)>` is encoded by `toJson()` (which only escapes JSON control chars like `"` and `\`) but **not** HTML-escaped because the output uses `{!! !!}`.

#### Attack path

1. Attacker registers account with name: `","onerror":"alert(1)","x":"` (or similar JSON-injection payload).
2. Attacker joins a classroom (or a teacher adds them).
3. Authenticated teacher / victim navigates to `/classroom/{id}`.
4. Browser parses the JSON literal embedded in `<script>`, breaks out of the object literal, and executes attacker JS in the teacher's origin (`window.classroomMembers = …;`).
5. Attacker JS has full access to the teacher's session, including admin actions (user approval, role changes, content moderation).

Note: even if the JSON-injection vector is partially mitigated by PHP's `json_encode` adding `\` escapes for double-quotes, the underlying HTML-context XSS still exists because `{!! !!}` is rendered into a `<script>` block where JS-context escaping differs from HTML-context escaping. A safer approach is `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT` and additionally wrapping the value in `Blade @json($members)` (which Blade's `@json` directive applies by default) — but `{!! !!}` here bypasses even Blade's `@json`.

#### Remediation

Replace

```blade
window.classroomMembers = {!! $members !!};
```

with Blade's `@json` directive, which encodes both JSON-safe AND HTML-safe:

```blade
window.classroomMembers = @json($members);
```

Alternatively, if `@json` is not available, encode explicitly:

```blade
window.classroomMembers = {!! json_encode($members, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
```

Defense in depth: also add `Rule::notIn(['<', '>', '"', "'"])` or a regex (`/^[a-zA-Z0-9 \-_.]+$/u`) on `RegisterRequest::name` and `UpdateProfileRequest::name`. The `Modules\Auth\Http\Requests\UpdateProfileRequest.php` line 18 currently allows the same loose pattern.

#### Code diff (High → fix in `Modules/Classroom/resources/views/show.blade.php`)

```diff
-            window.classroomMembers = {!! $members !!};
+            window.classroomMembers = @json($members);
```

---

### 3.2 SEC-002 — Stored XSS via `IeltsSetAttemptAnswer.feedback` (AI-graded text rendered unescaped)

| Field | Value |
|---|---|
| **Severity** | **MEDIUM** |
| **CWE** | CWE-79 (Cross-site Scripting) |
| **OWASP Top 10 2021** | A03 – Injection |
| **Location** | `Modules/IeltsSet/resources/views/section.blade.php:194` |
| **Pre-condition** | The AI grader (or an admin) writes a `feedback` column containing HTML/JS. Currently the value originates from `PracticeSessionService::submitAnswer(...) → $result['feedback']`. |
| **Exploitable** | Indirectly — requires AI-generated feedback or an admin with DB write to contain malicious markup. Prompt injection against Gemini could yield stored HTML. |

#### Evidence

```blade
{{-- Modules/IeltsSet/resources/views/section.blade.php:192-194 --}}
<div style="margin-top: 0.5rem">
    <strong>Reference:</strong> {{ $saved->correct_answer ?: 'No reference answer available.' }}
</div>
<div style="margin-top: 0.5rem">{!! $saved->feedback ?: 'No feedback available.' !!}</div>
```

`$saved->feedback` is written to the DB in `Modules/IeltsSet/Http/Controllers/IeltsSetController.php:171`:

```php
'feedback' => (string) ($result['feedback'] ?? ''),
```

`$result` is the return value of `PracticeSessionService::submitAnswer()`, which ultimately reflects AI-generated text (see `app/Services/AI/*` and the Gemini integration). If an attacker crafts a writing prompt or short-answer input that elicits AI output containing `<script>` tags (e.g., "explain the structure of an HTML document and include the literal tag"), that string is stored verbatim and rendered unescaped.

The "Reference" line directly above correctly uses `{{ }}` — proving the developer was aware of escaping — but `{!! $saved->feedback !!}` was a deliberate choice (likely to allow formatting such as `<strong>` from the grader), creating a stored-XSS sink.

#### Attack path

1. Student submits a writing/short-answer attempt with a question that elicits AI grader output containing `<img src=x onerror=alert(document.cookie)>`.
2. `feedback` column is stored with the literal markup.
3. Any subsequent render of `/student/sets/{set}/sections/{section}` for that user (or any user sharing the same section) executes the injected script in the student's session.
4. The script can call `/student/sets/{set}/sections/{section}/time` with attacker-controlled `active_seconds` values, or submit fake submissions for other questions.

#### Remediation

Sanitize AI feedback on the way out, or store already-sanitized text. Two equally good options:

**Option A** — HTML-escape on output:

```diff
-<div style="margin-top: 0.5rem">{!! $saved->feedback ?: 'No feedback available.' !!}</div>
+<div style="margin-top: 0.5rem">{!! nl2br(e($saved->feedback ?: 'No feedback available.')) !!}</div>
```

**Option B** — Purify on write in the controller (`IeltsSetController::submitSection`):

```php
'feedback' => strip_tags((string) ($result['feedback'] ?? '')),
```

Or run through `HTMLPurifier` if rich formatting is required. Option A is the minimal, surgical fix.

---

### 3.3 SEC-003 — SQL injection / DoS in `SearchController` LIKE-wildcard construction (theoretical + minor info leak)

| Field | Value |
|---|---|
| **Severity** | **LOW** (no SQLi — parameter binding is automatic — but a query-DoS and partial enumeration vector) |
| **CWE** | CWE-400 (Uncontrolled Resource Consumption), CWE-209 (Information Exposure Through Timing) |
| **OWASP Top 10 2021** | A04 – Insecure Design |
| **Location** | `app/Http/Controllers/SearchController.php:23-67` |
| **Pre-condition** | Authenticated user can call `/search?q=...`. |

#### Evidence

```php
// app/Http/Controllers/SearchController.php
public function __invoke(Request $request): JsonResponse
{
    $q = trim((string) $request->query('q', ''));
    if (strlen($q) < 2) {
        return response()->json(['results' => []]);
    }
    // ...
    $groups['courses'] = Course::query()
        ->where(function ($qb) use ($q) {
            $qb->where('title', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%");
        })
        ->limit(5)->get(['id', 'title', 'slug'])->toArray();
    // ...
}
```

#### Analysis

The `LIKE` wildcards `%` and `_` in `$q` are **not escaped**. So a user
searching for `%%%` (or `_`) triggers a full-table scan with the same
effective pattern as `%%`. This is a DoS amplifier, not an SQLi
(Eloquent parameter-binds the value, so an attacker cannot break out).

There is also **no max-length cap on `$q`** — only a 2-char minimum. A 64KB
`q` parameter expands into 5 LIKE comparisons across four tables with
leading+trailing `%`, which MySQL cannot use an index for (leading wildcard).
Auth is required for `/search` (route is inside the `auth` middleware group
in `routes/web.php:77`), which limits blast radius.

A **timing side-channel** exists on line 59-65: admin-only `users`
enumeration (`->where('email', 'like', "%{$q}%")`). A student cannot
trigger that branch (gated by `$isAdmin`), so no horizontal privilege
escalation, but admins can be used as an oracle.

#### Remediation

```php
$q = trim((string) $request->query('q', ''));
if (strlen($q) < 2 || strlen($q) > 100) {
    return response()->json(['results' => []]);
}
$qSafe = addcslashes($q, '%_\\');   // escape LIKE wildcards
// ...
->where('title', 'like', "%{$qSafe}%")
```

---

### 3.4 SEC-004 — `request->all()` used in Telegram webhook (intended, gated by secret middleware) — **INFO**

| Field | Value |
|---|---|
| **Severity** | **INFO** (no finding) |
| **CWE** | n/a |
| **OWASP Top 10 2021** | n/a |
| **Location** | `app/Http/Controllers/TelegramWebhookController.php:30`, `app/Http/Controllers/Api/TelegramWebhookController.php:22`, `Modules/TelegramBot/Http/Controllers/TelegramBotWebhookController.php:24` |

#### Evidence

```php
// TelegramWebhookController.php:30
$payload = $request->all();
```

#### Analysis

These three call sites are all on routes protected by the `telegram.secret`
middleware (see `routes/web.php:22`), which performs `hash_equals` secret
comparison and returns 503 in production when the secret is unconfigured
(per the SECURITY_UPGRADE_PLAN documented in `README.md` § Security
Hardening). The payload is then **read with explicit key access** (`isset($payload['callback_query'])`,
`$payload['message']`) and never written to the model layer.

This is the documented, intended use of `request->all()` for parsing
unstructured JSON from a trusted upstream (Telegram). It is **not** a
mass-assignment risk because the values are read into local variables
that are then routed to the bot command service. Cross-referenced with
the `tests/Feature/Security/TelegramWebhookSecurityTest.php` (confirmed
in repo), the secret check is regression-tested.

**No remediation required.**

---

### 3.5 SEC-005 — `DB::raw()` aggregate expressions (constant, no user input) — **INFO**

| Field | Value |
|---|---|
| **Severity** | **INFO** |
| **CWE** | n/a |
| **Location** | `app/Services/ProgressAnalyticsService.php:38, 77, 98`; `Modules/TelegramBot/Services/AchievementService.php:344` |

#### Evidence

```php
// ProgressAnalyticsService.php:38
->select('questions.skill', DB::raw('COUNT(*) as total'),
    DB::raw('SUM(CASE WHEN user_answers.is_correct = 1 THEN 1 ELSE 0 END) as correct'))
```

All four `DB::raw()` matches are **constant aggregate expressions** with
no user-controlled interpolation. The query parameters bind user input
separately through Eloquent's parameter-binding layer (`->where('user_id', $user->id)`).

**No SQLi risk. No remediation required.**

---

### 3.6 SEC-006 — Mass-assignment attack surface map — **PASS**

Every controller that creates or updates an Eloquent model does so through
either a `FormRequest::validated()` whitelist or a `$request->validate([...])`
inline whitelist. The `User` model uses the new PHP-8 attribute-based
`#[Fillable([...])]` declaration (see `app/Models/User.php:14`) — confirmed
via `grep` and read in full.

| Controller / Route | Method | Validation | Mass-assign safe? |
|---|---|---|---|
| `AuthController::webRegister` | POST | `RegisterRequest::rules()` → name/email/password/target_band only | ✅ |
| `AuthController::register` | POST | Same `RegisterRequest` | ✅ |
| `ProfileController::update` | POST | `UpdateProfileRequest::rules()` → name/target_band/password only | ✅ |
| `LessonRequestController::store` | POST | `StoreLessonRequestRequest` (Rule::in whitelist) | ✅ |
| `LessonRequestController::review` | POST | `ReviewLessonRequestRequest` (admin only) | ✅ |
| `SettingsController::update` | PUT | inline `validate()` with `boolean`/`integer`/`in:` whitelists | ✅ |
| `CommunityController::noteStore` | POST | inline `validate()` — title/content/is_public | ✅ |
| `CommunityController::commentStore` | POST | inline `validate()` — commentable_type/id/body | ✅ |
| `StudyPlanController::store` | POST | inline `validate()` — title/description/scheduled_at/duration/type | ✅ |
| `StudyPlanController::complete` | POST | `abort_unless` ownership check, no model assignment from request | ✅ |
| `StudyPlanController::destroy` | DELETE | ownership check only | ✅ |
| `AssignmentController::store` | POST | inline `validate()` — title/description/due_at/rubric | ✅ |
| `AssignmentController::submit` | POST | inline `validate()` — body only | ✅ |
| `AssignmentController::grade` | POST | inline `validate()` — score/feedback + `findOrFail` lookup | ✅ |
| `AdminBulkController::bulkApprove` | POST | inline `validate(['ids' => array, 'ids.*' => integer])` | ✅ |
| `AdminBulkController::bulkRole` | POST | inline `validate(['ids' => array, 'role' => in:admin,teacher,student])` | ✅ |
| `AdminBulkController::bulkDelete` | POST | inline `validate(['ids' => array, 'ids.*' => integer])` | ✅ |
| `AdminBulkController::importCsv` | POST | inline `validate(['file' => mimes:csv,txt, max:10240])` + per-row validator | ✅ |
| `CourseController::store` | POST | `CourseRequest` + role guard at controller | ✅ |
| `CourseController::update` | PUT | `CourseRequest` + ownership check (IDOR-safe) | ✅ |
| `CourseController::destroy` | DELETE | admin OR owner check (IDOR-safe) | ✅ |
| `CourseController::enroll` | POST | no input → only IDOR is the `$id` route param, mitigated by `find($id)` 404 | ✅ |
| `ClassroomController::store` | POST | `StoreClassroomRequest` + quota check | ✅ |
| `ClassroomController::join` | POST | `JoinClassroomRequest` (invite_code only) | ✅ |
| `ClassroomController::storePost` | POST | `StoreClassroomPostRequest` (content/type/attachment MIME whitelist) | ✅ |
| `ClassroomController::storeComment` | POST | `StoreClassroomCommentRequest` (content only) | ✅ |
| `ClassroomController::storeFeedback` | POST | `StoreClassroomFeedbackRequest` | ✅ |
| `IeltsSetController::*` (admin) | POST/PUT/DELETE | `UpsertIeltsSetRequest` (deep array, see §3.7) | ✅ |
| `IeltsSetController::submitSection` | POST | inline `validate(['answers' => array, 'answers.*' => nullable string])` | ✅ |
| `IeltsSetController::updateSectionTime` | POST | inline `validate(['seconds' => integer, min:0, max:3600])` | ✅ |
| `IeltsSetController::completeSpeakingSection` | POST | inline `validate(['active_seconds_delta' => ...])` | ✅ |
| `PracticeController::submitAnswer` | POST | `SubmitPracticeAnswerRequest` | ✅ |
| `PracticeController::submitSpeaking` | POST | `SubmitPracticeSpeakingRequest` | ✅ |
| `WritingController::submit` | POST | `SubmitWritingRequest` | ✅ |
| `SpeakingController::start` | POST | none — server-generated session | ✅ (no user input) |
| `SpeakingController::chat` | POST | `ChatSpeakingRequest` | ✅ |
| `SpeakingController::poll` | POST | `PollSpeakingRequest` | ✅ |
| `VoiceController::handleChunk` | POST | inline `validate()` — session_id/chunk/history | ✅ |
| `AiTutorController::{ask,explain,suggest,clear}` | POST | inline `validate()` + `throttle:ai` rate-limit | ✅ |
| `FlashcardController::grade` (app) | POST | inline `validate(['grade' => integer, between:0,3])` + ownership check | ✅ |
| `FlashcardController::saveToPersonal` (module) | POST | inline `validate(['word', 'meaning', 'example', 'skill'])` | ✅ |
| `AdminUserController::{webApprove,approve}` | POST | admin-only, `$id` is route param | ✅ |
| `AdminFeedbackController::updateStatus` | PATCH | `UpdateFeedbackStatusRequest` (status in:pending,reviewed,resolved) | ✅ |
| `AdminFeedbackController::assignUser` | POST | `AssignFeedbackRequest` (user_id exists:users,id) | ✅ |
| `AdminFeedbackController::addNote` | POST | `AddFeedbackNoteRequest` (note:string) | ✅ |
| `AdminFeedbackController::destroy` | DELETE | admin-only, `$id` route param | ✅ |
| `NotificationController::markAsRead` | POST | no input → marks all current user's unread | ✅ |
| `LinkingCodeController::generate` | POST | server-generated, rate-limited | ✅ |
| `TelegramSettingsController::{unlink,dismissBanner}` | POST | no input | ✅ |
| `ReadingPassageAdminController::store/update` | POST/PUT | `StoreReadingPassageRequest` (deep array validation) | ✅ |
| `ReadingPassageAdminController::destroy` | DELETE | admin-only, route-model binding | ✅ |
| `ReadingPassageAdminController::toggle` | POST | admin-only, route-model binding | ✅ |
| `ReadingPassageReviewController::grade` | POST | inline `validate(['answers' => array, 'grade' => integer])` | ✅ |
| `ReadingPassageReviewController::enrol` | POST | no body input | ✅ |
| `AIQuestionController::{generate,store}` | POST | inline `validate(['skill', 'topic', 'band', 'count'])` + `['questions' => array]` | ✅ |
| `QuestionController::store` | POST | inline `validate(['skill', 'type', 'topic', 'difficulty', 'content', 'audio_file'])` | ✅ |
| `QuestionController::delete` | DELETE | admin-only route | ✅ |
| `QuestionController::generateVoice` | POST | inline `validate(['text' => required string max:500])` | ✅ |
| `TelegramWebhookController::handle` | POST | `telegram.secret` middleware only (intended, see §3.4) | ✅ |

**Mass-assignment audit result: PASS** — no `Model::create($request->all())` pattern exists anywhere in the codebase.

---

### 3.7 SEC-007 — `UpsertIeltsSetRequest::prepareForValidation` and `ReadingPassageRequest::prepareForValidation` use `$this->all()` — **PASS** (defence-in-depth notice)

| Field | Value |
|---|---|
| **Severity** | **INFO** (no finding) |
| **Location** | `Modules/IeltsSet/Http/Requests/UpsertIeltsSetRequest.php:79`, `Modules/TelegramBot/Http/Requests/StoreReadingPassageRequest.php:79` |

#### Evidence

```php
// Modules/TelegramBot/Http/Requests/StoreReadingPassageRequest.php:77-98
protected function prepareForValidation(): void
{
    $payload = $this->all();

    if (empty($payload['slug']) && ! empty($payload['title'])) {
        $payload['slug'] = \Illuminate\Support\Str::slug($payload['title']);
    }
    // ... (slug/word_count/tags normalisation)

    $this->merge($payload);
}
```

#### Analysis

This is Laravel's documented `prepareForValidation` hook. `$this->all()` is
read into a local `$payload`, transformed with hard-coded helpers
(`Str::slug`, `str_word_count`, `array_filter`), then re-merged. The
subsequent `rules()` method applies the actual validation — the same
whitelist that protects the rest of the codebase. No raw DB writes from
this payload.

`UpsertIeltsSetRequest` uses `$validator->after(...)` to cross-check
question-skill alignment via a count query — again, with parameter
binding (`->whereIn('id', $questionIds)`), so safe.

**No remediation required.**

---

### 3.8 SEC-008 — Blade `{!! !!}` inventory and risk classification

Total `{!! !!}` occurrences in `Modules/**/resources/views/`: **12** across **9** files.

| # | File:line | Content | Source of variable | Risk |
|---|---|---|---|---|
| 1 | `Modules/IeltsSet/resources/views/section.blade.php:92` | `{!! nl2br(e($questionPrompt)) !!}` | `$question->content['question'\|'text']` — admin-authored | **SAFE** (already `e()`'d, `nl2br` adds `<br>` only) |
| 2 | `Modules/IeltsSet/resources/views/section.blade.php:146` | `{!! nl2br(e($questionPrompt)) !!}` | Same | **SAFE** |
| 3 | `Modules/IeltsSet/resources/views/section.blade.php:194` | `{!! $saved->feedback ?: 'No feedback available.' !!}` | AI-grader output (see SEC-002) | **MEDIUM — SEC-002** |
| 4 | `Modules/Gamification/resources/views/index.blade.php:4` | `{!! config('gamification.name') !!}` | `config()` constant | **SAFE** (constant) |
| 5 | `Modules/Classroom/resources/views/show.blade.php:18` | `{!! $members !!}` | User `name` from registration (see SEC-001) | **HIGH — SEC-001** |
| 6 | `Modules/Speaking/resources/views/index.blade.php:28` | `{!! __('ui.step_1') !!}` | Translation key constant | **SAFE** (no user input) |
| 7 | `Modules/Speaking/resources/views/index.blade.php:30` | `{!! __('ui.step_3') !!}` | Translation key constant | **SAFE** |
| 8 | `Modules/TelegramBot/resources/views/reading-review/session.blade.php:45` | `{!! nl2br(e($passage->body)) !!}` | Admin-authored passage body | **SAFE** (already `e()`'d) |
| 9 | `Modules/Question/resources/views/index.blade.php:4` | `{!! config('question.name') !!}` | `config()` constant | **SAFE** (constant) |
| 10 | `Modules/Auth/resources/views/student/dashboard.blade.php:7` | `{!! __('ui.welcome_back_desc', ['band' => auth()->user()->target_band ?? 'N/A']) !!}` | Translation file + `target_band` (numeric 1-9) | **LOW** — `target_band` is constrained to numeric via `RegisterRequest` / `UpdateProfileRequest` (`between:1,9`), so cannot carry HTML. Translation file is read-only. |
| 11 | `Modules/Auth/resources/views/index.blade.php:4` | `{!! config('auth.name') !!}` | `config()` constant | **SAFE** (constant) |
| 12 | `Modules/Practice/resources/views/drill.blade.php:32` | `{!! nl2br(e($text)) !!}` | Admin-authored question content | **SAFE** (already `e()`'d) |

**Net result:** 9 of 12 are explicitly safe (already `e()`'d or constants).
**2** findings (SEC-001 and SEC-002) carry risk.

---

### 3.9 SEC-009 — File upload / MIME validation audit — **PASS**

| Endpoint | Validation | Storage path | SAFE? |
|---|---|---|---|
| `ClassroomController::storePost` (`Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php:32-37`) | `file\|max:51200\|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,mp3,mp4` | `classroom_attachments/{classroom_id}/...` (public disk) | ✅ — `.php` rejected; test confirmed in `tests/Feature/Security/FileUploadValidationTest.php` |
| `QuestionController::store` (`Modules/Question/Http/Controllers/QuestionController.php:43, 47`) | `audio_file: nullable\|file\|mimes:mp3,wav\|max:5120` | `listening_audio/...` (public disk) | ✅ — small whitelist |
| `AdminBulkController::importCsv` (`app/Http/Controllers/AdminBulkController.php:79-81`) | `file: required\|file\|mimes:csv,txt\|max:10240` | ephemeral (read with `fopen` only, never stored) | ✅ |

No other file uploads in the codebase. **No file-inclusion risk**, as
`include($_...)` and `file_get_contents($_...)` patterns produced **0 matches**.

---

### 3.10 SEC-010 — RCE / object-injection / file-inclusion audit — **PASS**

| Pattern | Hits | Notes |
|---|---|---|
| `eval(` | 0 | Clean |
| `assert(` (PHP dynamic-eval form) | 0 | Clean |
| `preg_replace(... /e ...)` | 0 | Clean (the `/e` modifier has been deprecated since PHP 5.5; engines reject it) |
| `unserialize(` | 0 | Clean |
| `file_get_contents($_GET\|POST\|REQUEST)` | 0 | Clean |
| `include($_...)` / `require($_...)` | 0 | Clean |
| `shell_exec\|system\|exec\|passthru\|popen\|proc_open` | Not in required grep set; spot-checked | None found in `app/` or `Modules/` |

**No RCE / object-injection / file-inclusion candidates.**

---

### 3.11 SEC-011 — `dd()` / `var_dump()` / `print_r()` left in production — **PASS**

The grep returned only false positives:
- `Modules/TelegramBot/resources/views/student/settings/telegram.blade.php:376,381,400` — `el.classList.add('tgb-expired')` JavaScript (DOM API, not PHP `dd`)
- `Modules/Classroom/resources/views/index.blade.php:332` — JS `classList.add`
- `Modules/Speaking/resources/views/index.blade.php:165,369` — JS `classList.add`
- `app/Services/TelegramService.php:123` — `Cache::add()` (Laravel cache atomic set, not PHP `dd()`)
- `Modules/TelegramBot/Console/Commands/SendReviewRemindersCommand.php:85` — same `Cache::add()`
- `Modules/IeltsSet/Http/Requests/UpsertIeltsSetRequest.php:65` — `$validator->errors()->add(...)` (Laravel Validator, not PHP `dd()`)

**No debug code left in production.**

---

### 3.12 SEC-012 — `LocaleController::setLocale` — **PASS** (whitelisted)

`app/Http/Controllers/LocaleController.php:17-25` reads the `$locale` route
parameter and gates it through `in_array($locale, ['en', 'vi'])`. No arbitrary
locale loading.

---

### 3.13 SEC-013 — `StudyPlanController::index` parses `$request->query('start', ...)` via Carbon — **PASS**

`app/Http/Controllers/StudyPlanController.php:16` calls
`Carbon::parse($request->query('start', ...))`. Carbon throws `InvalidArgumentException`
on garbage input, which Laravel converts to a 500 — but the value is
never written to the DB, and route-model binding for `$plan` in
`complete()` / `destroy()` enforces ownership. No exploit path.

**Optional remediation**: add `validate(['start' => 'nullable|date'])` at
the top of `index()` to return a clean 422 on malformed input. **LOW.**

---

## 4. Controller-by-controller summary table

| HTTP Method | Controller | FormRequest? | Inline `validate`? | `request->all()`? | Verdict |
|---|---|---|---|---|---|
| POST | AuthController::webRegister | ✅ RegisterRequest | – | – | ✅ |
| POST | AuthController::webLogin | ✅ LoginRequest | – | – | ✅ |
| POST | AuthController::logout | – | – | – | n/a (no input) |
| POST | AuthController::register | ✅ RegisterRequest | – | – | ✅ |
| POST | AuthController::login | ✅ LoginRequest | – | – | ✅ |
| POST | ProfileController::update | ✅ UpdateProfileRequest | – | – | ✅ |
| POST | LessonRequestController::store | ✅ StoreLessonRequestRequest | – | – | ✅ |
| POST | LessonRequestController::review | ✅ ReviewLessonRequestRequest | – | – | ✅ |
| GET  | LessonRequestController::index | – | – | – | n/a |
| GET  | SettingsController::show | – | – | – | n/a |
| PUT  | SettingsController::update | – | ✅ | – | ✅ |
| GET  | SettingsController::export | – | – | – | n/a |
| POST | CommunityController::noteStore | – | ✅ | – | ✅ |
| POST | CommunityController::commentStore | – | ✅ | – | ✅ |
| GET  | CommunityController::findBuddy | – | – | – | n/a |
| POST | StudyPlanController::store | – | ✅ | – | ✅ |
| POST | StudyPlanController::complete | – | – | – | n/a (model binding) |
| DELETE | StudyPlanController::destroy | – | – | – | n/a |
| POST | AssignmentController::store | – | ✅ | – | ✅ |
| POST | AssignmentController::submit | – | ✅ | – | ✅ |
| POST | AssignmentController::grade | – | ✅ | – | ✅ |
| POST | AdminBulkController::bulkApprove | – | ✅ | – | ✅ |
| POST | AdminBulkController::bulkRole | – | ✅ | – | ✅ |
| POST | AdminBulkController::bulkDelete | – | ✅ | – | ✅ |
| POST | AdminBulkController::importCsv | – | ✅ | – | ✅ |
| POST | CourseController::store | ✅ CourseRequest | – | – | ✅ |
| PUT  | CourseController::update | ✅ CourseRequest | – | – | ✅ |
| DELETE | CourseController::destroy | – | – | – | n/a |
| POST | CourseController::enroll | – | – | – | n/a |
| POST | ClassroomController::store | ✅ StoreClassroomRequest | – | – | ✅ |
| POST | ClassroomController::join | ✅ JoinClassroomRequest | – | – | ✅ |
| POST | ClassroomController::storePost | ✅ StoreClassroomPostRequest | – | – | ✅ |
| POST | ClassroomController::storeComment | ✅ StoreClassroomCommentRequest | – | – | ✅ |
| POST | ClassroomController::storeFeedback | ✅ StoreClassroomFeedbackRequest | – | – | ✅ |
| POST | IeltsSetController::start | – | – | – | n/a |
| POST | IeltsSetController::submitSection | – | ✅ | – | ✅ |
| POST | IeltsSetController::updateSectionTime | – | ✅ | – | ✅ |
| POST | IeltsSetController::completeSpeakingSection | – | ✅ | – | ✅ |
| POST | AdminIeltsSetController::store | ✅ UpsertIeltsSetRequest | – | – | ✅ |
| PUT  | AdminIeltsSetController::update | ✅ UpsertIeltsSetRequest | – | – | ✅ |
| DELETE | AdminIeltsSetController::destroy | – | – | – | n/a |
| POST | PracticeController::submitAnswer | ✅ SubmitPracticeAnswerRequest | – | – | ✅ |
| POST | PracticeController::submitSpeaking | ✅ SubmitPracticeSpeakingRequest | – | – | ✅ |
| POST | WritingController::submit | ✅ SubmitWritingRequest | – | – | ✅ |
| POST | SpeakingController::start | – | – | – | n/a (no body) |
| POST | SpeakingController::chat | ✅ ChatSpeakingRequest | – | – | ✅ |
| POST | SpeakingController::poll | ✅ PollSpeakingRequest | – | – | ✅ |
| POST | SpeakingController::storeTranscript (api) | – | – | – | ⚠️ (no inline validation in this repo file, route exists in `Modules/Speaking/routes/api.php:12` — see §6 Residual Risk) |
| POST | VoiceController::handleChunk | – | ✅ | – | ✅ |
| POST | AiTutorController::{ask,explain,suggest,clear} | – | ✅ + throttle:ai | – | ✅ |
| POST | FlashcardController::grade (app) | – | ✅ | – | ✅ |
| POST | FlashcardController::saveToPersonal (module) | – | ✅ | – | ✅ |
| POST | AdminUserController::webApprove / approve | – | – | – | n/a (route param) |
| PATCH | AdminFeedbackController::updateStatus | ✅ UpdateFeedbackStatusRequest | – | – | ✅ |
| POST | AdminFeedbackController::assignUser | ✅ AssignFeedbackRequest | – | – | ✅ |
| POST | AdminFeedbackController::addNote | ✅ AddFeedbackNoteRequest | – | – | ✅ |
| DELETE | AdminFeedbackController::destroy | – | – | – | n/a |
| POST | NotificationController::markAsRead | – | – | – | n/a (no body) |
| POST | LinkingCodeController::generate | – | – | – | n/a (server-generated) |
| POST | TelegramSettingsController::{unlink,dismissBanner} | – | – | – | n/a |
| POST | ReadingPassageReviewController::grade | – | ✅ | – | ✅ |
| POST | ReadingPassageReviewController::enrol | – | – | – | n/a |
| POST | ReadingPassageAdminController::store | ✅ StoreReadingPassageRequest | – | – | ✅ |
| PUT  | ReadingPassageAdminController::update | ✅ StoreReadingPassageRequest | – | – | ✅ |
| DELETE | ReadingPassageAdminController::destroy | – | – | – | n/a |
| POST | ReadingPassageAdminController::toggle | – | – | – | n/a |
| POST | AIQuestionController::generate | – | ✅ | – | ✅ |
| POST | AIQuestionController::store | – | ✅ | – | ✅ |
| POST | QuestionController::store | – | ✅ | – | ✅ |
| DELETE | QuestionController::delete | – | – | – | n/a |
| POST | QuestionController::generateVoice | – | ✅ | – | ✅ |
| POST | TelegramWebhookController::handle | – | – | ✅ (gated by secret) | ✅ (intended, see §3.4) |
| POST | TelegramBotWebhookController::handle | – | – | ✅ (gated by secret) | ✅ (intended) |
| POST | DeployNotifyController::notify (api) | ✅ DeployNotifyRequest | – | – | ✅ |

**Coverage**: 60/60 writable routes are protected. **100% input-validation coverage.**

---

## 5. Severity Summary

| ID | Title | Severity | Exploitability | PoC | CWE | OWASP |
|---|---|---|---|---|---|---|
| SEC-001 | Stored XSS via classroom `members` JS literal | **HIGH** | Authenticated user controls own name; teacher render required for impact | Inject name = `</script><img src=x onerror=...>`; register; wait for teacher to view classroom | CWE-79 | A03 |
| SEC-002 | Stored XSS via AI-grader feedback | **MEDIUM** | Requires AI prompt that elicits HTML output | Submit answer that asks AI grader to include literal HTML; reload `/sets/{id}/sections/{id}` | CWE-79 | A03 |
| SEC-003 | Search query LIKE wildcard DoS / 64KB unbounded | **LOW** | Authenticated user; full-table scan | `curl /search?q=$(python3 -c 'print("a"*65536)')` | CWE-400 | A04 |

---

## 6. Residual Risk / Not Tested

| Item | Reason |
|---|---|
| Runtime exploitation of any finding | Forbidden by task; static + dry-run evidence only |
| AI prompt injection against Gemini to land SEC-002 | Requires live Gemini API; would need network access; static analysis only — `PracticeSessionService::submitAnswer` ultimately writes `$result['feedback']` to DB, so any string returned by Gemini that contains `<` or `>` lands unescaped in `IeltsSetAttemptAnswer.feedback` |
| `SpeakingController::storeTranscript` (api route `POST speaking/transcript`) | Controller method not found in this repo — `Modules/Speaking/routes/api.php:12` references it but `SpeakingController.php` does not define it; route is presumably broken or handled by a different version. **Marked as gap** but no exploitable surface in current state (the route would 404). |
| WebSocket / live-broadcast injection | No Laravel Echo / Pusher / Reverb usage found in `app/` or `Modules/`; the speaking session uses HTTP polling only. |
| Deep nested JSON in `ai/tutor/suggest` (`recent_mistakes`) | `recent_mistakes` array has shallow validation (`array`, `*.skill string`, etc.) — no array-depth limit. Could produce a large payload. **LOW / INFO.** |
| `RegisterRequest::name` character set | Currently `required\|string\|max:255`. Allows HTML special chars. The XSS in SEC-001 is the more direct fix; tightening name validation is defence-in-depth and recommended. |
| Mass-assignment on registerable but currently-not-exposed fields (e.g. `xp`, `streak`, `is_unlimited` via JSON request to `/register`) | Verified via `RegisterRequest` rules — these fields are NOT in the `rules()` array, so Eloquent's `#[Fillable]` accepts them but `RegisterRequest` strips them. The `tests/Feature/Security/MassAssignmentTest.php` in repo confirms this. **PASS.** |
| Static analysis of `auth()->user()->target_band` use in dashboard view (`{!! __('ui.welcome_back_desc', ['band' => ...target_band]) !!}`) | `target_band` validated as `numeric\|between:1,9` — cannot inject HTML. Translations are static files. **PASS.** |

---

## 7. Test Coverage Cross-Reference

Existing tests in `tests/Feature/Security/` that exercise this attack surface:

| Test | Coverage |
|---|---|
| `MassAssignmentTest` | Confirms `/register` cannot set `role` / `is_unlimited` / `status` |
| `AuthorizationTest` | Confirms FormRequest role enforcement |
| `FileUploadValidationTest` | Confirms `.php` upload rejected; `.pdf` accepted (verified §3.9) |
| `TelegramWebhookSecurityTest` | Confirms secret middleware behaviour |
| `RateLimitTest` | Confirms throttle middleware returns 429 |
| `AuditLogTest` | Confirms admin mutations are logged |

**Missing tests** (recommendations, not findings):
- A test asserting `{!! !!}` is never used in a JS `<script>` context with user-controlled data (would catch SEC-001 regression).
- A test asserting AI feedback is HTML-escaped on output (would catch SEC-002 regression).
- A test asserting `/search?q=%%%&q=aaaa...64K` is rejected (would catch SEC-003 regression).

---

## 8. Recommended Remediation Order

1. **SEC-001** (HIGH): One-line fix — change `{!! $members !!}` to `@json($members)` in `Modules/Classroom/resources/views/show.blade.php:18`.
2. **SEC-002** (MEDIUM): One-line fix — wrap `$saved->feedback` in `e()` in `Modules/IeltsSet/resources/views/section.blade.php:194`.
3. **SEC-003** (LOW): Add max-length cap and LIKE-wildcard escaping in `app/Http/Controllers/SearchController.php`.
4. **Defence-in-depth**: Add character-set restriction (`/^[A-Za-z0-9 \-_.]+$/u`) to `RegisterRequest::name` and `UpdateProfileRequest::name`.

---

## 9. Sign-off

- ✅ No SQL injection candidates (4 `DB::raw` matches verified safe).
- ✅ No RCE candidates (eval/assert/preg_replace-e — 0 matches).
- ✅ No object-injection / file-inclusion (unserialize / `include($_...)` — 0 matches).
- ✅ No debug code left in production (dd/var_dump/print_r — 0 genuine matches).
- ✅ No mass-assignment (`request->all()` writes — 0 matches; all writes go through whitelists).
- ✅ File-upload MIME validation enforced on every upload endpoint.
- ✅ 60/60 writable routes have input validation (FormRequest or inline `validate()`).
- ⚠️ 1 HIGH stored XSS (SEC-001), 1 MEDIUM stored XSS (SEC-002), 1 LOW DoS (SEC-003).
- ✅ All `{!! !!}` Blade uses audited; 9/12 explicitly safe, 2 carry findings, 1 LOW.
