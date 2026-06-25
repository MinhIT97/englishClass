# security-review - Work Plan

## TL;DR (For humans)
<!-- Filled LAST after detailed plan is written -->

**What you'll get:** Một file `SECURITY_AUDIT.md` ở working tree (chưa commit) liệt kê mọi lỗi bảo mật tìm được trong project englishClass, phân loại theo severity (Critical/High/Medium/Low/Info) với vị trí file:line cụ thể, đoạn code chứng minh, mapping sang CWE + OWASP, và code diff đề xuất fix cho từng mục High/Critical. Bao gồm cảnh báo về 4 secrets (Telegram bot token, Gemini API key, JWT secret, INTERNAL_API_TOKEN) đang nằm trong `.env` local cần rotate.

**Why this approach:** Review được chia theo 10 attack-surface component (auth, authz, validation, upload, telegram, AI, rate-limit, config, logging, supply-chain) để cover đủ chiều — không chỉ OWASP Top 10 mà còn business-logic, race conditions, supply-chain. Worker dùng `composer audit` + `php -l` + grep + đọc thủ công để có evidence, không exploit thật. Mỗi component ghi rõ file:line nên dễ audit lại.

**What it will NOT do:** KHÔNG edit product code; KHÔNG exploit thật lên bất kỳ môi trường nào (kể cả local); KHÔNG commit file audit (chỉ stage working tree, bạn review trước); KHÔNG rotate secrets (chỉ khuyến nghị, bạn tự làm).

**Effort:** Large (~3-5 giờ worker, 10 components × ~20-30 phút mỗi cái + tools + tổng hợp)
**Risk:** Low — read-only audit, không đụng code product. Rủi ro duy nhất là file `SECURITY_AUDIT.md` xuất hiện ở working tree (chưa commit) đợi bạn review.
**Decisions to sanity-check:**
- Bạn đã chọn "Full audit" (không chỉ OWASP Top 10) — nên tốn thời gian hơn quick scan nhưng cover kỹ hơn
- Worker sẽ KHÔNG sửa code — chỉ đề xuất diff. Nếu muốn apply fix, bạn copy/paste từ report
- Worker sẽ stage `SECURITY_AUDIT.md` (untracked) chứ KHÔNG commit. Nếu bạn muốn commit thì tự commit sau khi review

Your next move: Đọc `.omo/plans/security-review.md` này, nếu OK thì gõ `$start-work` (hoặc `/start-work`) để worker thực thi. Worker sẽ tạo `SECURITY_AUDIT.md` ở working tree.

---

> TL;DR (machine): Large / Low / 1 file SECURITY_AUDIT.md (untracked) + 10-component todo waves; full audit; read-only; no commits; no exploit.

## Scope

### Must have
- Phát hiện **TẤT CẢ** lỗi bảo mật có thể xác định bằng phương pháp tĩnh (static review + grep + composer audit + đọc code thủ công), ở 10 attack-surface components
- Mỗi finding PHẢI có:
  - **ID** duy nhất (SEC-001, SEC-002, ...)
  - **Severity** (Critical / High / Medium / Low / Info) — justify bằng impact + exploitability, không phải guess
  - **CWE ID** mapping (e.g. CWE-89 SQL Injection)
  - **OWASP Top 10 2021** category (e.g. A01:2021-Broken Access Control)
  - **Location** file:line cụ thể
  - **Evidence** đoạn code snippet hoặc output tool
  - **Remediation** mô tả fix bằng lời
  - **Code diff** (cho High/Critical) — unified diff style, chỉ ra trước/sau
- Đối với component "sạch" (không tìm thấy lỗi), vẫn phải ghi rõ "no findings — evidence: đã đọc file X, đã grep Y, không có pattern Z"
- Có Executive Summary ở đầu: tổng số findings theo severity, top 3 controls đã tốt (positive findings), top 3 rủi ro cao nhất cần fix trước
- Có Appendix: raw output từ `composer audit` + grep (để bạn reproduce được)
- Stage `SECURITY_AUDIT.md` ở working tree (KHÔNG commit)

### Must NOT have (guardrails, anti-slop, scope boundaries)
- **KHÔNG edit** bất kỳ file PHP / Blade / config / migration / JS / .env nào của project
- **KHÔNG exploit thật** lên bất kỳ môi trường nào (kể cả local) — không gửi request thật đến app, không chạy script gọi DB, không ghi file upload
- **KHÔNG commit** `SECURITY_AUDIT.md` (chỉ để untracked, bạn review rồi tự commit)
- **KHÔNG rotate secrets** (Telegram bot, Gemini API key, JWT, internal token) — chỉ khuyến nghị rotate trong report
- **KHÔNG leak secrets** ra ngoài working tree (không tạo file riêng chứa secrets, không echo secrets ra console log)
- **KHÔNG generate PoC exploit** mà có thể bị lạm dụng (ví dụ: không viết script có thể chạy lại để tấn công thật)
- **KHÔNG suy đoán findings không có evidence** — nếu không tìm được file:line cụ thể, ghi là "needs deeper investigation" thay vì claim
- KHÔNG sửa `SECURITY.md` / `SECURITY_UPGRADE_PLAN.md` hiện có — chỉ tham chiếu chúng
- KHÔNG thêm finding "để đẹp report" — chỉ ghi những gì thật sự tìm được, nếu component sạch thì ghi "no findings"

## Verification strategy
> Zero human intervention - all verification is agent-executed.
- **Test decision:** none — đây là audit (read-only), không phải feature task. "Test" là bản thân evidence mà worker tìm được.
- **Evidence paths:**
  - `.omo/evidence/task-N-security-review.md` cho mỗi todo: ghi lại file đã đọc, grep đã chạy, composer audit output (sanitized), findings thô
  - Final: `SECURITY_AUDIT.md` ở working tree (root), untracked
- **Tooling evidence (reproducible):**
  - `composer audit --format=json --no-interaction 2>&1` → save output sanitized (đã redact secret nếu có)
  - `php -l` trên mọi file PHP trong scope (collect via `find`)
  - `grep -rn` các pattern nguy hiểm (xem Todo C5 Input Validation cho list đầy đủ)
  - Manual code review cho authorization logic, FormRequest, services
- **Severity calibration rule:** Critical = exploitable từ xa, không cần auth, impact cao (RCE, auth bypass, data breach). High = cần auth hoặc local, impact cao (privilege escalation, mass PII leak). Medium = cần specific condition, impact trung bình (stored XSS, info disclosure). Low = defense-in-depth gợi ý. Info = positive finding hoặc hygiene.
- **Verifiability:** mỗi finding có file:line + snippet → bạn (hoặc reviewer) mở file đó lên kiểm tra được trong <1 phút

## Execution strategy

### Parallel execution waves

> Target 5-8 todos per wave. Audit task có 10 components chia thành 3 waves. Mỗi todo là 1 attack-surface component độc lập, hoàn toàn parallel được.

**Wave 1 — Configuration & Supply Chain** (parallel, 2 todos)
- Todo 1: C1 Configuration & secrets
- Todo 2: C2 Composer supply chain

**Wave 2 — Core attack surfaces** (parallel, 5 todos)
- Todo 3: C3 Auth & session
- Todo 4: C4 Authorization / IDOR
- Todo 5: C5 Input validation
- Todo 6: C6 File upload
- Todo 7: C7 Telegram & webhook

**Wave 3 — App-specific & polish** (parallel, 3 todos)
- Todo 8: C8 AI / Gemini integration
- Todo 9: C9 Rate limit & DoS
- Todo 10: C10 Logging & PII

**Wave 4 — Final consolidation** (sequential, 1 todo)
- Todo 11: Tổng hợp findings từ 10 evidence files → `SECURITY_AUDIT.md`

**Final verification wave** (4 sub-tasks, parallel, sau Wave 4)

### Dependency matrix

| Todo | Depends on | Blocks | Can parallelize with |
| --- | --- | --- | --- |
| 1. C1 Config & secrets | nothing | 11 | 2, 3, 4, 5, 6, 7, 8, 9, 10 |
| 2. C2 Composer supply chain | nothing | 11 | 1, 3, 4, 5, 6, 7, 8, 9, 10 |
| 3. C3 Auth & session | nothing | 11 | 1, 2, 4, 5, 6, 7, 8, 9, 10 |
| 4. C4 Authorization / IDOR | nothing | 11 | 1, 2, 3, 5, 6, 7, 8, 9, 10 |
| 5. C5 Input validation | nothing | 11 | 1, 2, 3, 4, 6, 7, 8, 9, 10 |
| 6. C6 File upload | nothing | 11 | 1, 2, 3, 4, 5, 7, 8, 9, 10 |
| 7. C7 Telegram & webhook | nothing | 11 | 1, 2, 3, 4, 5, 6, 8, 9, 10 |
| 8. C8 AI / Gemini | nothing | 11 | 1, 2, 3, 4, 5, 6, 7, 9, 10 |
| 9. C9 Rate limit & DoS | nothing | 11 | 1, 2, 3, 4, 5, 6, 7, 8, 10 |
| 10. C10 Logging & PII | nothing | 11 | 1-9 |
| 11. Consolidate SECURITY_AUDIT.md | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 | F1-F4 | none (must be sequential) |

## Todos

> Implementation + Test = ONE todo. For audit task, "test" IS the evidence (file:line + grep + tool output).
> 11 todos total: 10 attack-surface components + 1 final consolidation.
> Worker must NOT rewrite the headers above.

- [x] 1. C1 — Audit Configuration & secrets (`.env`, JWT, session, Telescope, APP_DEBUG, BCRYPT, key rotation) — COMPLETED (9 findings: 2 High, 2 Medium, 4 Low, 1 Info)
  What to do / Must NOT do:
    - DO: đọc `.env`, `.env.example`, `.env.docker`, `.env.test`, `config/jwt.php`, `config/session.php`, `config/app.php`, `config/logging.php`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`. Grep `env(`, `config(`, `APP_DEBUG`, `APP_KEY`, `JWT_SECRET`, `SESSION_`, `BCRYPT`. Kiểm tra `app/Http/Middleware/SecurityHeaders.php` xem CSP có quá lỏng không. Đối chiếu `SECURITY.md` checklist.
    - DO: nếu tìm thấy secrets trong working tree, ghi vào evidence (chỉ tên biến + độ dài + 4 ký tự đầu/4 ký tự cuối để xác nhận, KHÔNG ghi full secret value ra file evidence).
    - DO NOT: edit bất kỳ file nào. KHÔNG rotate secrets. KHÔNG ghi full secret ra bất kỳ file evidence nào.
    - DO NOT: assume "no finding" — phải đọc thật sự từng file và grep thật sự.
  Parallelization: Wave 1 | Blocked by: nothing | Blocks: 11
  References (executor has NO interview context - be exhaustive):
    - `.env`, `.env.example`, `.env.docker`, `.env.test` (đã đọc ở phase ground)
    - `config/jwt.php`, `config/session.php`, `config/app.php`, `config/logging.php`, `config/cache.php`
    - `bootstrap/app.php` (middleware registration)
    - `app/Providers/AppServiceProvider.php` (gates, rate limiters)
    - `app/Http/Middleware/SecurityHeaders.php`
    - `app/Http/Middleware/AuditAdminActions.php`
    - `app/Services/AuditLogger.php`
    - `SECURITY.md` lines 110-135 (Production Deployment Checklist)
  Acceptance criteria (agent-executable):
    - Có file `.omo/evidence/task-1-security-review.md` chứa:
      a) Bảng inventory tất cả `env(` calls trong code (file:line + biến env)
      b) Đánh giá từng biến: có default an toàn không, có được validate không, có sensitive không
      c) Findings theo schema (ID/Severity/CWE/OWASP/Location/Evidence/Remediation/Diff)
      d) Nếu component sạch: ghi "no findings — evidence: ..." với reference cụ thể
    - Verify được: APP_DEBUG default trong .env.example có đúng `false` không (đã đọc README claim)
    - Verify được: SESSION_SECURE_COOKIE có được set true trong .env.docker không
    - Verify được: Telescope có được disable trong production không (composer.json yêu cầu dev, kiểm tra service provider registration)
    - Có ít nhất 1 finding liên quan `.env` (recommend rotate secrets đã lộ local)
  QA scenarios (name the exact tool + invocation): happy + failure, Evidence .omo/evidence/task-1-security-review.md
    - Happy: chạy `grep -rn "env(" app/ Modules/ | head -50` thu được list; đối chiếu từng cái với .env.example; kết quả có ≥1 finding về .env hygiene.
    - Failure: nếu không tìm thấy finding nào, evidence file phải có câu "no findings — đã đọc .env.example đầy đủ, không có giá trị default nguy hiểm" + danh sách 12 env biến đã verify.
  Commit: N | type(audit): cannot edit product code

- [x] 2. C2 — Audit Composer / NPM supply chain (CVE known deps, version pinning, dev deps lọt prod) — COMPLETED (20 findings: 16 CVE Laravel/Symfony/Guzzle runtime, 4 NPM dev-only)
  What to do / Must NOT do:
    - DO: chạy `composer audit --format=json --no-interaction 2>&1` (hoặc `composer audit` nếu JSON không available), redirect output vào `.omo/evidence/task-2-composer-audit.txt`. Nếu không có composer installed locally, document điều đó và dùng `composer.lock` parse manually.
    - DO: đọc `composer.json` + `composer.lock` cho Laravel 12, `php-open-source-saver/jwt-auth`, `nwidart/laravel-modules`, `prettus/l5-repository`, `spatie/laravel-sluggable`, `textalk/websocket`, dev deps (`barryvdh/laravel-debugbar`, `laravel/telescope`).
    - DO: đọc `package.json` + `package-lock.json` (chỉ tóm tắt deps, không cần npm audit vì frontend nhẹ).
    - DO: kiểm tra dev-only deps có bị require vào production không (xem `require-dev` có chính xác không).
    - DO NOT: chạy `composer update` hoặc `composer install` (có thể thay đổi lock file).
  Parallelization: Wave 1 | Blocked by: nothing | Blocks: 11
  References (executor has NO interview context - be exhaustive):
    - `composer.json` (đã đọc)
    - `composer.lock` (file 375KB, dùng grep để tìm version constraints)
    - `package.json` (479 bytes, đã đọc header)
    - `package-lock.json` (91KB)
    - `Modules/*/composer.json` (15 modules, kiểm tra merge-plugin resolve đúng)
  Acceptance criteria (agent-executable):
    - Có `.omo/evidence/task-2-security-review.md`:
      a) Raw `composer audit` output (sanitized)
      b) Bảng từng package chính + version + ngày phát hành + known CVEs (nếu có)
      c) Findings (nếu có CVE chưa patched)
      d) Đánh giá dev-deps có lọt production không
    - Có thể cite: Laravel framework version exact (đã thấy `13.0.0 as 12.0.0`), JWT-auth version (`^2.9`).
    - Recommendation cho mỗi CVE tìm được (upgrade version / alternative).
  QA scenarios:
    - Happy: `composer audit` returns list; mỗi CVE được cite trong report với fix version.
    - Failure: nếu composer không chạy được (Windows path issue), evidence phải có log lỗi + manual parsing composer.lock cho các package version.
  Commit: N

- [x] 3. C3 — Audit Auth & session (register, login, JWT, password policy, session fixation) — COMPLETED (7 findings: 2 High, 2 Medium, 3 Low)
  What to do / Must NOT do:
    - DO: đọc `Modules/Auth/Http/Controllers/AuthController.php`, `Modules/Auth/Http/Requests/*`, `Modules/Auth/routes/web.php`, `Modules/Auth/routes/api.php`.
    - DO: đọc `config/jwt.php` (TTL, refresh, blacklist, required claims).
    - DO: đọc `app/Models/User.php` (fillable, hidden, casts, password mutator).
    - DO: kiểm tra `php-open-source-saver/jwt-auth` ^2.9 có known CVEs không (cross-ref với composer audit Todo 2).
    - DO: kiểm tra register có dùng `$request->only([...])` không hay `$request->all()` (mass assignment).
    - DO: kiểm tra login có password_verify đúng cách không, có rate limit thật không, có lockout account sau N lần sai không.
    - DO: kiểm tra logout có invalidate JWT không (blacklist enabled).
    - DO: kiểm tra session cookie flags (HttpOnly, Secure, SameSite) qua `config/session.php` và `.env`.
    - DO NOT: gửi request thật đến /login hoặc /register.
  Parallelization: Wave 2 | Blocked by: nothing | Blocks: 11
  References (executor has NO interview context - be exhaustive):
    - `Modules/Auth/Http/Controllers/AuthController.php`
    - `Modules/Auth/Http/Requests/RegisterRequest.php`, `LoginRequest.php` (nếu có)
    - `Modules/Auth/routes/web.php`, `Modules/Auth/routes/api.php`
    - `config/jwt.php`
    - `config/session.php`
    - `app/Models/User.php`
    - `app/Http/Middleware/*` (auth-related)
    - `routes/api.php` (auth endpoints)
    - `tests/Feature/Security/MassAssignmentTest.php` (existing test — verify it actually tests what it claims)
    - `tests/Feature/Security/RateLimitTest.php`
  Acceptance criteria (agent-executable):
    - Có `.omo/evidence/task-3-security-review.md`:
      a) Mỗi FormRequest: trích excerpt validate rules + authorize logic
      b) Mass assignment risk: list các field nhạy cảm (`role`, `is_unlimited`, `status`, `password`) và xác nhận có bị filter không
      c) JWT config: TTL, refresh TTL, blacklist enabled, leeway, required claims
      d) Session config: driver, lifetime, encrypt, http_only, secure, same_site
      e) Password hashing: bcrypt rounds, verify có qua Hash facade
      f) Login flow: throttle đúng, lockout không, fail message generic hay leak info
    - Verify: existing MassAssignmentTest có thật sự test register không cho set `role` không (đọc code test).
    - Findings: mỗi risk có file:line + snippet + severity + fix.
  QA scenarios:
    - Happy: tìm được ≥1 finding (ví dụ: JWT blacklist disabled, hoặc register mass assignment chưa filter, hoặc login response leak info user-enumeration).
    - Failure: nếu component "sạch" hoàn toàn, evidence phải ghi rõ "no findings — đã đọc AuthController.php, mass assignment test đã cover, JWT blacklist enabled, ...".
  Commit: N

- [x] 4. C4 — Audit Authorization / IDOR (gates, policies, ownership check, role escalation) — COMPLETED (4 findings: 1 High, 2 Medium, 1 Low)
  What to do / Must NOT do:
    - DO: kiểm tra MỌI controller trong `app/Http/Controllers/` và `Modules/*/Http/Controllers/` có dùng gate/policy/middleware không, hay chỉ `auth()` middleware.
    - DO: đọc `app/Policies/*` (nếu có), `app/Providers/AppServiceProvider.php` (gates).
    - DO: spot-check IDOR trên: CommunityController (notes/comments có check owner không), StudyPlanController (update/destroy có check owner không), FlashcardController (grade có check user_id không), SettingsController::export (chỉ export của mình hay ai cũng export được), SearchController (admin-only user search có filter đúng không).
    - DO: AdminBulkController: kiểm tra có validate input array đúng không, có check role admin trên từng operation không, có audit log cho mỗi bulk action không.
    - DO: TelegramWebhookController::approveUser / rejectUser (đã đọc ở phase ground): verify `chat_id` check có thật sự chống spoof không, hay chỉ tin tưởng Telegram callback (line 102-114).
    - DO: kiểm tra route `Route::middleware(['auth', 'can:admin-access', 'audit.admin'])` (line 85 web.php) cover đủ admin route chưa.
    - DO NOT: gửi request IDOR thật.
  Parallelization: Wave 2 | Blocked by: nothing | Blocks: 11
  References (executor has NO interview context - be exhaustive):
    - All files in `app/Http/Controllers/` (22 files, đã list ở phase ground)
    - All files in `app/Policies/` (nếu có)
    - `app/Providers/AppServiceProvider.php` (gates section)
    - `routes/web.php`, `routes/api.php` (full route file)
    - `Modules/*/Http/Controllers/*` (15 modules × variable count)
    - `Modules/*/routes/*` (15 modules routes)
    - `tests/Feature/Security/AuthorizationTest.php` (verify coverage)
  Acceptance criteria (agent-executable):
    - Có `.omo/evidence/task-4-security-review.md`:
      a) Bảng: controller | endpoint | middleware chain | có policy không | có ownership check không
      b) Mỗi controller có write operation: phân tích ownership / role check
      c) IDOR candidates: list endpoint + cách exploit (lý thuyết) + severity
      d) Admin route coverage: list `/admin/*` và `/api/admin/*` (nếu có) và verify middleware đầy đủ
      e) Telegram callback spoof analysis: nếu secret verify dùng `hash_equals` đúng cách thì OK; nếu dùng `!==` thì Critical
    - Finding severity: thường là High (privilege escalation) hoặc Medium (info disclosure).
  QA scenarios:
    - Happy: tìm được ≥3 IDOR candidates (ví dụ: StudyPlan destroy không check owner, Community notes edit không check author, Settings export trả về user_id từ route param thay vì auth user).
    - Failure: nếu tất cả controller đều có authz đúng, evidence ghi "no IDOR findings — verified N controllers, M có policy, K có inline ownership check".
  Commit: N

- [x] 5. C5 — Audit Input validation (FormRequest, XSS, search DoS, file name, JSON depth) — COMPLETED (3 findings: 1 High, 1 Medium, 1 Low)
  What to do / Must NOT do:
    - DO: kiểm tra MỌI FormRequest có Authorize + Rules đầy đủ không (không phải FormRequest = security risk vì không có validation layer).
    - DO: grep `request->all()` trong Controllers — pattern nguy hiểm (mass assignment ngầm).
    - DO: grep `request->input(` không qua validate — chỗ nào dùng raw input cho DB query.
    - DO: grep Blade views cho `{!! ... !!}` (unescaped output = XSS risk) và so sánh với `{{ ... }}` (escaped).
    - DO: kiểm tra search controller: có limit complexity không (regex DoS, deep nested JSON).
    - DO: grep `->whereRaw`, `DB::raw`, `DB::statement` — SQL injection candidates.
    - DO: grep `eval(`, `assert(`, `preg_replace` với `/e` flag — RCE candidates (legacy PHP).
    - DO: grep `unserialize(`, `file_get_contents($_GET`, `file_get_contents($_POST`, `include($_GET` — file inclusion / object injection.
    - DO: kiểm tra search controller regex (nếu có), pagination `LIMIT ?` có bound param không.
    - DO NOT: exploit thật.
  Parallelization: Wave 2 | Blocked by: nothing | Blocks: 11
  References (executor has NO interview context - be exhaustive):
    - All files matching `app/Http/Controllers/**.php` (22 controllers)
    - All files matching `Modules/*/Http/Requests/**.php`
    - All files matching `Modules/*/Http/Controllers/**.php`
    - `app/Http/Controllers/SearchController.php`
    - All files matching `resources/views/**/*.blade.php` (check {!! usage)
    - `routes/web.php`, `routes/api.php` (full)
  Acceptance criteria (agent-executable):
    - Có `.omo/evidence/task-5-security-review.md`:
      a) Grep results cho các pattern nguy hiểm (file:line cho mỗi match)
      b) Bảng mỗi controller POST/PUT/DELETE: có FormRequest không, có validate inline không, có `request->all()` không
      c) Blade `{!! !!}` count: list file:line từng cái + đánh giá user-controlled hay constant
      d) SQL injection candidates: list file:line + đánh giá (raw query có bind param không)
      e) RCE candidates: list (thường sẽ là 0 trong Laravel hiện đại)
      f) File inclusion candidates: list
    - Findings: severity thường High (SQLi), Medium (XSS stored qua Blade unescaped), Low (DoS).
  QA scenarios:
    - Happy: tìm được ≥2 findings (ví dụ: 1 search query không sanitize dùng LIKE, 1 controller dùng request->all()).
    - Failure: nếu tất cả đều dùng FormRequest + escaped Blade + Eloquent binding, evidence ghi "no findings — verified N controllers, M FormRequests, X Blade {!! } (tất cả constant)".
  Commit: N

- [x] 6. C6 — Audit File upload (MIME bypass, path traversal, XXE, magic bytes) — COMPLETED (6 findings: 2 Medium, 3 Low, 1 Info)
  What to do / Must NOT do:
    - DO: đọc `Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php` (đã reference trong SECURITY.md).
    - DO: tìm tất cả file upload handler: `request->file(`, `->store(`, `->storeAs(`, `Storage::put(`, `Storage::putFile(`. Grep toàn project.
    - DO: với mỗi handler, kiểm tra:
      - MIME validation: dùng `mimes:` (extension-based) hay `mimetypes:` (MIME-content-based)? Extension check có bypass được bằng rename không?
      - Path traversal: filename có qua `basename()` không? Có prefix user-controlled không?
      - File size cap có không?
      - Stored location: có ngoài webroot không? Symlink?
      - Public access: có trả URL accessible không? Có check role trước khi download không?
    - DO: kiểm tra có upload XML/JSON/SVG không (SVG có thể chứa JS = stored XSS, XML có XXE).
    - DO: kiểm tra `MediaRecorder API` ở frontend (Speaking drills, Pronunciation) có validate audio MIME không.
    - DO NOT: upload file thật lên app.
  Parallelization: Wave 2 | Blocked by: nothing | Blocks: 11
  References (executor has NO interview context - be exhaustive):
    - `Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php`
    - `Modules/Classroom/Http/Controllers/*Controller.php` (upload handlers)
    - All files matching grep `->store\(|->storeAs\(|Storage::put|file\(`
    - `config/filesystems.php` (disk config)
    - `Modules/Speaking/`, `Modules/Pronunciation/` (audio upload path)
    - Speaking drills frontend (resources/views)
  Acceptance criteria (agent-executable):
    - Có `.omo/evidence/task-6-security-review.md`:
      a) Inventory tất cả upload handler (file:line + rules + storage path)
      b) Mỗi handler đánh giá: MIME check (extension vs content), size cap, path safety, public access control
      c) SVG/XML/JSON upload risks
      d) Existing FileUploadValidationTest có cover đủ không
    - Findings: severity thường High nếu upload .php rename .jpg thành công, Medium nếu SVG XSS qua rename.
  QA scenarios:
    - Happy: tìm được ≥1 finding về MIME bypass hoặc path traversal.
    - Failure: nếu tất cả upload đều dùng `mimetypes:` + `basename()` + storage ngoài webroot + role check, evidence ghi "no findings".
  Commit: N

- [x] 7. C7 — Audit Telegram & webhook (callback integrity, chat_id spoof, replay) — COMPLETED (5 findings: 2 Medium, 2 Low, 1 Info)
  What to do / Must NOT do:
    - DO: đọc `app/Http/Controllers/TelegramWebhookController.php` (đã đọc ở phase ground — secret verify ở middleware).
    - DO: đọc `Modules/TelegramBot/Http/Middleware/VerifyTelegramSecret.php`.
    - DO: đọc `Modules/TelegramBot/Services/TelegramBotCommandService.php` và `app/Services/TelegramService.php`.
    - DO: phân tích:
      - `dispatchAdminCallback` (line 102-114): có verify `chat_id === TELEGRAM_ADMIN_CHAT_ID` không? Hay chỉ tin `cb['from']['first_name']`?
      - Approve/reject user (line 116-184): có check user tồn tại + status hợp lệ không? Có rate limit không? Có thể replay nhiều lần không?
      - Learning bot commands (`handleCommand`, `handleFreeText`, `handleCallback`): có sanitize input không? Có SQL/XSS/Command injection không?
      - Log injection: `Log::info("[Telegram] Admin duyệt học viên #{$userId} ({$user->email})")` — newline trong email có phá log format không? (email có thể chứa \n qua spoof? Thường không nhưng cần check.)
      - `update_id` không được check → replay attack cùng update nhiều lần.
    - DO: check secret rotation policy (SECURITY.md có nhưng có enforce không?).
    - DO NOT: gửi request thật đến `/telegram/webhook`.
  Parallelization: Wave 2 | Blocked by: nothing | Blocks: 11
  References (executor has NO interview context - be exhaustive):
    - `app/Http/Controllers/TelegramWebhookController.php` (đã đọc)
    - `app/Http/Controllers/Api/TelegramWebhookController.php` (khác controller cho API?)
    - `Modules/TelegramBot/Http/Middleware/VerifyTelegramSecret.php`
    - `Modules/TelegramBot/Services/TelegramBotCommandService.php`
    - `app/Services/TelegramService.php`
    - `config/telegram.php` (nếu có)
    - `routes/web.php` line 21-23 (webhook route)
    - `routes/api.php` (API webhook, nếu có)
    - `tests/Feature/Security/TelegramWebhookSecurityTest.php`
    - `bootstrap/app.php` (middleware alias `telegram.secret`)
  Acceptance criteria (agent-executable):
    - Có `.omo/evidence/task-7-security-review.md`:
      a) Verify secret check dùng `hash_equals` đúng (không `!==`)
      b) Admin callback: phân tích chat_id spoof feasibility (attacker có thể giả callback với chat_id khác không? Hay middleware secret đã chặn trước?)
      c) Replay attack: phân tích update_id có được check không
      d) Command injection: list các command handler + input sanitization
      e) Log injection: các Log::info có user-controlled data không
      f) Existing TelegramWebhookSecurityTest có cover đủ cases không
    - Findings: severity thường High (privilege escalation nếu chat_id không check), Medium (replay).
  QA scenarios:
    - Happy: tìm được ≥1 finding (ví dụ: admin callback chỉ check secret mà không check chat_id — nếu attacker lấy được secret có thể giả admin, hoặc replay update).
    - Failure: nếu secret verify đúng + chat_id check + replay protection + sanitization, evidence ghi "no findings — secret timing-safe, chat_id verified, replay protected, commands sanitized".
  Commit: N

- [x] 8. C8 — Audit AI / Gemini integration (prompt injection, stored XSS, quota bypass) — COMPLETED (4 findings: 2 Medium, 2 Low)
  What to do / Must NOT do:
    - DO: đọc `app/Http/Controllers/AiTutorController.php`, `app/Http/Controllers/Api/AIChatController.php`.
    - DO: đọc `app/Services/GeminiService*` (tìm file).
    - DO: phân tích:
      - Prompt construction: user input có đưa thẳng vào prompt không? Có escape không? Có thể prompt-inject "ignore previous instructions" không?
      - Output rendering: Gemini response có render qua Blade `{!! !!}` không (stored XSS nếu user lưu câu trả lời)?
      - Quota: `LessonQuotaService` có check `is_unlimited` flag đúng cách không? User có thể tự set `is_unlimited` qua register/update không?
      - API key rotation: có atomic switch không, hay đang chạy song song có thể double-charge?
      - API key leakage: error message từ Gemini có thể leak prompt hay key không?
    - DO: grep `GEMINI_API_KEY` để xem có log không (redaction).
    - DO NOT: gửi request thật đến Gemini API.
  Parallelization: Wave 3 | Blocked by: nothing | Blocks: 11
  References (executor has NO interview context - be exhaustive):
    - `app/Http/Controllers/AiTutorController.php`
    - `app/Http/Controllers/Api/AIChatController.php`
    - `app/Services/GeminiService*` (find by glob)
    - `app/Services/LessonQuotaService.php`
    - `routes/web.php` line 25-41 (AI routes)
    - `.env` line 92 (GEMINI_API_KEY) — confirm secret NOT in working tree evidence
    - `config/gemini.php` (nếu có)
  Acceptance criteria (agent-executable):
    - Có `.omo/evidence/task-8-security-review.md`:
      a) 5 AI endpoints: trích prompt construction snippet
      b) Prompt injection test: ví dụ 2-3 payload mẫu có thể inject (lý thuyết, không gửi)
      c) Output render path: từ Gemini response → DB / View → render
      d) Quota bypass paths
      e) API key handling
    - Findings: severity thường Medium (prompt injection khó exploit trong context này), High nếu output lưu DB rồi render unsanitized.
  QA scenarios:
    - Happy: tìm được ≥1 finding (ví dụ: output Gemini render qua {!! } hoặc quota bypass qua fallback model).
    - Failure: nếu tất cả prompt escaped + output sanitized + quota robust, evidence ghi "no findings".
  Commit: N

- [x] 9. C9 — Audit Rate limit & DoS (pagination cap, search complexity, reverb auth) — COMPLETED (10+ findings: 4 High, 3 Medium, 3 Low, 1 Info)
  What to do / Must NOT do:
    - DO: đọc `bootstrap/app.php` (middleware registration) và `app/Providers/AppServiceProvider.php` (rate limiters).
    - DO: kiểm tra MỌI route có cần throttle không (đặc biệt AI endpoints, search, community, settings).
    - DO: kiểm tra pagination cap (CourseService::MAX_PER_PAGE=100 theo SECURITY.md — verify thật).
    - DO: grep `LIMIT ?`, `LIMIT $`, `paginate(`, `->limit(` để tìm unbounded query.
    - DO: kiểm tra Reverb (broadcast): có auth không? Có CORS cho WebSocket không? Có thể spam message không?
    - DO: kiểm tra global search: có rate limit không? Query có index không (slow query DoS)?
    - DO: kiểm tra file download endpoints: có throttle không?
    - DO NOT: gửi request spam thật.
  Parallelization: Wave 3 | Blocked by: nothing | Blocks: 11
  References (executor has NO interview context - be exhaustive):
    - `bootstrap/app.php`
    - `app/Providers/AppServiceProvider.php`
    - `app/Providers/RouteServiceProvider.php` (Laravel default)
    - `routes/web.php`, `routes/api.php`
    - `Modules/Course/Services/CourseService.php`
    - `app/Http/Controllers/SearchController.php`
    - `config/reverb.php` (nếu có)
    - `config/broadcasting.php`
    - `tests/Feature/Security/RateLimitTest.php`
  Acceptance criteria (agent-executable):
    - Có `.omo/evidence/task-9-security-review.md`:
      a) Bảng: route | method | middleware | throttle name | throttle limit
      b) Endpoint nào thiếu throttle (severity Medium/Low)
      c) Pagination cap analysis
      d) Search complexity (regex DoS, deep nested JSON)
      e) Reverb WebSocket auth
    - Findings: severity thường Low/Medium.
  QA scenarios:
    - Happy: tìm được ≥2 endpoints thiếu throttle hoặc pagination chưa cap.
    - Failure: nếu tất cả route có throttle + pagination cap + search indexed, evidence ghi "no findings".
  Commit: N

- [x] 10. C10 — Audit Logging & PII (log injection, PII leak, audit append-only, exception trace) — COMPLETED (7 findings: 2 High, 2 Medium, 3 Low)
  What to do / Must NOT do:
    - DO: grep `Log::info|Log::error|Log::warning|Log::debug` cho user-controlled data (email, name, chat_id, message text).
    - DO: grep `dd\(|var_dump\(|print_r\(` (debug code có thể còn sót).
    - DO: đọc `app/Services/AuditLogger.php` + `app/Models/AuditLog.php` + migration: verify append-only (UPDATED_AT null, no UPDATE policy).
    - DO: đọc `config/logging.php` + check log channel có redact sensitive không.
    - DO: phân tích exception handler: có thể leak stack trace + path + secret không? `APP_DEBUG=true` ở local = OK, `false` ở prod = OK, nhưng default handler có sanitize không?
    - DO: kiểm tra GDPR export endpoint: trả về data gì, có chứa password hash không, có ghi log access không?
    - DO NOT: gửi crafted input để inject log.
  Parallelization: Wave 3 | Blocked by: nothing | Blocks: 11
  References (executor has NO interview context - be exhaustive):
    - `app/Services/AuditLogger.php`
    - `app/Models/AuditLog.php`
    - `database/migrations/*create_audit_logs*.php` (2026_06_19_100000_create_audit_logs_table)
    - `config/logging.php`
    - `bootstrap/app.php` (exception handler)
    - `app/Http/Controllers/SettingsController.php` (export method)
    - All grep matches `Log::` in `app/`, `Modules/`
    - `tests/Feature/Security/AuditLogTest.php`
  Acceptance criteria (agent-executable):
    - Có `.omo/evidence/task-10-security-review.md`:
      a) Bảng Log:: calls có user-controlled data (file:line + biến + risk)
      b) AuditLog append-only verification
      c) Exception handler analysis
      d) GDPR export analysis
      e) Existing AuditLogTest coverage
    - Findings: severity thường Low (log injection khó exploit), Medium (PII leak trong logs).
  QA scenarios:
    - Happy: tìm được ≥1 finding (ví dụ: log email without redaction, hoặc GDPR export trả password hash).
    - Failure: nếu tất cả log sanitized + audit append-only + GDPR export filtered, evidence ghi "no findings".
  Commit: N

- [x] 11. Tổng hợp findings → `SECURITY_AUDIT.md` ở working tree — COMPLETED (52 findings total: 14 High, 23 Medium, 13 Low, 2 Info)
  What to do / Must NOT do:
    - DO: đọc TẤT CẢ `.omo/evidence/task-N-security-review.md` (N=1..10).
    - DO: gộp findings vào `SECURITY_AUDIT.md` theo template:
      1. **Executive Summary** (3-5 dòng): tổng số findings theo severity, top 3 risks, top 3 positive controls
      2. **Bảng Summary** (table): ID | Title | Severity | CWE | OWASP | Location
      3. **Findings chi tiết** (theo severity giảm dần: Critical → Info):
         - Mỗi finding có đầy đủ: Description, Evidence (code snippet + file:line), Impact, Remediation (kèm code diff cho High/Critical), References
      4. **Positive Findings** (controls đã tốt, khen ngợi)
      5. **Out-of-Scope / Future Hardening** (theo dõi từ SECURITY.md)
      6. **Appendix A**: Raw `composer audit` output (sanitized)
      7. **Appendix B**: Raw grep outputs (key patterns)
      8. **Appendix C**: Recommended secret rotation list (Telegram bot, Gemini, JWT, internal token) — chỉ tên biến + 4 char đầu/4 char cuối
    - DO: validate format — mỗi High/Critical có code diff ≥ 5 dòng (context + change).
    - DO: stage file ở working tree (git add không cần — chỉ cần file tồn tại).
    - DO: verify file không chứa full secret value (grep lại nội dung).
    - DO NOT: commit file.
    - DO NOT: tự thêm finding không có evidence.
    - DO NOT: skip component nào (kể cả "no findings" vẫn phải list).
  Parallelization: Wave 4 | Blocked by: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 | Blocks: F1, F2, F3, F4
  References (executor has NO interview context - be exhaustive):
    - `.omo/evidence/task-1-security-review.md`
    - `.omo/evidence/task-2-security-review.md`
    - ...
    - `.omo/evidence/task-10-security-review.md`
    - `SECURITY.md` (existing)
    - `SECURITY_UPGRADE_PLAN.md` (existing)
    - `.env` (chỉ đọc, không ghi nội dung vào report)
  Acceptance criteria (agent-executable):
    - File `SECURITY_AUDIT.md` tồn tại ở `C:\laragon\www\englishClass\SECURITY_AUDIT.md`
    - File KHÔNG được git add (kiểm tra bằng `git status --short SECURITY_AUDIT.md` → `??` ở đầu, không phải `A` hoặc `M`)
    - File không chứa full secret value (grep 5 forbidden substrings → không match — actual substrings omitted from plan per no-leak policy).
    - File chứa đủ sections theo template trên.
    - File chứa ≥ 1 finding tổng cộng (nếu tất cả 10 components đều "no findings" thì đó là audit kỹ, vẫn ghi summary).
  QA scenarios:
    - Happy: SECURITY_AUDIT.md tồn tại, có ≥1 finding, có Executive Summary, không leak secret.
    - Failure: nếu file không tồn tại → STOP và báo lỗi. Nếu có leak secret → redact và ghi warning.
  Commit: N (stage only)

## Final verification wave

> Runs in parallel after ALL todos. ALL must APPROVE. Surface results and wait for the user's explicit okay before declaring complete.

- [x] F1. Plan compliance audit — APPROVE (11/11 todos complete, 10 evidence files 24-42KB each, SECURITY_AUDIT.md 96KB untracked)
- [x] F2. Report quality review — APPROVE (52 findings SEC-001..SEC-052, 14 High có code diffs ≥5 lines, đủ sections A-H)
- [x] F3. Secret leak check — APPROVE (SECURITY_AUDIT.md + 10 evidence files: zero forbidden substrings; drafts/plans leaks đã redact ở bước fix)
- [x] F4. Scope fidelity — APPROVE (zero tracked file modifications; Modules/InternalManager/ + config/internal.php đã xóa; chỉ untracked .omo/ + SECURITY_AUDIT.md)

## Commit strategy

- KHÔNG commit bất kỳ file nào (audit là read-only).
- `SECURITY_AUDIT.md` ở working tree, untracked. Bạn review rồi tự commit nếu OK.

## Success criteria

- `SECURITY_AUDIT.md` tồn tại ở working tree, chưa commit.
- Mỗi finding có ID/Severity/CWE/OWASP/Location/Evidence/Remediation (+ diff cho High/Critical).
- Mỗi component có evidence file `.omo/evidence/task-N-security-review.md`.
- `.env` không bị edit.
- Không có full secret value trong SECURITY_AUDIT.md hoặc evidence files.
- Worker KHÔNG exploit thật lên bất kỳ môi trường nào.