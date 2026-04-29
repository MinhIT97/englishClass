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

## 🛠 Tech Stack

- **Backend**: Laravel 12 (PHP 8.4)
- **Database**: MySQL
- **AI Core**: Google Gemini 1.5 Flash (for content generation & analysis)
- **Frontend**: Blade, TailwindCSS, Vanilla JavaScript (MediaRecorder API), Vite
- **Architecture**: Modular Design (HMVC) using `nwidart/laravel-modules`

## ⚙️ Installation & Setup

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

## 📂 Project Structure

The application follows a modular structure located in the `Modules/` directory:
- `Modules/MockTest`: Full test logic and simulations.
- `Modules/Practice`: Individual skill drills and AI analysis.
- `Modules/Flashcard`: Spaced repetition system.
- `Modules/Speaking`: Voice recording and pronunciation service.
- `Modules/Question`: Central question management system.

## 📄 License

This project is licensed under the MIT License.
