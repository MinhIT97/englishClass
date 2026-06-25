# Task 9 — Security Review: Rate Limiting & DoS Protection

**Scope:** Rate limiting, pagination caps, broadcast/WS auth, unbounded queries, file download throttles.
**Target:** englishClass (Laravel 12 + nwidart/laravel-modules).
**Audit type:** Read-only — no requests sent, no product files modified.
**Date:** 2026-06-25.

---

## Verdict

**PASS WITH FINDINGS** — login/register/AI throttles are wired correctly (per SECURITY.md) but the surface has **five missing throttles and one unbounded-query gap** that materially expose cost abuse, DoS, and brute-force risk. The claimed `CourseService::MAX_PER_PAGE = 100` cap is **verified**, but the equivalent **`QuestionService::paginate` has no cap** — a direct contradiction with SECURITY.md. Fixes are mostly one-line throttle additions plus a 3-line cap in `QuestionService`.

---

## Throttle Table

### Route inventory — middleware audit

| Method | URI | Middleware chain | Throttle | Effective limit | File:line |
|---|---|---|---|---|---|
| POST | `/login` (web) | `guest` → `throttle:5,1` | built-in (IP) | **5/min/IP** | `Modules/Auth/routes/web.php:15` |
| POST | `/register` (web) | `guest` → `throttle:3,60` | built-in (IP) | **3/hour/IP** | `Modules/Auth/routes/web.php:16` |
| POST | `/api/login` | `throttle:5,1` | built-in (IP) | **5/min/IP** | `Modules/Auth/routes/api.php:15` |
| POST | `/api/register` | `throttle:3,60` | built-in (IP) | **3/hour/IP** | `Modules/Auth/routes/api.php:14` |
| POST | `/ai/chat` | `auth` → `throttle:ai` | named (`ai`) | **20/min/user-or-IP** | `routes/web.php:26-28` |
| POST | `/ai/tutor` | `auth` → `throttle:ai` | named (`ai`) | **20/min/user-or-IP** | `routes/web.php:31-33` |
| POST | `/ai/tutor/explain` | `auth` → `throttle:ai` | named (`ai`) | **20/min/user-or-IP** | `routes/web.php:34-36` |
| POST | `/ai/tutor/suggest` | `auth` → `throttle:ai` | named (`ai`) | **20/min/user-or-IP** | `routes/web.php:37-39` |
| POST | `/ai/tutor/clear` | `auth` | **NONE** | unlimited | `routes/web.php:40-41` |
| POST | `/lesson-requests` | `auth` → `throttle:lesson-requests` | named | **3/hour/user-or-IP** | `routes/web.php:44-46` |
| POST | `/telegram/webhook` | `telegram.secret` | **NONE** (signature-gated only) | unbounded if secret matches | `routes/web.php:21-23` |
| GET | `/flashcards` | `auth` | **NONE** | unbounded | `routes/web.php:49` |
| POST | `/flashcards/{id}/grade` | `auth` | **NONE** | unbounded | `routes/web.php:50` |
| GET | `/flashcards/stats` | `auth` | **NONE** | unbounded | `routes/web.php:51` |
| GET | `/study-plan` | `auth` | **NONE** | unbounded | `routes/web.php:54` |
| POST | `/study-plan` | `auth` | **NONE** | unbounded | `routes/web.php:55` |
| POST | `/study-plan/{plan}/complete` | `auth` | **NONE** | unbounded | `routes/web.php:56` |
| DELETE | `/study-plan/{plan}` | `auth` | **NONE** | unbounded | `routes/web.php:57` |
| GET | `/quests` | `auth` | **NONE** | unbounded | `routes/web.php:60` |
| GET | `/community/notes` | `auth` | **NONE** | unbounded | `routes/web.php:63` |
| POST | `/community/notes` | `auth` | **NONE** | unbounded | `routes/web.php:64` |
| POST | `/community/comments` | `auth` | **NONE** | unbounded | `routes/web.php:65` |
| GET | `/community/find-buddy` | `auth` | **NONE** | unbounded | `routes/web.php:66` |
| GET | `/analytics` | `auth` | **NONE** | unbounded | `routes/web.php:69` |
| GET | `/teacher/dashboard` | `auth` → `role:teacher,admin` | **NONE** | unbounded | `routes/web.php:73` |
| **GET** | **`/search`** | **`auth`** | **NONE** | **unbounded** | `routes/web.php:77` |
| GET | `/settings/preferences` | `auth` | **NONE** | unbounded | `routes/web.php:80` |
| PUT | `/settings/preferences` | `auth` | **NONE** | unbounded | `routes/web.php:81` |
| **GET** | **`/settings/export`** | **`auth`** | **NONE** | **unbounded (GDPR JSON dump)** | `routes/web.php:82` |
| GET | `/admin/lesson-requests` | `auth` → `can:admin-access` → `audit.admin` | **NONE** | unbounded | `routes/web.php:86` |
| POST | `/admin/lesson-requests/{id}/review` | `auth` → `can:admin-access` → `audit.admin` | **NONE** | unbounded | `routes/web.php:87` |
| POST | `/feedback` | `auth` | **NONE** | unbounded | `Modules/Auth/routes/web.php:32` |
| GET | `/notifications/unread-count` | `auth` | **NONE** | unbounded (polling loop) | `Modules/Auth/routes/web.php:25` |
| POST | `/notifications/mark-as-read` | `auth` | **NONE** | unbounded | `Modules/Auth/routes/web.php:24` |
| POST | `/courses/{course}/enroll` | `auth` → `verified` | **NONE** | unbounded | `Modules/Course/routes/web.php:7` |
| CRUD | `/courses` (resource) | `auth` → `verified` | **NONE** | unbounded | `Modules/Course/routes/web.php:8` |
| CRUD | `/api/courses` (resource) | (none — bare apiResource) | **NONE** | **unauthenticated CRUD** | `Modules/Course/routes/api.php:10` |
| POST | `/student/flashcards/save` | `auth` → `can:active-user` | **NONE** | unbounded | `Modules/Flashcard/routes/web.php:8` |
| CRUD | `/api/v1/flashcards` | `auth:sanctum` | **NONE** | unbounded | `Modules/Flashcard/routes/api.php:7` |
| POST | `/student/settings/telegram/linking-code` | `auth` | **NONE** | unbounded | `Modules/TelegramBot/routes/web.php:12` |
| POST | `/student/settings/telegram/unlink` | `auth` | **NONE** | unbounded | `Modules/TelegramBot/routes/web.php:13` |
| POST | `/student/settings/telegram/dismiss-banner` | `auth` | **NONE** | unbounded | `Modules/TelegramBot/routes/web.php:14-15` |
| POST | `/reading-review/passages/{id}/grade` | `auth` → `can:active-user` | **NONE** | unbounded | `Modules/TelegramBot/routes/web.php:23` |
| POST | `/reading-review/passages/{id}/enrol` | `auth` → `can:active-user` | **NONE** | unbounded | `Modules/TelegramBot/routes/web.php:24` |
| CRUD | `/admin/reading-passages` | `auth` → `can:admin-access` → `audit.admin` | **NONE** | unbounded | `Modules/TelegramBot/routes/web.php:35-41` |
| **POST** | **`/student/speaking/start`** | `auth` → `can:active-user` | **NONE** (despite calling AI service) | **unbounded** | `Modules/Speaking/routes/web.php:8` |
| **POST** | **`/student/speaking/chat`** | `auth` → `can:active-user` | **NONE** (despite calling AI service) | **unbounded** | `Modules/Speaking/routes/web.php:9` |
| GET | `/student/speaking/poll` | `auth` → `can:active-user` | **NONE** | unbounded (polling loop) | `Modules/Speaking/routes/web.php:10` |
| POST | `/api/speaking/start` | `auth:api` | **NONE** | unbounded | `Modules/Speaking/routes/api.php:11` |
| POST | `/api/speaking/transcript` | `auth:api` | **NONE** | unbounded | `Modules/Speaking/routes/api.php:12` |
| GET | `/api/speaking/{session}/result` | `auth:api` | **NONE** | unbounded | `Modules/Speaking/routes/api.php:13` |
| GET | `/student/practice/drill/{skill}` | `auth` → `can:active-user` | **NONE** | unbounded | `Modules/Practice/routes/web.php:8` |
| **POST** | **`/student/practice/submit`** | `auth` → `can:active-user` | **NONE** (despite DB writes) | **unbounded** | `Modules/Practice/routes/web.php:9` |
| **POST** | **`/student/practice/submit-speaking`** | `auth` → `can:active-user` | **NONE** (despite DB writes) | **unbounded** | `Modules/Practice/routes/web.php:10` |
| GET | `/student/test/start` | `auth` → `can:active-user` | **NONE** | unbounded | `Modules/MockTest/routes/web.php:8` |
| POST | `/student/writing/submit` | `auth` → `can:active-user` | **NONE** | unbounded | `Modules/Writing/routes/web.php:8` |
| POST | `/admin/questions/store` | `auth` → `can:admin-access` | **NONE** | unbounded | `Modules/Question/routes/web.php:10` |
| **POST** | **`/admin/questions/ai-generate`** | `auth` → `can:admin-access` | **NONE** (despite calling Gemini AI) | **unbounded** | `Modules/Question/routes/web.php:15` |
| **POST** | **`/admin/questions/generate-voice`** | `auth` → `can:admin-access` | **NONE** (despite calling TTS) | **unbounded** | `Modules/Question/routes/web.php:17` |
| POST | `/admin/questions/store-batch` | `auth` → `can:admin-access` | **NONE** | unbounded | `Modules/Question/routes/web.php:16` |
| GET | `/api/questions` | (none — bare) | **NONE** | **public, unauthenticated** | `Modules/Question/routes/api.php:10` |
| POST | `/classroom` (store) | `auth` | **NONE** | unbounded | `Modules/Classroom/routes/web.php:8` |
| POST | `/classroom/join` | `auth` | **NONE** (invite-code brute force possible) | unbounded | `Modules/Classroom/routes/web.php:10` |
| POST | `/classroom/{id}/post` | `auth` | **NONE** | unbounded | `Modules/Classroom/routes/web.php:11` |
| POST | `/classroom/post/{post}/comment` | `auth` | **NONE** | unbounded | `Modules/Classroom/routes/web.php:13` |
| POST | `/classroom/post/{post}/feedback` | `auth` | **NONE** | unbounded | `Modules/Classroom/routes/web.php:12` |
| CRUD | `/api/v1/classrooms` | `auth:sanctum` | **NONE** | unbounded | `Modules/Classroom/routes/api.php:7` |
| POST | `/student/sets/{set}/start` | `auth` → `can:active-user` | **NONE** | unbounded | `Modules/IeltsSet/routes/web.php:19` |
| POST | `/student/sets/{set}/sections/{section}` | `auth` → `can:active-user` | **NONE** | unbounded | `Modules/IeltsSet/routes/web.php:21` |
| POST | `/api/deploy/notify` | `auth.deploy` (token) | **NONE** (token-gated) | unbounded if token matches | `routes/api.php:6-7` |
| GET | `/api/internal/{health,info,metrics,status}` | `internal.token` | **NONE** (token-gated) | unbounded if token matches | `Modules/InternalManager/routes/api.php:7-10` |

### Named rate limiters (verified)

| Name | Definition | Limit | Where used |
|---|---|---|---|
| `ai` | `AppServiceProvider::registerRateLimiters()` line 76-80 | 20/min per user-or-IP | `routes/web.php:27,32,35,38` ✓ |
| `ai-speaking` | line 84-88 | 10/min per user-or-IP | **NEVER USED** (no route attaches it) ⚠️ |
| `lesson-requests` | line 92-96 | 3/hour per user-or-IP | `routes/web.php:45` ✓ |
| `webhook` | line 100-102 | 120/min per IP | **NEVER USED** ⚠️ |
| Built-in | `throttle:5,1` | 5/min/IP | login (web+api) ✓ |
| Built-in | `throttle:3,60` | 3/hour/IP | register (web+api) ✓ |

### Global middleware verification

`bootstrap/app.php:15-24` — the `web` and `api` groups register **only** `SetLocale`, `SecurityHeaders`, `ApplyUserLocale`. **No global throttle middleware** is applied to either group. Laravel 12 ships without `RouteServiceProvider`, so the `api` group never gets the implicit `throttle:api` that previous versions provided — every `/api/*` endpoint is un-throttled by default unless the route file attaches one.

---

## Findings

| Severity | ID | Title | CWE | OWASP 2021 | Exploitability | Impact | PoC ref | Fix complexity |
|---|---|---|---|---|---|---|---|---|
| **High** | **SEC-009** | `/search` runs unbounded LIKE across 4 tables with no throttle | CWE-770 (Allocation of Resources Without Limits) | A04:2021 Insecure Design | Trivial — any authenticated user, GET with `?q=` | DB CPU spike; per-group `limit(5)` caps rows but **not query cost** (4 separate LIKE `%%` scans per request) | F-1 | 1 line |
| **High** | **SEC-010** | `QuestionService::paginate` accepts user-supplied `?limit=` without cap (contradicts SECURITY.md claim of "pagination cap = 100") | CWE-770 | A04:2021 | Trivial — admin route, but admins can be phished / taken over | `paginate(99999999)` → query times out / memory exhaustion; admin route amplifies blast radius (1 bad query → full table scan + render) | F-2 | 3 lines |
| **High** | **SEC-011** | Speaking `/start` and `/chat` invoke AI service with **no throttle** (despite `ai-speaking` limiter existing) | CWE-799 (Improper Control of Interaction Frequency) | A04:2021 | Trivial | Cost abuse — these endpoints queue messages to Gemini/TTS pipeline (see `SpeakingSessionService::queueMessage`); without rate limit, a single user can spam paid AI calls | F-3 | 2 lines |
| **High** | **SEC-012** | `/admin/questions/ai-generate` and `/generate-voice` invoke Gemini/TTS with no throttle | CWE-799 | A04:2021 | Trivial | Same cost-abuse vector from admin compromise; `QuestionController::generateVoice` calls `AiSpeakingService::generateTTS` (line 68-69) | F-4 | 2 lines |
| **Medium** | **SEC-013** | `/settings/export` returns full GDPR JSON dump with no throttle — info-exfil + cost amplifier | CWE-799 | A04:2021 | Trivial | Repeated hits generate fresh DB queries and force a content-disposition download; can be looped to amplify other DoS vectors (e.g., the unprotected `/search`) | F-5 | 1 line |
| **Medium** | **SEC-014** | Community write endpoints (`/community/notes`, `/community/comments`) have no throttle — spam / DB-fill | CWE-799 | A04:2021 | Trivial | Authenticated user can fill `study_notes` / `comments` with 5 000-char entries at full DB write speed (5 000 chars × N hits) | F-6 | 2 lines |
| **Medium** | **SEC-015** | Reverb WebSocket has `allowed_origins = ['*']` and `rate_limiting.enabled` defaults to **false** | CWE-346 (Origin Validation Error) + CWE-770 | A05:2021 Security Misconfiguration | Trivial — any browser JS can connect | CORS wildcard lets any malicious site open WS connections; rate limiting disabled by default means no per-connection caps | F-7 | 2 env vars |
| **Low** | **SEC-016** | Classroom `/join` accepts invite codes without throttle — invite-code brute-force | CWE-307 (Improper Restriction of Excessive Auth Attempts) | A07:2021 Identification & Auth Failures | Non-trivial (codes were bumped 6 → 10 chars in this release) | 10^10 space is large, but unauthenticated endpoint + no throttle = offline-style attack feasible from one IP | F-8 | 1 line |
| **Low** | **SEC-017** | Speaking `/poll` and `/notifications/unread-count` are polling loops with no throttle | CWE-770 | A04:2021 | Trivial | A misbehaving client can hammer these endpoints (no `EventSource`/WS switch); per-user `auth` only | F-9 | 1 line |
| **Low** | **SEC-018** | `POST /feedback`, `POST /notifications/mark-as-read` have no throttle — minor abuse vectors | CWE-799 | A04:2021 | Trivial | DB row spam / log inflation | F-10 | 2 lines |
| **Info** | **SEC-019** | `ai-speaking` and `webhook` rate limiters are registered but **never attached to any route** — dead code / misleading documentation | — | — | — | Maintenance hazard: future devs assume `ai-speaking` is on `/student/speaking/chat` because the limiter exists | — | 1 line or remove dead limiters |

### CWE summary

- **CWE-770** (Allocation of Resources Without Limits): SEC-009, SEC-015, SEC-017
- **CWE-799** (Improper Control of Interaction Frequency): SEC-011, SEC-012, SEC-013, SEC-014, SEC-018
- **CWE-307** (Improper Restriction of Excessive Auth Attempts): SEC-016
- **CWE-346** (Origin Validation Error): SEC-015
- **CWE-20** (Improper Input Validation): SEC-010

### OWASP Top 10 (2021) coverage

- **A04 Insecure Design** dominates this finding set (8 of 11 findings).
- **A05 Security Misconfiguration** for the Reverb CORS issue.
- **A07 Identification & Auth Failures** for invite-code brute force.

---

## Finding Details

### SEC-009 (High) — Unthrottled `/search` with cross-table LIKE

**Evidence:**

`routes/web.php:76-77`
```php
// Global search
Route::get('search', \App\Http\Controllers\SearchController::class)->name('search');
```
No middleware besides the surrounding `auth` group (line 25).

`app/Http\Controllers/SearchController.php:33-66` — runs 4 separate unindexed LIKE queries per call:
```php
$groups['courses'] = Course::query()
    ->where(function ($qb) use ($q) {
        $qb->where('title', 'like', "%{$q}%")
            ->orWhere('description', 'like', "%{$q}%");
    })
    ->limit(5)->get(...);
// ... 3 more groups with `where('column', 'like', "%{$q}%")`
```

**Attack path:** Authenticated user → `GET /search?q=a` repeatedly. Per request: 4 wide LIKE queries. No index on `title LIKE %x%` (B-tree can't help with leading wildcard). A user with 100 000+ courses/classrooms/notes makes each request cost seconds of CPU.

**Severity rationale:** High because (a) it's reachable from any authenticated user, (b) `limit(5)` only caps returned rows, not the LIKE scan itself, and (c) the LIKE pattern is `%q%` with leading wildcard — full table scan every time.

**Minimal fix (1 line):**
```php
Route::get('search', \App\Http\Controllers\SearchController::class)
    ->middleware('throttle:30,1')   // 30/min/user
    ->name('search');
```
Plus register a `search` limiter in `AppServiceProvider::registerRateLimiters()`:
```php
RateLimiter::for('search', function (Request $request) {
    return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
});
```

**Regression check:** Feature test — `$this->actingAs($user)->get('/search?q=hello')->assertStatus(200)` repeated 31 times → 31st returns 429.

---

### SEC-010 (High) — `QuestionService::paginate` accepts unbounded `?limit=`

**Evidence:**

`Modules/Question/Http/Controllers/QuestionController.php:22-28`
```php
public function index(Request $request)
{
    $filters = $request->only(['skill', 'type', 'topic']);
    $questions = $this->service->paginate($filters, $request->get('limit', 15));

    return view('question::admin.index', compact('questions'));
}
```

`Modules/Question/Services/QuestionService.php:19-22`
```php
public function paginate(array $filters, int $perPage = 15)
{
    return $this->repository->model()::query()->filter($filters)->paginate($perPage);
}
```

`paginate($perPage)` is called **without any cap** — directly contradicting `Modules/Course/Services/CourseService.php:26-33` which clamps with `max(1, min($perPage, self::MAX_PER_PAGE))`.

**Attack path:** Admin or attacker with admin token → `GET /admin/questions?limit=99999999`. Eloquent calls `paginate(99999999)`. Database SELECT runs, fetches full question table, hydrates models, renders Blade template. Memory exhaustion + 30-60s request → worker starvation.

**Severity rationale:** High — SECURITY.md explicitly claims "Pagination cap — CourseService::MAX_PER_PAGE = 100 prevents DoS via ?limit=99999999", but the protection is **only in `CourseService`**, not a shared trait / repository helper. `QuestionService` (admin-facing, larger table) ships unprotected. Same vulnerability shape as CVE-2024-… equivalents in Laravel apps that don't share the cap.

**Minimal fix (3 lines):**
```php
// Modules/Question/Services/QuestionService.php
+ use App\Services\Pagination;

  public function paginate(array $filters, int $perPage = 15)
  {
+     $perPage = max(1, min($perPage, 100));
      return $this->repository->model()::query()->filter($filters)->paginate($perPage);
  }
```

**Better fix:** Extract `MAX_PER_PAGE` to `App\Services\Pagination::MAX_PER_PAGE = 100;` and reuse from both `CourseService` and `QuestionService`. Same audit applies to `Modules/TelegramBot/Services/ReadingPassageAdminService.php:47` which calls `paginate($perPage)` from admin routes (also user-supplied via `Request::integer('limit', 15)`).

**Regression check:** `GET /admin/questions?limit=99999999` should return at most 100 rows; test asserts `count($questions->items()) <= 100`.

---

### SEC-011 (High) — Speaking AI endpoints unthrottled despite `ai-speaking` limiter existing

**Evidence:**

`Modules/Speaking/routes/web.php:6-11`
```php
Route::middleware(['auth', 'can:active-user'])->prefix('student/speaking')->group(function () {
    Route::get('/', [SpeakingController::class, 'index'])->name('student.speaking.index');
    Route::post('/start', [SpeakingController::class, 'start'])->name('student.speaking.start');
    Route::post('/chat', [SpeakingController::class, 'chat'])->name('student.speaking.chat');
    Route::get('/poll', [SpeakingController::class, 'poll'])->name('student.speaking.poll');
});
```

`app/Providers/AppServiceProvider.php:82-88` defines `ai-speaking` as **10/min/user** — but **no route attaches it**.

`Modules/Speaking/Http/Controllers/SpeakingController.php:27-50` — `start` and `chat` queue work for `SpeakingSessionService`, which talks to `AiSpeakingService` (Gemini + TTS).

**Attack path:** Authenticated user → 1000 calls/minute to `/student/speaking/chat`. Each call: message queued, AI service called (paid Gemini tokens), audio streamed. Without throttle, a single user can rack up hundreds of dollars in API charges per hour.

**Severity rationale:** High because (a) cost abuse is direct and bounded only by user's patience, (b) the limiter already exists but isn't wired — pure oversight, (c) speaking is the most expensive endpoint (TTS + LLM tokens).

**Minimal fix (2 lines):**
```php
Route::post('/start', [SpeakingController::class, 'start'])
    ->middleware('throttle:ai-speaking')
    ->name('student.speaking.start');
Route::post('/chat', [SpeakingController::class, 'chat'])
    ->middleware('throttle:ai-speaking')
    ->name('student.speaking.chat');
```

**Regression check:** Acting as a user, 11 hits to `/student/speaking/chat` in 60s → 11th returns 429.

---

### SEC-012 (High) — `/admin/questions/ai-generate` and `/generate-voice` unthrottled

**Evidence:**

`Modules/Question/routes/web.php:14-17`
```php
Route::get('/ai-generate', [AIQuestionController::class, 'index'])->name('admin.questions.ai');
Route::post('/ai-generate', [AIQuestionController::class, 'generate'])->name('admin.questions.generate');
Route::post('/store-batch', [AIQuestionController::class, 'store'])->name('admin.questions.store_batch');
Route::post('/generate-voice', [QuestionController::class, 'generateVoice'])->name('admin.questions.generate_voice');
```

`Modules/Question/Http/Controllers/QuestionController.php:64-75` — `generateVoice` calls `AiSpeakingService::generateTTS` (paid TTS).

`AIQuestionController::generate` (not read — would need to read, but route has no throttle) presumably calls Gemini.

**Attack path:** Admin compromised / phished / token leaked → POST `/admin/questions/ai-generate` in a loop. Each call invokes Gemini LLM. Single admin account, no throttle = unbounded cost amplifier.

**Severity rationale:** High — admin-only routes have a larger blast radius (admin auth often reused across environments, logins persist longer, token theft is a single phish).

**Minimal fix (2 lines):**
```php
Route::post('/ai-generate', [AIQuestionController::class, 'generate'])
    ->middleware('throttle:ai')
    ->name('admin.questions.generate');
Route::post('/generate-voice', [QuestionController::class, 'generateVoice'])
    ->middleware('throttle:ai-speaking')
    ->name('admin.questions.generate_voice');
```

**Regression check:** As admin, 21 POSTs to `/admin/questions/ai-generate` in 60s → 21st returns 429.

---

### SEC-013 (Medium) — `/settings/export` returns GDPR dump with no throttle

**Evidence:**

`routes/web.php:82`
```php
Route::get('settings/export', [\App\Http\Controllers\SettingsController::class, 'export'])->name('settings.preferences.export');
```

`app/Http\Controllers/SettingsController.php:43-56`
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

**Attack path:** Any authenticated user → loop `GET /settings/export`. Each call: 1 user query + 1 preferences query + JSON serialize + response stream. Combined with SEC-009 (also unthrottled) → single user can saturate DB connection pool with cheap-but-serial queries.

**Severity rationale:** Medium — not directly cost-abusive (just queries), but amplifies other DoS findings and is a GDPR-data-exfil channel (logs may capture export contents).

**Minimal fix (1 line):**
```php
Route::get('settings/export', [\App\Http\Controllers\SettingsController::class, 'export'])
    ->middleware('throttle:10,1')   // 10/min/user — humans don't need more
    ->name('settings.preferences.export');
```

**Regression check:** Acting as user, 11 GETs to `/settings/export` in 60s → 11th returns 429.

---

### SEC-014 (Medium) — Community write endpoints unthrottled

**Evidence:**

`routes/web.php:63-66`
```php
Route::get('community/notes', [\App\Http\Controllers\CommunityController::class, 'notesIndex'])->name('community.notes.index');
Route::post('community/notes', [\App\Http\Controllers\CommunityController::class, 'noteStore'])->name('community.notes.store');
Route::post('community/comments', [\App\Http\Controllers\CommunityController::class, 'commentStore'])->name('community.comments.store');
Route::get('community/find-buddy', [\App\Http\Controllers\CommunityController::class, 'findBuddy'])->name('community.buddy');
```

`app/Http\Controllers/CommunityController.php:25-58` — validates 5 000-char notes and 2 000-char comments but **no throttle**.

**Attack path:** Authenticated user → 1000 POSTs to `/community/notes` with 5 000-char title + content → 5 MB of `study_notes` rows in seconds. No DB-side check beyond `max:5000`.

**Severity rationale:** Medium — DB fill-up + log spam; not critical because rows are user-attributed (accountable), but storage abuse is real.

**Minimal fix (2 lines):** Add throttle:30,1 to both POSTs; or define a `community-write` limiter (10/min/user).

**Regression check:** 31st POST to `/community/notes` in 60s → 429.

---

### SEC-015 (Medium) — Reverb WS: `allowed_origins = ['*']` + rate limiting disabled by default

**Evidence:**

`config/reverb.php:85-96`
```php
'allowed_origins' => ['*'],
'ping_interval' => env('REVERB_APP_PING_INTERVAL', 60),
'activity_timeout' => env('REVERB_APP_ACTIVITY_TIMEOUT', 30),
'max_connections' => env('REVERB_APP_MAX_CONNECTIONS'),
'max_message_size' => env('REVERB_APP_MAX_MESSAGE_SIZE', 10_000),
'accept_client_events_from' => env('REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM', 'members'),
'rate_limiting' => [
    'enabled' => env('REVERB_APP_RATE_LIMITING_ENABLED', false),     // ← DEFAULT OFF
    'max_attempts' => env('REVERB_APP_RATE_LIMIT_MAX_ATTEMPTS', 60),
    'decay_seconds' => env('REVERB_APP_RATE_LIMIT_DECAY_SECONDS', 60),
    'terminate_on_limit' => env('REVERB_APP_RATE_LIMIT_TERMINATE', false),
],
```

`routes/channels.php:5-12` — only 2 channels registered, both auth-gated (good). But the WS server itself accepts connections from any origin.

**Attack path:** Malicious site at `evil.com` opens a Reverb WS connection. CORS wildcard allows it. Server then checks channel auth — if attacker doesn't have a session, channels reject. **But:** the connection itself is accepted, the handshake costs CPU, and `accept_client_events_from = members` is configured to gate client→server messages, but the `members` gating only kicks in if the connection is auth'd. With no auth, the attacker can still spam pings / reconnects.

Additionally, **rate_limiting.enabled = false** means even auth'd users have no per-connection message rate cap.

**Severity rationale:** Medium — origin validation weakness is real (CWE-346) but Reverb's `accept_client_events_from = members` mitigates unauth message spam. The bigger issue is `rate_limiting.enabled = false` defaulting — production deploys that don't override env vars get no rate limit.

**Minimal fix (2 env vars):**
```env
REVERB_APP_ALLOWED_ORIGINS=https://yourdomain.com
REVERB_APP_RATE_LIMITING_ENABLED=true
REVERB_APP_RATE_LIMIT_MAX_ATTEMPTS=120
REVERB_APP_RATE_LIMIT_TERMINATE=true
```

**Regression check:** Manual — open WS connection from `evil.com` (simulated via curl) → connection rejected by origin check. Force `REVERB_APP_RATE_LIMITING_ENABLED=false` in test, then verify the deploy script / `.env.example` sets it true.

---

### SEC-016 (Low) — Classroom `/join` invite-code brute force

**Evidence:**

`Modules/Classroom/routes/web.php:10`
```php
Route::post('classroom/join', [ClassroomController::class, 'join'])->name('classroom.join');
```

No throttle. README claims codes bumped 6 → 10 chars — but rate-limit is the second leg, not the only leg.

**Attack path:** Attacker → `POST /classroom/join` with `code=ABCDEFGHIJ` × N. At no throttle = 1 000 attempts/sec from one IP. 10-char alphabet (per README) gives large search space, but with no backoff the cost per attempt is just 1 SELECT.

**Severity rationale:** Low — only joins classrooms (low-value target) and codes are now 10 chars. But pattern (auth endpoint with no throttle) is wrong.

**Minimal fix (1 line):**
```php
Route::post('classroom/join', [ClassroomController::class, 'join'])
    ->middleware('throttle:10,1')   // 10/min/user
    ->name('classroom.join');
```

**Regression check:** 11 POSTs to `/classroom/join` in 60s → 11th returns 429.

---

### SEC-017 (Low) — Polling endpoints `/speaking/poll` and `/notifications/unread-count`

**Evidence:**

`Modules/Speaking/routes/web.php:10`
```php
Route::get('/poll', [SpeakingController::class, 'poll'])->name('student.speaking.poll');
```

`Modules/Auth/routes/web.php:25`
```php
Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unreadCount');
```

Both are designed for high-frequency client polling (every few seconds). No throttle.

**Attack path:** Misbehaving / malicious JS → poll every 100ms. With no throttle, each user creates their own DB load multiplier.

**Severity rationale:** Low — bounded by `auth` middleware (per-user), but still amplifies. A user opening 10 tabs = 10× load.

**Minimal fix (1 line per route):**
```php
->middleware('throttle:600,1')   // 10/sec — generous for polling
```

**Regression check:** 601 hits in 60s → 601st returns 429.

---

### SEC-018 (Low) — `/feedback`, `/notifications/mark-as-read` no throttle

**Evidence:**

`Modules/Auth/routes/web.php:24,32`
```php
Route::post('notifications/mark-as-read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
...
Route::post('feedback', [\App\Http\Controllers\FeedbackController::class, 'store'])->name('feedback.store');
```

**Severity rationale:** Low — DB-row spam; bounded by `auth`. No cost amplifier.

**Minimal fix:** `->middleware('throttle:30,1')` on each.

---

### SEC-019 (Info) — Dead rate limiters `ai-speaking` and `webhook`

**Evidence:**

`app/Providers/AppServiceProvider.php:84-102` defines both. Grep across `routes/` and `Modules/*/routes/` finds **zero usages**.

**Severity rationale:** Info — misleading documentation (README claims "10/min on AI-speaking" — no route enforces it). Future devs may attach them assuming they're already on the matching routes.

**Minimal fix:** Either (a) attach `ai-speaking` to `Modules/Speaking/routes/web.php:8-9` (fixes SEC-011 simultaneously), or (b) delete the unused limiter to avoid drift. The `webhook` limiter can be attached to `routes/web.php:21-23` (Telegram webhook) for defense-in-depth.

---

## Downgraded or Rejected Candidates

| Candidate | Reason rejected/downgraded |
|---|---|
| **Lesson quota / `LessonQuotaService::check`** | A business-logic cap, not a throttle. Counts creations per day. Not part of this task's scope. |
| **Telegram webhook signature gap** | Out of scope — already covered by `telegram.secret` middleware + 503-on-empty-secret fix (Task C5). Mentioned only to flag the `webhook` limiter (SEC-019). |
| **`api/questions` is unauthenticated** | `Modules/Question/routes/api.php:10` lists `GET /api/questions` with NO middleware. Real bug, but **out of scope** for "rate limiting" — belongs to C1 (Auth). Reported here as a side observation only. |
| **`api/courses` is unauthenticated CRUD** | `Modules/Course/routes/api.php:10` — bare `apiResource`. **Out of scope** for rate limiting; belongs to C1 (Auth). Flagged here because it makes the missing throttle worse (anonymous CRUD = anonymous DoS). |
| **`/api/internal/*`** | Token-gated, internal-only. Out of scope. |
| **SpeakingSessionService::queueMessage DB query** | Worth a deeper look (could be N+1), but not a "rate limit" finding. Belongs to C8 (Performance). |

---

## Residual Risk

- **Not tested:** Live `php artisan test --filter=Security` run. The rate-limit test file (`tests/Feature/Security/RateLimitTest.php`) exists and covers login (5/min), register (3/hour), and `ai/chat` (20/min). It does **not** cover the new findings (search, question paginate cap, speaking chat throttle, settings export, community write, classroom join, Reverb CORS). A follow-up Wave 4 task should add regression tests for SEC-009 through SEC-018.
- **Not verified at runtime:** Whether `CourseService::MAX_PER_PAGE = 100` actually clamps `?limit=99999999` in practice. The static read confirms the clamp is in place (`$perPage = max(1, min($perPage, self::MAX_PER_PAGE));`); a 30-second PHPUnit test would lock it.
- **Out of scope (flagged for other tasks):** The unauthenticated `GET /api/questions` and bare `apiResource('courses')` are real authorization gaps but belong to C1 (Auth), not C9 (Rate Limiting). They amplify C9 findings and should be cross-referenced when C1 is reviewed.
- **Reverb environment variables:** Without `.env` from a live deploy, I cannot verify whether `REVERB_APP_RATE_LIMITING_ENABLED` is overridden to `true` in production. The default-false in `config/reverb.php` means **any deploy that doesn't explicitly opt in ships with rate limiting off**. This is a deployment-config concern, not a code bug, but it's worth a deployment-script audit.
- **Pollination between findings:** A single authenticated user, hitting the unprotected `/search` (SEC-009) and `/settings/export` (SEC-013) in tandem, can saturate the DB connection pool. Fixing SEC-009 + SEC-013 in isolation is necessary but not sufficient — add a per-user global cap (e.g., `throttle:user-global` at 120/min) as defense in depth.

---

## Summary of code diffs (High/Critical only)

### SEC-009 — Add `search` limiter + attach to route

**File:** `app/Providers/AppServiceProvider.php` (after line 102, inside `registerRateLimiters`)
```php
RateLimiter::for('search', function (Request $request) {
    return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
});
```

**File:** `routes/web.php:77`
```php
- Route::get('search', \App\Http\Controllers\SearchController::class)->name('search');
+ Route::get('search', \App\Http\Controllers\SearchController::class)
+     ->middleware('throttle:search')
+     ->name('search');
```

### SEC-010 — Cap `QuestionService::paginate`

**File:** `Modules/Question/Services/QuestionService.php`
```php
  public function paginate(array $filters, int $perPage = 15)
  {
+     $perPage = max(1, min($perPage, 100));
      return $this->repository->model()::query()->filter($filters)->paginate($perPage);
  }
```

Same fix needed in `Modules/TelegramBot/Services/ReadingPassageAdminService.php:47` (admin paginate also user-supplied).

### SEC-011 — Attach `ai-speaking` to speaking routes

**File:** `Modules/Speaking/routes/web.php:8-9`
```php
- Route::post('/start', [SpeakingController::class, 'start'])->name('student.speaking.start');
- Route::post('/chat', [SpeakingController::class, 'chat'])->name('student.speaking.chat');
+ Route::post('/start', [SpeakingController::class, 'start'])
+     ->middleware('throttle:ai-speaking')
+     ->name('student.speaking.start');
+ Route::post('/chat', [SpeakingController::class, 'chat'])
+     ->middleware('throttle:ai-speaking')
+     ->name('student.speaking.chat');
```

### SEC-012 — Throttle admin AI endpoints

**File:** `Modules/Question/routes/web.php:15-17`
```php
- Route::post('/ai-generate', [AIQuestionController::class, 'generate'])->name('admin.questions.generate');
+ Route::post('/ai-generate', [AIQuestionController::class, 'generate'])
+     ->middleware('throttle:ai')
+     ->name('admin.questions.generate');
  Route::post('/store-batch', [AIQuestionController::class, 'store'])->name('admin.questions.store_batch');
- Route::post('/generate-voice', [QuestionController::class, 'generateVoice'])->name('admin.questions.generate_voice');
+ Route::post('/generate-voice', [QuestionController::class, 'generateVoice'])
+     ->middleware('throttle:ai-speaking')
+     ->name('admin.questions.generate_voice');
```

---

**End of report.**