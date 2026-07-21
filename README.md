# IELTS AI - Premium English Learning Platform

![IELTS AI Logo](https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg)

IELTS AI is a state-of-the-art English learning platform designed to help students master the IELTS exam through advanced artificial intelligence. It features a modular architecture and integrates deeply with Google's Gemini AI to provide real-time feedback, automated grading, and dynamic content generation.

## 🚀 Key Features

### 1. Smart Mock Test Center
- **Full Simulation**: Experience real IELTS testing conditions for Listening, Reading, Writing, and Speaking.
- **AI Grading**: Receive instant, detailed scores and improvement tips for your Writing and Speaking tasks.

### 2. Interactive Skill Drills
- **Speaking Drills**: Record your voice directly in the browser. Our AI analyzes your **pronunciation, fluency, and correctness** to provide targeted feedback.
- **Listening Drills**: High-quality audio is automatically generated for every question using advanced Text-to-Speech technology.
- **Reading & Writing**: Dynamic exercises that adapt to your skill level.

### 3. Advanced Flashcard System
- **Spaced Repetition**: Study smart with cards categorized by IELTS topics (Environment, Technology, Education, etc.).
- **Review Modes**: Self-evaluate with "Know It" and "Don't Know" buttons to focus on your weak points.
- **Personal Notebook**: Bookmark difficult words to your private vocabulary list for later review.

### 4. Gamified Experience
- **XP & Levels**: Earn experience points for every correct answer and drill completed.
- **Progress Tracking**: Visualize your journey toward your target band score.

### 5. Telegram Bot — Admin Approval
- **Instant Notification**: The moment a student registers, the admin receives a Telegram message with the student's name, email, and target band score.
- **One-tap Approval**: Approve or reject the student directly in Telegram via Inline Buttons — no need to log into the web dashboard.
- **Audit Trail**: Every action (approved/rejected, timestamp, admin name) is recorded in the message history and application logs.

## 🛠 Tech Stack

- **Backend**: Laravel 12 (PHP 8.4)
- **Database**: MySQL
- **AI Core**: Google Gemini 1.5 Flash (for content generation & analysis)
- **Frontend**: Blade, TailwindCSS, Vanilla JavaScript (MediaRecorder API), Vite
- **Architecture**: Modular Design (HMVC) using `nwidart/laravel-modules`
- **Real-time Notifications**: Telegram Bot API (native HTTP, no extra package)
- **Deployment**: Docker (multi-stage build)

## ⚙️ Installation & Setup (Local)

### Option 1: Run with Docker (recommended)

*Note: Our Docker setup uses an automated entrypoint. Database migrations and cache optimization run automatically when the container starts.*

1. **Copy environment file**:
   ```bash
   cp .env.docker .env
   ```

2. **Enable Local Development Mode** (Skip this for Production):
   To map your local code into the container for live editing, copy the override file:
   ```bash
   cp docker-compose.override.yml.example docker-compose.override.yml
   ```

3. **Build and start containers**:
   ```bash
   docker compose up -d --build
   ```

4. **Access the app**:
   - HTTP: `http://localhost:8080`
   - HTTPS: `https://localhost:8443`

5. **Database connection**:
   - MySQL host: `127.0.0.1`
   - MySQL port: `3307`
   - Redis port: `6379`

### Option 2: Local PHP setup

1. **Clone the repository**:
   ```bash
   git clone <repository-url>
   cd englishClass
   ```

2. **Install dependencies**:
   ```bash
   composer install
   npm install && npm run dev
   ```

3. **Configure environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Add your Gemini API Key in `.env`:
   ```env
   GEMINI_API_KEY=your_api_key_here
   ```

4. **Run migrations**:
   ```bash
   php artisan migrate --seed
   ```

5. **Start the app**:
   ```bash
   php artisan serve
   ```

## 🤖 Telegram Bot Setup

The platform integrates a Telegram Bot that notifies the admin when a new student registers and allows **one-tap approval directly from Telegram**.

### How it works

```
Student registers → StudentRegistered event fired
    → SendTelegramNotification listener
    → TelegramService sends message to Admin Chat
    → Admin taps [✅ Duyệt] or [❌ Từ chối]
    → Telegram sends callback to /telegram/webhook
    → User status updated (active / rejected)
    → Bot edits the message to confirm action
```

### Step 1 — Create a Telegram Bot

1. Open Telegram and search for **@BotFather**.
2. Send `/newbot` and follow the prompts to get your **Bot Token**.
3. Send any message to your new bot, then visit:
   ```
   https://api.telegram.org/bot{TOKEN}/getUpdates
   ```
4. Find `"chat":{"id": ...}` in the response — this is your **Admin Chat ID**.

### Step 2 — Add environment variables

```env
TELEGRAM_BOT_TOKEN=123456789:ABCDefgh...
TELEGRAM_ADMIN_CHAT_ID=987654321
TELEGRAM_WEBHOOK_SECRET=englishclass_webhook_secret
```

### Step 3 — Register the Webhook (once after deploy)

Replace `{TOKEN}` and `{YOUR_DOMAIN}` then open in browser or run with curl:

```bash
curl "https://api.telegram.org/bot{TOKEN}/setWebhook\
?url=https://{YOUR_DOMAIN}/telegram/webhook\
&secret_token=englishclass_webhook_secret"
```

Expected response:
```json
{"ok":true,"result":true,"description":"Webhook was set"}
```

### Gemini API rotation

Telegram lesson generation supports the same key/model rotation pattern used by
GymMap. Keys and fallback models are tried in order:

```env
GEMINI_API_KEY=key_one,key_two,key_three
GEMINI_MODEL=gemini-2.5-flash-lite
GEMINI_FALLBACK_MODELS=gemini-2.5-flash
```

`GEMINI_API_KEYS` can be used instead of `GEMINI_API_KEY` when an explicit
multi-key variable is preferred. After changing production environment values,
recreate the app containers or run `php artisan optimize:clear`.

Production must also seed Telegram learning topics after migrations:

```bash
php artisan db:seed --force --class='Modules\TelegramBot\Database\Seeders\TgbTopicSeeder'
```

The lesson service repairs missing user paths automatically once topics exist.

### Step 4 — Verify

Register a new student account on the platform. You should immediately receive a Telegram message like:

```
🎓 Học sinh mới đăng ký!

👤 Tên: Nguyen Van A
📧 Email: a@gmail.com
🎯 Target Band: 7.0
🕐 Thời gian: 30/04/2026 10:00

Vui lòng duyệt học viên này:
  [✅ Duyệt]  [❌ Từ chối]
```

---

## 📂 Project Structure

The application follows a modular structure located in the `Modules/` directory:
- `Modules/MockTest`: Full test logic and simulations.
- `Modules/Practice`: Individual skill drills and AI analysis.
- `Modules/Flashcard`: Spaced repetition system.
- `Modules/Speaking`: Voice recording and pronunciation service.
- `Modules/Question`: Central question management system.

## 📄 License

This project is licensed under the MIT License.

---

## 🆕 What's New — June 2026 Update

This release adds 26 features across four pillars. See
[SECURITY_UPGRADE_PLAN.md](SECURITY_UPGRADE_PLAN.md) for the
implementation roadmap and [SECURITY.md](SECURITY.md) for the
security policy.

### 🔒 Security Hardening (8 fixes)

- **Role-based authorization** — CourseRequest, CourseController, and
  Classroom upload now enforce admin/teacher role at both the
  FormRequest and controller layers. Students get a clean 403.
- **Rate limiting** — `throttle:5,1` on /login and `throttle:3,60` on
  /register (web + API). 20/min on AI endpoints. Hourly cap on lesson
  quota requests.
- **Audit logging** — Append-only `audit_logs` table + `AuditLogger`
  service. Every admin mutation (user approval, quota override,
  bulk operation) writes a row with actor, IP, user-agent, and
  metadata.
- **Telegram webhook hardening** — Empty secret now returns 503 in
  production instead of silently disabling verification. Removed
  duplicate timing-unsafe `!==` check from the controller.
- **Security headers** — X-Frame-Options DENY, X-Content-Type-Options
  nosniff, Referrer-Policy, Permissions-Policy, and a baseline CSP
  applied globally via `SecurityHeaders` middleware.
- **MIME validation** — Classroom uploads restricted to safe types
  (jpg, png, pdf, doc, zip, mp4...) with a 50 MB cap.
- **Pagination cap** — `CourseService::MAX_PER_PAGE = 100` prevents
  DoS via `?limit=99999999`.
- **Hygiene** — `.env.example` defaults `APP_DEBUG=false` and warns
  about APP_KEY rotation. Classroom invite codes bumped from 6 to
  10 chars. Telegram webhook logging no longer dumps full stack
  traces.

### 🎨 UX & Performance Polish (6 improvements)

- **Loading skeletons** — `<x-ui.skeleton>` component + animated
  shimmer CSS. Drops into any view where data is loading.
- **PWA + offline** — `manifest.json`, service worker with
  cache-first for static assets, network-first for HTML, and an
  `/offline` fallback page.
- **Dark mode** — Token-based theme system (`:root[data-theme]`).
  Toggle button cycles system → light → dark. Preference saved to
  `localStorage` and applied before first paint to avoid FOUC.
- **Accessibility** — Skip-to-content link, visible focus rings,
  ARIA live regions for screen reader announcements, focus trap for
  modals, Cmd/Ctrl+K to focus search, Cmd/Ctrl+J to open AI tutor.
- **Animations** — `window.celebrate()` for confetti on lesson
  completion. Respects `prefers-reduced-motion`.
- **Core Web Vitals** — Lazy-loaded images with explicit
  width/height to prevent CLS, prefetch hints for likely next
  routes.

### ✨ Student Features (9 new)

- **🤖 AI Tutor** — Floating chat widget (Cmd/Ctrl+J). Remembers the
  last 5 conversation turns. Three entry points: free-form ask,
  explain-a-wrong-answer, suggest-next-lesson. All rate-limited via
  the `ai` limiter.
- **📚 SRS Flashcard UI** — Web version of the Telegram bot's
  spaced-repetition system. Anki-style flow with keyboard shortcuts
  (Space = flip, 1-4 = grade). Confetti on deck completion.
- **⏱ Mock Test Timer** — Realistic IELTS countdown with
  `is-warning` and `is-critical` states. Auto-submits the form on
  timeout via a `mock-test:timeout` CustomEvent.
- **✍️ Writing Checker** — Live inline feedback as the student types.
  Flags repeated words, long sentences (>30 words), contractions
  (penalised in IELTS), and gives band-specific tips.
- **🎙 Pronunciation Shadowing** — Record + waveform visualisation
  + native-speaker comparison. Uses MediaRecorder API; degrades
  gracefully if unavailable.
- **📅 Study Planner** — Monthly calendar view with event creation,
  Pomodoro timer (25/5 min cycles), per-event types.
- **🎯 Daily Quests** — Gamification engine that awards XP when
  metrics (flashcards reviewed, lessons completed, etc.) hit
  configurable targets.
- **👥 Community** — Public study notes, polymorphic comments,
  buddy-matching at the same target band.
- **📊 Progress Analytics** — Per-skill radar chart, estimated
  band score, 30-day activity heatmap, top-5 weakest topics.

### 👨‍🏫 Teacher & Admin Features (7 new)

- **Teacher Dashboard** — Class list with at-risk students (>7 days
  inactive), recent submissions, high-level stats.
- **Assignment workflow** — Per-classroom assignments with rubric,
  submissions, and manual grading.
- **Bulk operations** — Bulk approve / role change / delete users,
  CSV import with row-level error reporting.
- **Question Bank AI tagging** — (Foundations in place; per-question
  tagging UI hooks exist via `QuestionController`.)
- **Class Analytics** — Cohort comparison, common mistakes across
  classes, per-topic time spent.
- **Content Authoring Tools** — Rich-text lesson editor scaffold
  with publish workflow (draft → review → published).
- **Student Support** — One-click messaging, canned responses,
  ticket-style notes attached to user records.

### 🌐 Cross-cutting (4 new)

- **🔍 Global Search (Cmd/Ctrl+K)** — Single search bar that hits
  courses, classrooms, public notes, and (admin-only) users.
- **🔔 Notification Center 2.0** — Per-category preferences,
  digest mode (realtime / daily / weekly / off), snooze support.
- **⚙️ Settings & Privacy** — Notification prefs, learning prefs,
  privacy toggles, locale switcher, GDPR/PDPA data export.
- **🌍 Multi-language** — `vi` / `en` UI dictionaries with the
  `ApplyUserLocale` middleware auto-switching on every request
  based on the user's saved preference.

### 🆕 Database changes

Run `php artisan migrate` to apply:

```
2026_06_18_100000_add_lesson_quota_to_users_table
2026_06_18_100100_create_lesson_requests_table
2026_06_19_100000_create_audit_logs_table
2026_06_19_120000_create_study_plans_table
2026_06_19_130000_create_quests_and_achievements
2026_06_19_140000_create_community_tables
2026_06_19_150000_create_user_preferences_table
```

### 🆕 New routes

```
POST   /ai/tutor              AI tutor free-form
POST   /ai/tutor/explain      Explain wrong answer
POST   /ai/tutor/suggest      Next-lesson recommendation

GET    /flashcards            SRS review UI
POST   /flashcards/{id}/grade Submit grade

GET    /study-plan            Calendar
POST   /study-plan            Create entry

GET    /quests                Daily quest list

GET    /community/notes       Public study notes
POST   /community/notes       Create note
POST   /community/comments    Add comment
GET    /community/find-buddy  Match a study buddy

GET    /analytics             Student progress dashboard
GET    /teacher/dashboard     Teacher overview

GET    /search                 Global search (Cmd+K)
GET    /settings/preferences  User settings
PUT    /settings/preferences  Save settings
GET    /settings/export       GDPR data export
```

### 🆕 Test coverage

Six new feature tests under `tests/Feature/Security/`:

- `AuthorizationTest` — Role-based access enforcement
- `RateLimitTest` — Throttle middleware returns 429 after limits
- `MassAssignmentTest` — Register cannot set `role` or `is_unlimited`
- `TelegramWebhookSecurityTest` — Secret check + 503 on prod
- `AuditLogTest` — Audit rows written for sensitive actions
- `FileUploadValidationTest` — MIME types enforced

Run with:

```bash
php artisan test --testsuite=Feature --filter=Security
```
