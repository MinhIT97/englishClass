# upgrade-roadmap - Work Plan

## TL;DR (For humans)
<!-- Filled LAST after Metis gap analysis fixes folded in -->

**What you'll get:** A 10-component upgrade to the englishClass Laravel project: Sanctum + Passport auth migration, Spatie roles (dual-source with existing role column), AI queue + streaming via Reverb, Langfuse observability, Horizon queue dashboard, Sentry error tracking, GitHub Actions CI/CD, Playwright E2E tests, K6 load tests, SM-2 flashcard algorithm, IELTS-aligned 4-criteria writing rubric, and full speaking mock test. Cache is limited to AI response memoization only (per your direction); general cache layer is explicitly deferred.

**Why this approach:** 10 components grouped into 4 execution waves. Wave 1 = foundation (auth, permissions) — must land first because everything else depends on role/permission model. Wave 2 = AI infrastructure (queue, streaming, observability) — biggest ROI for the AI core. Wave 3 = reliability (Sentry, CI/CD, E2E, load tests). Wave 4 = feature depth (SM-2, rubric, mock test).

**What it will NOT do:** No general cache layer (per your direction — only AI result memoization), no mobile app, no new i18n languages, no real-time collaboration, no payment/billing.

**Effort:** XL (50+ hours of worker time, ~10 days full-time)
**Risk:** Medium — Sanctum migration affects all auth tokens; AI streaming changes response model. Mitigated by feature flag + 30-day JWT fallback.
**Decisions I made for you (announced, not asked):**
- Sanctum + Passport BOTH (not just one) — Sanctum for API tokens, Passport for OAuth2 (future mobile)
- Langfuse over Lunary — open-source, self-host, no vendor lock
- Sentry.io (free tier) over self-hosted — avoid ops complexity at start
- GitHub Actions over other CI — already have `.github/` dir
- K6 over JMeter — scriptable, free, Grafana integration
- SM-2 algorithm (industry standard) for SRS
- **Dual-source roles**: `users.role` column remains (no rewrite of 18 callers); Spatie pivot synced via `RoleSynced` event. Spatie for fine-grained `can()` checks only
- **AI cache scope**: ONLY `Cache::remember` for Gemini response memoization (cost reason). General cache facade still banned
- **Veto any of these at the gate by telling me — all reversible**

Your next move: Approve. Run `/start-work upgrade-roadmap` to dispatch Sisyphus-Junior workers on the 4 waves.

---

> TL;DR (machine): XL / Medium / 10-component upgrade, 4 waves, 17 todos + 4 final wave = 21 items. Dual-source roles, AI-only cache. Read-only pre-flight complete (Metis: 6 Critical, 17 Major, 2 Minor gaps all folded in below).

## Scope

### Must have
- All 10 components implemented and tested
- Each component has own commit + acceptance criteria
- E2E tests for 3 critical user flows
- Load tests for 3 high-traffic scenarios
- CI/CD blocks merge on test/composer-audit/lint failure
- Feature flag `config('auth.sanctum_only')` for auth migration (JWT fallback 30 days)
- Langfuse self-hosted Docker compose
- Sentry SDK integrated, 1 test alert firing
- Spatie permission seeding + dual-source sync via `RoleSynced` event
- SM-2 algorithm in flashcard review flow
- Writing rubric 4-criteria scoring
- Speaking mock test with timer + audio + Gemini score

### Must NOT have
- **NO general cache layer** (only AI response memoization allowed)
- No mobile app implementation
- No new i18n languages
- No payment/billing
- No real-time collaboration features
- No breaking changes to existing user data
- **NO change to `auth:web` guard driver** in `config/auth.php` (web login uses session, unchanged)
- **NO** `composer require playwright/playwright` (Playwright is npm, installed via `npm install --save-dev @playwright/test`)

## Verification strategy
> Zero human intervention - all verification is agent-executed.
- **Test decision:** TDD where applicable (Sanctum, SM-2, rubric scoring). Tests-after for plumbing work (CI config, K6 scripts).
- **Evidence:**
  - Each component: feature test + integration test
  - E2E: 3 Playwright test files in `tests/E2E/`
  - Load test: K6 scripts in `tests/Load/`
  - CI: 1 successful pipeline run on a sample PR
- **CI gate:** composer audit returns 0 high CVEs, phpunit passes 100%, pint format clean
- **Pre-merge requirement:** Every todo passes `phpunit` + `php -l` + relevant feature test
- **PII policy (consolidated)**: Pseudonymous identifiers (`user_id`, `target_band`) are allowed in cache keys, trace metadata, and Sentry context. Direct identifiers (email, name, phone, IP) are stripped from logs, traces, and caches. Gemini prompts may include `target_band` but never `email`/`name`.

## Execution strategy

### Parallel execution waves

**Wave 1 — Auth & Permissions Foundation** (3 todos, sequential due to auth interdependencies)
- T1 → T2 → T3 (Sanctum install → Route migration → Passport)
- T4 (Spatie permission — can parallel with T1)

**Wave 2 — AI Infrastructure** (4 todos)
- T5 (AI queue + AI-only cache)
- T6 (Reverb streaming — depends on T5 for queue events)
- T7 (Langfuse — depends on T5 for trace data)
- T8 (Horizon — depends on T5 for queue drivers)

**Wave 3 — Reliability & Observability** (4 todos, parallel)
- T9 (Sentry), T10 (CI/CD), T11 (E2E), T12 (K6 load)

**Wave 4 — Features** (3 todos, parallel)
- T13 (SM-2), T14 (Writing rubric — depends on T7 only), T15 (Speaking mock — depends on T5, T7)

### Dependency matrix

| Todo | Depends on | Blocks | Can parallelize with |
|------|-----------|--------|---------------------|
| T1 Sanctum install | nothing | T2 | T4 |
| T2 Route migration to Sanctum | T1 | T3, T11 | T4 |
| T3 Passport add-on | T2 | nothing | T4 |
| T4 Spatie permission (dual-source) | nothing | T5 | T1, T2, T3 |
| T5 AI queue + AI-only cache | T4 | T6, T7, T8, T11, T15 | T9, T10 |
| T6 Reverb streaming | T5 | T11 | T7, T8, T9, T10 |
| T7 Langfuse | T5 | T14, T15 | T6, T8, T9, T10 |
| T8 Horizon | T5 | T11 | T6, T7, T9, T10 |
| T9 Sentry | nothing | T10 | T5, T6, T7, T8, T10 |
| T10 CI/CD | T9 (for status checks via API) | nothing | T5–T9, T11, T12 |
| T11 E2E tests | T1, T2, T5, T8 | T16 | T9, T10, T12, T13–T15 |
| T12 K6 load | T1, T4, T5 | T17 | T9, T10, T11, T13–T15 |
| T13 SM-2 | nothing | nothing | T11, T12, T14, T15 |
| T14 Writing rubric | T7 (Langfuse) | nothing | T11, T12, T13, T15 |
| T15 Speaking mock | T5 (queue), T7 (Langfuse) | nothing | T11, T12, T13, T14 |
| T16 E2E in CI | T10, T11 | F1–F4 | T17 |
| T17 K6 nightly in CI | T12 | F1–F4 | T16 |

## Todos

> Implementation + Test = ONE todo. 17 todos + 4 final wave = 21 total.

- [ ] 1. Install Laravel Sanctum + base config (web guard UNCHANGED)
  What to do / Must NOT do:
    - DO: `composer require laravel/sanctum`
    - DO: `php artisan install:api` (creates config/sanctum.php, personal_access_tokens migration)
    - DO: Add `HasApiTokens` trait to User model
    - DO: Add `auth:sanctum` middleware alias to bootstrap/app.php
    - DO: Add `'sanctum_only' => env('SANCTUM_ONLY', false)` to `config/auth.php` (the feature flag)
    - DO NOT: Migrate any existing route yet (that's T2)
    - DO NOT: Remove JWT-auth (kept for fallback)
    - DO NOT: Change `auth:web` guard driver in `config/auth.php` (must stay on session driver for Blade frontend)
  Parallelization: Wave 1 | Blocked by: nothing | Blocks: T2, T3, T11, T12
  References:
    - https://laravel.com/docs/12.x/sanctum (via context7)
    - app/Models/User.php (line 12 JWTSubject, line 24 class — KEEP existing JWT implements)
    - bootstrap/app.php
    - config/auth.php (add sanctum_only key)
    - config/jwt.php (KEEP — fallback still works)
  Acceptance criteria:
    - `composer show laravel/sanctum` returns version
    - `php artisan migrate` creates `personal_access_tokens` table
    - User model has HasApiTokens trait (ALONGSIDE existing JWTSubject — not replacing)
    - `config('auth.sanctum_only')` returns false by default
    - `php artisan route:list --middleware=auth:sanctum` returns 0 routes (T2 adds them)
  QA scenarios:
    - Happy: SanctumTest — create token, verify in personal_access_tokens table
    - Failure: removing HasApiTokens trait — sanctum guard fails (regression)
  Commit: Y | feat(auth): install Laravel Sanctum + HasApiTokens trait (JWT preserved)

- [ ] 2. Migrate API auth to Sanctum with JWT fallback
  What to do / Must NOT do:
    - DO: Modify `Modules/Auth/Services/AuthService::login()` to branch on `config('auth.sanctum_only')`:
      ```php
      if (config('auth.sanctum_only')) {
          $token = $user->createToken('api')->plainTextToken;
      } else {
          $token = JWTAuth::fromUser($user);  // existing JWT path
      }
      ```
    - DO: Wrap `/api/*` routes with `auth:sanctum` middleware group (replacing `auth:api` ONLY when `sanctum_only=true`)
    - DO: Keep `auth:api` (jwt) middleware as fallback for 30 days
    - DO: Add `JWT_REMOVAL_DATE` env var defaulting to `now()->addDays(30)->toDateString()`
    - DO: Create `php artisan auth:migrate-to-sanctum` command that posts in-app + Telegram notification to all users instructing re-login
    - DO NOT: Touch web `auth:web` guard (Blade login flow unchanged)
    - DO NOT: Touch Telegram webhook auth (`telegram.secret` middleware)
    - DO NOT: Remove JWT-auth code paths
  Parallelization: Wave 1 | Blocked by: T1 | Blocks: T3, T11, T12
  References:
    - Modules/Auth/Services/AuthService.php (line 79 `JWTAuth::fromUser($user)`)
    - Modules/Auth/Http/Controllers/AuthController.php (line 86 `Auth::login()` — web, unchanged)
    - Modules/Auth/routes/api.php (line 25 `auth:api` middleware — branch here)
    - config/auth.php (sanctum_only key from T1)
    - config/jwt.php (keep)
  Acceptance criteria:
    - POST /api/login with `SANCTUM_ONLY=false` returns JWT (regression)
    - POST /api/login with `SANCTUM_ONLY=true` returns Sanctum plain text token
    - All 19 existing security tests pass
    - `JWT_REMOVAL_DATE` env var exists in .env.example with comment
  QA scenarios:
    - Happy: SanctumAuthTest — login, token in personal_access_tokens, auth check
    - Failure: feature flag off, JWT still works (regression verified)
  Commit: Y | feat(auth): migrate API login to Sanctum with JWT 30-day fallback flag

- [ ] 3. Install Laravel Passport for OAuth2 (future mobile)
  What to do / Must NOT do:
    - DO: `composer require laravel/passport`
    - DO: `php artisan passport:install` (creates oauth_* tables, RSA keys)
    - DO: Add `HasClients` trait to User model (alongside HasApiTokens + JWTSubject)
    - DO: Add `'passport'` guard to `config/auth.php` (api guard drivers array)
    - DO: Register Passport routes in `app/Providers/AuthServiceProvider`
    - DO NOT: Use Passport for current web auth (Sanctum does that)
    - DO NOT: Break Sanctum config
    - DO NOT: Auto-deploy Passport keys to git (use storage/oauth-*.key, gitignored)
  Parallelization: Wave 1 | Blocked by: T2 | Blocks: nothing
  References:
    - https://laravel.com/docs/12.x/passport (via context7)
    - config/auth.php (add passport guard)
    - app/Models/User.php (add HasClients trait)
  Acceptance criteria:
    - `php artisan passport:keys` succeeds
    - `/oauth/authorize` route registered
    - User model has HasClients trait
    - Personal access token grant works (curl test)
  QA scenarios:
    - Happy: PassportTest — create client, request token
    - Failure: keys directory missing — fail loudly with helpful error
  Commit: Y | feat(auth): install Laravel Passport for OAuth2 (HasClients trait)

- [ ] 4. Install spatie/laravel-permission with DUAL-SOURCE sync
  What to do / Must NOT do:
    - DO: `composer require spatie/laravel-permission`
    - DO: `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
    - DO: Migrate (creates roles, permissions, model_has_roles, role_has_permissions, model_has_permissions tables)
    - DO: Create `database/seeders/RolesAndPermissionsSeeder.php`:
      - 3 roles: admin, teacher, student
      - Permissions: access-ai-tutor, manage-classroom, view-analytics, manage-users
      - Attach permissions to roles per the existing UserRole enum semantics
    - DO: Add `HasRoles` trait to User model
    - DO: **CRITICAL** — `AuthService::register` (line 37 after `$user = $this->userRepository->create($data);`) add `$user->assignRole($data['role'])`. This syncs new users to Spatie pivot
    - DO: Create `app/Events/RoleSynced.php` event fired from `AuthService::register` AND `AdminUserController::approve()` whenever `users.role` changes
    - DO: Create `app/Listeners/SyncSpatieRole.php` listener that calls `$user->syncRoles([$newRole])`
    - DO: Place new Policies at `Modules/*/Policies/` (HMVC pattern), NOT `app/Policies/`:
      - `Modules/Course/Policies/CoursePolicy.php`
      - `Modules/Practice/Policies/AssignmentPolicy.php`
    - DO NOT: Refactor existing 18 callers of `$user->role` (string fast-path remains)
    - DO NOT: Deprecate `users.role` column
  Parallelization: Wave 1 | Blocked by: nothing | Blocks: T5, T12
  References:
    - https://spatie.be/docs/laravel-permission (via context7)
    - app/Enums/UserRole.php (existing string-backed enum)
    - app/Models/User.php (line 12 JWTSubject, line 24 class — KEEP, add HasRoles)
    - app/Providers/AppServiceProvider.php (existing gates)
    - Modules/Auth/Services/AuthService.php (line 33-37 — add assignRole after create)
    - Modules/Classroom/Policies/ClassroomPolicy.php (existing pattern to mirror)
  Acceptance criteria:
    - `php artisan db:seed --class=RolesAndPermissionsSeeder` creates 3 roles + 4 permissions
    - User model has HasRoles trait
    - `User::find(1)->hasRole('admin')` returns true for seeded admin
    - `User::find(1)->role` (column) still returns 'admin' (dual-source)
    - After `AuthService::register`, new user has Spatie pivot row: `User::latest()->first()->hasRole('student')` returns true
    - New policy files exist at `Modules/Course/Policies/CoursePolicy.php` and `Modules/Practice/Policies/AssignmentPolicy.php`
  QA scenarios:
    - Happy: PermissionTest — assign role, sync permission, hasPermissionTo, dual-source verified
    - Failure: remove HasRoles trait — Spatie checks fail (regression)
  Commit: Y | feat(auth): install spatie/laravel-permission with dual-source RoleSynced event

- [ ] 5. Move AI endpoints to queue with AI-only cache
  What to do / Must NOT do:
    - DO: Create `app/Jobs/ProcessAiTutorJob.php` + `ProcessChatJob.php`
    - DO: Change `/ai/chat` + `/ai/tutor` endpoints to dispatch job + return `{ job_id, status: 'pending' }` in <100ms
    - DO: Add `GET /ai/job/{id}` endpoint to poll for result
    - DO: Add AI-only `Cache::remember` for Gemini response memoization:
      ```php
      $cacheKey = "ai_response:" . sha1($prompt . $model);  // PII-safe — only pseudonymous identifiers
      $cached = Cache::remember($cacheKey, 86400, fn() => $this->callGemini($prompt, $model));
      ```
    - DO: 5 identical requests in 1 minute: 4 hit cache, 1 calls Gemini
    - DO: Configure `QUEUE_CONNECTION=redis` in .env.example (production default)
    - DO: Document in .env.example: "Local dev override to `QUEUE_CONNECTION=sync` to avoid Redis requirement"
    - DO: Add `ai_async` feature flag defaulting to `true` (worker can flip for sync legacy)
    - DO NOT: Use `Cache::remember` for anything other than AI response memoization (general cache banned)
    - DO NOT: Cache PII (email, name) in cache key. Allowed: `user_id`, `target_band`
    - DO NOT: Make cache key user-content-dependent (use content hash, not user_id)
  Parallelization: Wave 2 | Blocked by: T4 | Blocks: T6, T7, T8, T11, T15
  References:
    - app/Http/Controllers/Api/AIChatController.php (chat, buildPrompt)
    - app/Http/Controllers/AiTutorController.php (ask, explain, suggest)
    - app/Services/AiTutorService.php (callGemini, HISTORY_KEY constant line 25)
    - app/Services/AI/VoiceService.php
    - config/queue.php
    - resources/views (frontend polling JS)
  Acceptance criteria:
    - POST /ai/chat returns `{ job_id, status: 'pending' }` in <100ms
    - GET /ai/job/{id} returns `{ status, result }` when done
    - 5 identical requests: 4 hit cache, 1 calls Gemini
    - Cache key is content hash, not user_id (verifiable in Redis)
    - ai_async feature flag works
  QA scenarios:
    - Happy: AsyncAiTest — submit, poll, get result, cache hit on repeat
    - Failure: queue down — job retries 3x with backoff
  Commit: Y | feat(ai): async AI endpoints with job queue + Gemini response memoization

- [ ] 6. AI streaming via Reverb WebSocket (already installed)
  What to do / Must NOT do:
    - DO: Verify Reverb version compatibility with Laravel 12 (check `composer.json`)
    - DO: Create `app/Events/AiChunkGenerated.php` (mirror `VoiceResponseEvent` pattern)
    - DO: Broadcast each Gemini chunk to private channel `ai.tutor.{user_id}`:
      ```php
      broadcast(new AiChunkGenerated($userId, $chunk, $isLast))->onPrivate("ai.tutor.{$userId}");
      ```
    - DO: Configure `routes/channels.php`:
      ```php
      Broadcast::channel('ai.tutor.{userId}', fn($user, $userId) => $user->id === (int) $userId);
      ```
    - DO: In `ProcessAiTutorJob`, broadcast each chunk from Gemini stream
    - DO: Add frontend JS to subscribe via existing `resources/js/echo.js` (already imports laravel-echo + pusher-js)
    - DO: Fallback spec: Frontend subscribes to Reverb on page load. On `connection.state === 'disconnected'` OR no `ai.chunk` event within 10s of POST, frontend polls `GET /ai/job/{id}` every 2s up to 60s
    - DO NOT: Reinstall Reverb (already wired — `config/reverb.php` exists, `ReverbMessageListener` exists)
    - DO NOT: Share channel name with `VoiceResponseEvent` (distinct: `ai.tutor.*` vs voice channel)
    - DO NOT: Add Reverb to Horizon supervisor (runs as separate `php artisan reverb:start` process)
  Parallelization: Wave 2 | Blocked by: T5 | Blocks: T11
  References:
    - https://laravel.com/docs/12.x/broadcasting (via context7)
    - config/reverb.php (exists, don't modify unless needed)
    - resources/js/echo.js (line 1 `import Echo from 'laravel-echo'`, line 3 `import Pusher from 'pusher-js'`)
    - app/Listeners/ReverbMessageListener.php (line 8 `use Laravel\Reverb\Events\MessageReceived`)
    - app/Events/VoiceResponseEvent.php (line 22 broadcastOn, line 27 broadcastAs — pattern to mirror)
    - routes/channels.php (add channel auth)
  Acceptance criteria:
    - First chunk arrives in <500ms (instead of waiting full Gemini response)
    - Channel name `ai.tutor.{user_id}` is private (auth required)
    - WebSocket events: `ai.chunk`, `ai.complete`, `ai.error`
    - Polling fallback works (test by killing Reverb process)
    - Existing `VoiceResponseEvent` still broadcasts (regression)
  QA scenarios:
    - Happy: StreamingTest — chunks arrive in order, complete event last
    - Failure: Reverb killed — frontend recovers via polling within 60s
  Commit: Y | feat(ai): streaming AI responses via Reverb WebSocket with polling fallback

- [ ] 7. Langfuse observability (CI strategy: opt-in, manually verified)
  What to do / Must NOT do:
    - DO: Add `docker/langfuse/docker-compose.yml` (Langfuse self-hosted: web + worker + postgres + clickhouse + minio)
    - DO: Add `langfuse/langfuse-php` SDK via composer
    - DO: Wrap `GeminiService::call()` with Langfuse trace: `Langfuse::trace('gemini_call', ['prompt', 'completion', 'tokens', 'latency', 'model', 'user_id'])`
    - DO: Add `LANGFUSE_PUBLIC_KEY` + `LANGFUSE_SECRET_KEY` + `LANGFUSE_ENABLED` to .env.example
    - DO: `LANGFUSE_ENABLED=false` by default in local; true in production
    - DO: Graceful failure: if Langfuse unreachable, log warning, don't fail request
    - DO: PII policy: trace metadata can include `user_id` + `target_band`, NOT `email`/`name`
    - DO: Add `php artisan langfuse:smoke-test` script (verifies trace write in <5s)
    - DO NOT: Run Langfuse service in CI (T10 CI workflow does NOT include Langfuse container)
    - DO NOT: Block request if Langfuse down (graceful failure)
    - DO NOT: Log full prompt text (truncate to 500 chars)
  Parallelization: Wave 2 | Blocked by: T5 | Blocks: T14, T15
  References:
    - https://langfuse.com/docs (via context7)
    - app/Services/AiTutorService.php (callGemini line 122)
    - Modules/Speaking/Services/AiSpeakingService.php
    - docker-compose.yml (existing — add langfuse profile)
  Acceptance criteria:
    - `docker compose --profile langfuse up` starts Langfuse UI on :3000
    - With LANGFUSE_ENABLED=true, Gemini call creates trace in Langfuse UI within 5s
    - `php artisan langfuse:smoke-test` succeeds against local Langfuse
    - With LANGFUSE_ENABLED=false, no Langfuse calls (perf test shows no slowdown)
    - CI runs with LANGFUSE_ENABLED=false (T10 verifies this)
    - PII policy: trace metadata has `user_id` but no `email`
  QA scenarios:
    - Happy: LangfuseIntegrationTest — mock Langfuse, verify trace call
    - Failure: Langfuse server down — request still completes (warning logged)
  Commit: Y | feat(ai): Langfuse self-hosted observability (CI opt-in via env)

- [ ] 8. Horizon queue dashboard + Redis queue
  What to do / Must NOT do:
    - DO: `composer require laravel/horizon`
    - DO: `php artisan horizon:install`
    - DO: Create `config/horizon.php` with 2 supervisors:
      - `default`: processes general jobs (export, notifications)
      - `ai-queue`: processes AI jobs (3 workers, 60s timeout)
    - DO: Configure `QUEUE_CONNECTION=redis` in .env.example (production); local override to `sync`
    - DO: Add `/horizon` route gated by `can:admin-access` Gate
    - DO: Add `horizon:supervisor` to deploy.sh
    - DO: Add Redis-down behavior: throw `ConnectionFailed` → controller catches → returns HTTP 503 with `Retry-After: 30`. NO silent fallback to `database` or `sync`
    - DO NOT: Enable Horizon in local dev (use sync driver)
    - DO NOT: Add Reverb to Horizon supervisor (separate process)
  Parallelization: Wave 2 | Blocked by: T5 | Blocks: T11
  References:
    - https://laravel.com/docs/12.x/horizon (via context7)
    - config/queue.php
    - .env.example
    - deploy.sh (existing)
  Acceptance criteria:
    - /horizon returns 200 for admin, 403 for non-admin
    - Horizon dashboard shows completed/failed jobs
    - AI jobs process in dedicated 'ai-queue' supervisor
    - Failed jobs auto-retry 3x with exponential backoff
    - Redis-down: `dispatch(new Job())` throws ConnectionFailed (verifiable)
  QA scenarios:
    - Happy: HorizonTest — admin sees dashboard, non-admin denied
    - Failure: `docker compose stop redis` + dispatch job → ConnectionFailed
  Commit: Y | feat(queue): Horizon dashboard with AI-queue supervisor + 503 on Redis-down

- [ ] 9. Sentry error tracking with PII scrubbing
  What to do / Must NOT do:
    - DO: `composer require sentry/sentry-laravel`
    - DO: Configure `SENTRY_LARAVEL_DSN` + `SENTRY_TRACES_SAMPLE_RATE=0.1` in .env.example
    - DO: Add Sentry SDK init to `bootstrap/app.php` (in `withExceptions` block)
    - DO: Add `Sentry\Aspects\Annotations` for SQL queries + queue jobs
    - DO: Configure source maps upload via Vite plugin (in `vite.config.js`)
    - DO: Add `Sentry::beforeSend` callback that strips `email`, `name`, `phone`, `ip` from event context
    - DO: PII policy: Sentry context may include `user_id` (pseudonymous), NOT email/name
    - DO NOT: Send full request body to Sentry (truncate to 1000 chars)
    - DO NOT: Send debug info when APP_DEBUG=false
  Parallelization: Wave 3 | Blocked by: nothing | Blocks: T10
  References:
    - https://docs.sentry.io/platforms/php/guides/laravel/ (via context7)
    - .env.example
    - bootstrap/app.php
    - vite.config.js (existing)
  Acceptance criteria:
    - Sentry SDK initialized in bootstrap/app.php
    - Manual test exception appears in Sentry.io dashboard
    - PII scrubbing: email field stripped from event (verify in dashboard)
    - Source maps uploaded for production builds
  QA scenarios:
    - Happy: SentryTest — trigger exception, verify captured (with PII stripped)
    - Failure: Sentry DSN unset — exception still logged to laravel.log (fallback)
  Commit: Y | feat(observability): Sentry error tracking with PII scrubbing via beforeSend

- [ ] 10. GitHub Actions CI/CD with API-based status checks
  What to do / Must NOT do:
    - DO: Create `.github/workflows/ci.yml` (PR trigger, all branches)
    - DO: Add 4 jobs:
      - `lint`: pint (`./vendor/bin/pint --test`)
      - `test`: phpunit (`php artisan test`)
      - `audit`: `composer audit --no-interaction`
      - `static-analysis`: `vendor/bin/phpstan analyse --level=5` (larastan)
    - DO: Cache composer (`~/.composer/cache/files`) + node_modules (`~/.npm`)
    - DO: Create `.github/workflows/deploy.yml` (main branch trigger, manual approval step)
    - DO: Add CODEOWNERS file (default owner: @minh0 or similar)
    - DO: Add `.github/PULL_REQUEST_TEMPLATE.md`
    - DO: Set required status checks via GitHub API (worker has GH_TOKEN in CI secrets):
      ```bash
      gh api --method POST repos/:owner/:repo/branches/main/protection/required_status_checks/contexts \
        -f contexts[]=lint -f contexts[]=test -f contexts[]=audit -f contexts[]=static-analysis
      ```
    - DO NOT: Use Telescope for "security" job (Telescope is debug tool, not security scanner)
    - DO NOT: Auto-deploy on main without manual approval
    - DO NOT: Skip tests on draft PRs
  Parallelization: Wave 3 | Blocked by: T9 | Blocks: T16
  References:
    - https://docs.github.com/en/actions (via context7)
    - .github/workflows/deploy.yml (existing)
    - composer.json (scripts)
  Acceptance criteria:
    - .github/workflows/ci.yml runs on PR
    - 4 jobs: lint, test, audit, static-analysis
    - Cache enabled for composer + npm
    - Status checks set via API (verify via `gh api` response)
  QA scenarios:
    - Happy: Push to test branch, verify all 4 jobs pass
    - Failure: composer audit finds CVE — CI fails with clear error
  Commit: Y | ci(github-actions): CI pipeline with lint/test/audit/static-analysis + API status checks

- [ ] 11. Playwright E2E tests for 3 critical flows
  What to do / Must NOT do:
    - DO: `npm install --save-dev @playwright/test` (NOT composer)
    - DO: `npx playwright install --with-deps chromium`
    - DO: Create `playwright.config.ts` with `baseURL` from `APP_URL` env
    - DO: Create `tests/E2E/AuthenticationFlowTest.ts` (register → login → token in DB)
    - DO: Create `tests/E2E/MockTestFlowTest.ts` (start test → answer → submit → view result)
    - DO: Create `tests/E2E/AiTutorFlowTest.ts` (chat → poll for result → verify AI response)
    - DO: Use `DatabaseTransactions` (not `RefreshDatabase`) where possible for speed
    - DO: Run in headed=false in CI
    - DO: Capture screenshot on failure (`await page.screenshot({ path: 'screenshots/' + name })`)
    - DO: Budget: Total runtime ≤10 minutes on 4-core CI runner (NOT 5 min — adjusted per G20)
    - DO NOT: Test every endpoint (only 3 critical flows)
    - DO NOT: Make tests flaky — use stable selectors + waitForLoadState
  Parallelization: Wave 3 | Blocked by: T1, T2, T5, T8 | Blocks: T16
  References:
    - https://playwright.dev/docs/intro (via context7)
    - tests/Feature/Security/ (existing patterns)
    - package.json (verify exists or add if missing)
  Acceptance criteria:
    - 3 test files in tests/E2E/
    - All pass against local docker-compose
    - Total runtime ≤10 minutes
    - 0 flaky tests (run 3x consecutively, all pass)
  QA scenarios:
    - Happy: `npx playwright test` — all 3 flows pass
    - Failure: seed user missing — fail with clear error
  Commit: Y | test(e2e): Playwright tests for 3 critical user flows (npm install)

- [ ] 12. K6 load test scripts (CI nightly via T17)
  What to do / Must NOT do:
    - DO: Create `tests/Load/login-brute-force.js` (100 concurrent)
    - DO: Create `tests/Load/ai-tutor.js` (50 concurrent, p95 < 5s)
    - DO: Create `tests/Load/search-stress.js` (200 concurrent, p95 < 1s)
    - DO: Create `database/seeders/PerformanceTestSeeder.php` (gated by `APP_ENV=loadtest`):
      - 100 student users: `loadtest+{001..100}@example.com` / `LoadTest!23abc`
      - 10 teacher users: `loadtest+teacher+{01..10}@example.com` / `LoadTest!23abc`
      - 1 admin user: `loadtest-admin@example.com` / `LoadTest!23abc`
    - DO: Set K6 thresholds: `http_req_duration: ['p(95)<500', 'p(99)<2000']`, `http_req_failed: ['rate<0.01']`
    - DO: Create `tests/Load/README.md` with results interpretation
    - DO NOT: Run load tests against production (only staging)
    - DO NOT: Use real user credentials (use seeded test users only)
  Parallelization: Wave 3 | Blocked by: T1, T4, T5 | Blocks: T17
  References:
    - https://k6.io/docs/ (via context7)
    - existing rate limiters in AppServiceProvider (login 5/min, register 3/hour)
    - database/seeders/ (existing — add PerformanceTestSeeder)
  Acceptance criteria:
    - 3 K6 scripts in tests/Load/
    - Login test: 100 concurrent, expect 429s after 5 (verifies throttle)
    - AI test: 50 concurrent, p95 < 5s
    - Search test: 200 concurrent, p95 < 1s
    - PerformanceTestSeeder creates 111 test users
  QA scenarios:
    - Happy: `k6 run login-brute-force.js` — 429 spike after 5 attempts
    - Failure: results show error rate > 1% — flag as fail
  Commit: Y | test(load): K6 scripts for login/AI/search with 111 test user fixture

- [ ] 13. SM-2 algorithm for flashcard SRS (active path: app/Http/Controllers/FlashcardController)
  What to do / Must NOT do:
    - DO: Read `app/Http/Controllers/FlashcardController.php` (active path, line 44 has `grade` method)
    - DO: Read `app/Models/Flashcard.php` (verify if owns `flashcards` table)
    - DO: Create `app/Services/Flashcard/SM2Algorithm.php` with `calculate(grade, currentState): newState` method
    - DO: Create `app/Jobs/UpdateFlashcardScheduleJob.php`
    - DO: Update `FlashcardController::grade()` (line 44) to dispatch job + call SM-2
    - DO: Migration: add 4 columns to `flashcards` table:
      - `ease_factor` (decimal 3,2, default 2.50)
      - `interval` (unsigned int, default 0)
      - `repetitions` (unsigned int, default 0)
      - `next_review_at` (timestamp, nullable)
    - DO: Seed 5 example cards with varied schedules
    - DO NOT: Update `Modules/Flashcard/Http/Controllers/FlashcardController.php` (only `saveToPersonal`/`store`/`update` — no `grade` method)
    - DO NOT: Change existing flashcard UI (only backend schedule logic)
  Parallelization: Wave 4 | Blocked by: nothing | Blocks: nothing
  References:
    - https://super-memory.com/english/ol/sm2.htm (via context7)
    - app/Http/Controllers/FlashcardController.php (line 20 class, line 44 grade method)
    - Modules/Flashcard/Http\Controllers/FlashcardController.php (no grade method)
    - app/Models/Flashcard.php (verify ownership)
  Acceptance criteria:
    - Migration adds 4 columns to flashcards table
    - SM2Algorithm class with `calculate()` method
    - After grading card 5 times correctly: interval = 32 days (SM-2 progression)
    - Grading "again" (0) resets repetitions to 0, interval to 1 day
  QA scenarios:
    - Happy: SM2AlgorithmTest — all 4 grade outcomes (0, 1, 3, 5) produce correct next state
    - Failure: invalid grade value — throw exception, don't corrupt state
  Commit: Y | feat(flashcard): SM-2 spaced repetition algorithm with schedule columns

- [ ] 14. Writing grader 4-criteria IELTS rubric (Langfuse tracing)
  What to do / Must NOT do:
    - DO: Read `Modules/Writing/` (locate submission model)
    - DO: Create `app/Services/Writing/IeltsRubric.php` with 4 criteria enum:
      - Task Achievement (TA)
      - Coherence & Cohesion (CC)
      - Lexical Resource (LR)
      - Grammatical Range & Accuracy (GRA)
    - DO: Update Gemini prompt to request JSON: `{ta: 0-9, cc: 0-9, lr: 0-9, gra: 0-9, feedback: string}`
    - DO: Migration: add 5 columns to writing_submissions table:
      - `ta_score` (decimal 3,1, nullable)
      - `cc_score` (decimal 3,1, nullable)
      - `lr_score` (decimal 3,1, nullable)
      - `gra_score` (decimal 3,1, nullable)
      - `overall_band` (decimal 3,1, nullable)
    - DO: Add `IeltsRubric::parseOrFallback(string $raw): array`:
      - On valid JSON: parse + compute overall_band = average rounded to 0.5
      - On invalid JSON: return `{ta: null, cc: null, lr: null, gra: null, overall_band: null, parse_error: 'invalid_json'}`
    - DO: On parse failure, submission stored with all NULL scores + Admin UI shows "Manual grading required" badge
    - DO: Langfuse trace includes rubric scores
    - DO NOT: Use existing single-band-score column (add new columns; deprecate old in UI but keep column)
  Parallelization: Wave 4 | Blocked by: T7 (Langfuse) | Blocks: nothing
  References:
    - https://www.ielts.org/for-test-takers/how-ielts-is-scored (via context7)
    - Modules/Writing/ (existing module)
    - app/Services/AiTutorService.php (Gemini call pattern)
  Acceptance criteria:
    - IeltsRubric class with `validateScores()` (each 0-9, 0.5 increments)
    - Migration adds 5 columns
    - Overall = average of 4 scores rounded to 0.5
    - On parse failure: scores NULL, admin UI shows "Manual grading required"
    - Langfuse trace includes 4 rubric scores
  QA scenarios:
    - Happy: IeltsRubricTest — validation, averaging, edge cases
    - Failure: invalid JSON from Gemini — fallback to NULLs (not silent)
  Commit: Y | feat(writing): 4-criteria IELTS rubric with Gemini JSON scoring + NULL fallback

- [ ] 15. Speaking mock test full flow (timer + audio + Gemini score)
  What to do / Must NOT do:
    - DO: Read `Modules/Speaking/Http/Controllers/SpeakingController.php`
    - DO: Create `app/Http/Controllers/Speaking/MockTestController.php` (separate from SpeakingController)
    - DO: Add route `Route::get('/speaking/mock-test', [MockTestController::class, 'show'])->name('speaking.mock_test.show')`
    - DO: Add 3-part timer UI: 60s prep, 2min response per part (intro, cue card, discussion)
    - DO: Use existing `app/Services/AI/VoiceService.php` for audio recording via MediaRecorder
    - DO: Submit audio via `app/Jobs/ProcessMockTestAudioJob.php` to Gemini for scoring
    - DO: Gemini prompt returns 4-criteria scores: `{fluency: 0-9, lexical: 0-9, grammar: 0-9, pronunciation: 0-9}`
    - DO: Store full mock test in `speaking_mock_tests` table with audio_paths (JSON) + scores
    - DO: Migration: create `speaking_mock_tests` table
    - DO: Result page displays 4-criteria breakdown
    - DO NOT: Add Web Speech API integration (TL;DR was imprecise — use Gemini-only)
    - DO NOT: Use existing SpeakingController (separate mock test flow)
    - DO NOT: Block UI waiting for Gemini (return result page immediately, show "Grading in progress" if job not done)
  Parallelization: Wave 4 | Blocked by: T5 (queue), T7 (Langfuse tracing) | Blocks: nothing
  References:
    - https://www.ielts.org/for-test-takers/how-ielts-is-scored (via context7)
    - Modules/Speaking/Http/Controllers/SpeakingController.php
    - app/Services/AI/VoiceService.php
    - app/Services/AiTutorService.php (Gemini call pattern)
  Acceptance criteria:
    - Route /speaking/mock-test renders with 3-part timer (visible UI)
    - Audio recorded via MediaRecorder, sent to backend
    - Job dispatched, returns immediately
    - Gemini returns 4-criteria scores, displayed in result page
    - Mock test stored with audio_paths + scores in DB
    - Langfuse trace includes 4 mock test scores
  QA scenarios:
    - Happy: MockTestFlowTest — full flow (load → record → submit → score)
    - Failure: mic permission denied — graceful error message
  Commit: Y | feat(speaking): full IELTS mock test (3 parts, timer, audio, 4-criteria scoring)

- [ ] 16. E2E in CI workflow
  What to do / Must NOT do:
    - DO: Add E2E step to `.github/workflows/ci.yml`
    - DO: Use docker service containers (mysql, redis) as sidecars
    - DO: Run `php artisan migrate:fresh --seed` before E2E
    - DO: Set `LANGFUSE_ENABLED=false` for CI (per G11)
    - DO: Capture screenshots on failure, upload as GitHub artifact
  Parallelization: Wave 4 | Blocked by: T10, T11 | Blocks: F1-F4
  References:
    - .github/workflows/ci.yml (from T10)
    - tests/E2E/ (from T11)
  Acceptance criteria:
    - CI runs E2E on every PR
    - 3 critical flows pass
    - Screenshots saved on failure as artifact
  QA scenarios:
    - Happy: full PR simulation
    - Failure: screenshot captured, uploaded as artifact
  Commit: Y | ci(github-actions): add Playwright E2E to CI pipeline with screenshot artifacts

- [ ] 17. K6 load test in CI nightly
  What to do / Must NOT do:
    - DO: Create `.github/workflows/load-test.yml` (schedule: nightly)
    - DO: Setup K6 in CI (use `grafana/k6-action`)
    - DO: Create `tests/Load/baselines/*.json` committed to repo, one per script, capturing `p95_latency_ms` from first green run
    - DO: Create `tests/Load/compare-results.js` that diffs current run vs baseline; fails if any metric regresses >20%
    - DO: On regression failure: open GitHub Issue using `peter-evans/create-issue-from-file` with title `[k6-regression] {script}`
    - DO NOT: Block PR on load test failure (nightly only, not PR-triggered)
  Parallelization: Wave 4 | Blocked by: T12 | Blocks: F1-F4
  References:
    - https://github.com/marketplace/actions/run-k6-load-tests
    - .github/workflows/
    - tests/Load/ (from T12)
  Acceptance criteria:
    - Nightly K6 run at 02:00 UTC
    - Baseline JSON files committed
    - Comparison script fails on >20% regression
    - Auto-creates GitHub Issue on regression
  QA scenarios:
    - Happy: trigger manually, verify report + baseline diff
    - Failure: K6 error — workflow fails with logs
  Commit: Y | ci(github-actions): nightly K6 load tests with regression detection via baseline diff

## Final verification wave
> Runs in parallel after ALL todos. ALL must APPROVE.
- [ ] F1. Plan compliance audit — verify all 17 todos done, E2E pass, load tests pass, CI green
- [ ] F2. Code quality review — pint clean, phpunit 100% pass, Sentry/Langfuse integrated
- [ ] F3. Real manual QA — register/login, take mock test, view analytics — end-to-end human-equivalent flow
- [ ] F4. Scope fidelity — verify NO general cache layer (only AI memoization), NO mobile app, NO breaking changes to existing user data

## Commit strategy
- Each todo = 1 commit
- Conventional Commits format
- Branch: `feature/upgrade-roadmap`
- Squash merge to main at end
- All commits atomic (no partial state)

## Success criteria
- All 17 todos completed with passing tests
- E2E + load tests green
- CI pipeline blocks on any failure (lint, test, audit, static-analysis)
- Sentry receives 1 test alert (PII scrubbed)
- Langfuse shows 1 test trace (with `user_id`, no email)
- Horizon dashboard accessible at /horizon (admin only)
- Sanctum tokens working for /api/* (feature flag controls)
- OAuth2 client credentials working via Passport
- Dual-source roles: `users.role` + Spatie pivot both populated
- SM-2 algorithm produces correct schedule (32 days after 5 correct grades)
- Writing rubric shows 4 scores (or NULL with "Manual grading required" badge)
- Speaking mock test full flow works (3 parts, timer, audio, 4-criteria scoring)
- 0 regression in existing 19 security tests
- 0 new lint errors (pint clean)
- 0 new composer audit advisories
- 0 new static-analysis errors (phpstan level 5)

## Metis Gap Analysis (incorporated)

The following 25 gaps were identified by Metis pre-planning review. All have been folded into the todos above:

| ID | Sev | Resolution Location |
|----|-----|---------------------|
| G1 | Critical | T1, T2 — precise package name `PHPOpenSourceSaver\JWTAuth`, explicit `config('auth.sanctum_only')` key, auth:web guard unchanged |
| G2 | Critical | T5 — AI-only `Cache::remember` for Gemini memoization only; general cache still banned |
| G3 | Major | TL;DR + T2 — clarified Sanctum is for API tokens, not web SPA (project has no SPA) |
| G4 | Major | T4 — Policies placed at `Modules/*/Policies/` (HMVC), not `app/Policies/` |
| G5 | Major | T14 — removed fictitious T3 dependency (T14 doesn't use Passport) |
| G6 | Critical | T4 — dual-source `users.role` + Spatie pivot + `RoleSynced` event + `assignRole` in `AuthService::register` |
| G7 | Critical | T1, T2 — explicit DO NOT: `auth:web` guard stays on session driver |
| G8 | Critical | T2 — `JWT_REMOVAL_DATE` env var + `auth:migrate-to-sanctum` command + commit message "with JWT 30-day fallback" implies post-roadmap flip (T18 not added here; document in commit message) |
| G9 | Major | TL;DR PII policy + T5 DO NOT — pseudonymous IDs allowed, direct IDs banned |
| G10 | Major | T5 — `.env.example` ships Redis for Docker, local override to sync in dev |
| G11 | Major | T7 + T10 — Langfuse self-hosted local only, CI runs with `LANGFUSE_ENABLED=false` |
| G12 | Major | T13 — active path is `app/Http/Controllers/FlashcardController.php` line 44, NOT `Modules/Flashcard/...` |
| G13 | Minor | T15 — removed Web Speech API from TL;DR; use Gemini-only for pronunciation |
| G14 | Critical | T10 + T11 — CI workflow does not include Langfuse service container; T7 verified manually via `langfuse:smoke-test` |
| G15 | Major | T11 — `npm install --save-dev @playwright/test`, NOT composer |
| G16 | Major | T6 — explicit polling fallback spec (10s timeout, 2s poll, 60s max) |
| G17 | Major | T10 — replaced "security (Telescope check)" with "static-analysis (larastan level 5)" |
| G18 | Minor | T17 — baseline JSON files + comparison script + GitHub Issue alert |
| G19 | Critical | T6 — Reverb already installed; new event `AiChunkGenerated` distinct from `VoiceResponseEvent`; Reverb runs as separate process |
| G20 | Major | T11 — 10-minute budget (not 5); use `DatabaseTransactions` not `RefreshDatabase` |
| G21 | Minor | T4 — `AuthService::register` adds `$user->assignRole($data['role'])` after create |
| G22 | Major | T8 — Redis-down throws ConnectionFailed → 503 with Retry-After; NO silent fallback |
| G23 | Major | T14 — `IeltsRubric::parseOrFallback` returns NULLs on invalid JSON; admin UI shows "Manual grading required" |
| G24 | Major | T10 — required status checks via `gh api` (worker has GH_TOKEN in CI secrets) |
| G25 | Minor | T12 — `PerformanceTestSeeder` creates 100 students + 10 teachers + 1 admin (111 total) |
