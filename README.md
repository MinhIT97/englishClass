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
- **Deployment**: Docker (multi-stage build) + Cloudflare Tunnel

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

> **Note:** The app uses Cloudflare Tunnel to expose the server over HTTPS — Telegram requires HTTPS for webhooks. No extra configuration needed.

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
