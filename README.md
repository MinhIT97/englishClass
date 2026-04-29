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

- **Backend**: Laravel 11 (PHP)
- **Database**: MySQL
- **AI Core**: Google Gemini 1.5 Flash (for content generation & analysis)
- **Frontend**: Blade, Vanilla JavaScript (MediaRecorder API), CSS (Glassmorphism design)
- **Architecture**: Modular Design (HMVC) using `nwidart/laravel-modules`

## ⚙️ Installation & Setup (Local)

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

3. **Configure Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   **Crucial**: Add your Gemini API Key in `.env`:
   ```env
   GEMINI_API_KEY=your_api_key_here
   ```

4. **Run Migrations & Seeders**:
   ```bash
   php artisan migrate --seed
   ```

5. **Start the server**:
   ```bash
   php artisan serve
   ```

## 🐳 Docker Setup (Recommended)

1. **Start the containers** (runs in background):
   ```bash
   docker compose up -d
   ```

2. **Install dependencies**:
   ```bash
   docker-compose exec app composer install
   docker-compose exec app npm install;
   docker-compose exec app npm run build
   ```

3. **Configure Environment**:
   ```bash
   cp .env.example .env
   docker-compose exec app php artisan key:generate
   ```
   *Make sure `DB_HOST=db` and `REDIS_HOST=redis` are set in your `.env`.*
   *Also ensure your `GEMINI_API_KEY` is added.*

4. **Run Migrations & Seeders**:
   ```bash
   docker-compose exec app php artisan migrate --seed
   ```

5. **Access the application**:
   Open [http://localhost:8000](http://localhost:8000) in your browser.

## 📂 Project Structure

The application follows a modular structure located in the `Modules/` directory:
- `Modules/MockTest`: Full test logic and simulations.
- `Modules/Practice`: Individual skill drills and AI analysis.
- `Modules/Flashcard`: Spaced repetition system.
- `Modules/Speaking`: Voice recording and pronunciation service.
- `Modules/Question`: Central question management system.

## 📄 License

This project is licensed under the MIT License.
