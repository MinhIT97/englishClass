<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    
    <!-- Reverb Config -->
    <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
    <meta name="reverb-host" content="{{ config('broadcasting.connections.reverb.options.host') == 'reverb' ? '' : config('broadcasting.connections.reverb.options.host') }}">
    <meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.options.port') }}">
    <meta name="reverb-scheme" content="{{ config('broadcasting.connections.reverb.options.scheme') }}">
    {{ $head ?? '' }}

    <title>{{ config('app.name', 'IELTS Mastery') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@600;700&display=swap" rel="stylesheet">

    <!-- Styles & Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <!-- Background Orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="layout-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-wrapper">
                    <h2>IELTS <span class="text-primary-glow">AI</span></h2>
                    <div class="logo-glow"></div>
                </div>
                
                <!-- Quick User Stats (Reference Style) -->
                <div class="sidebar-user-stats animate-fade-in" style="animation-delay: 0.1s">
                    <div class="stat-card glass">
                        <div class="stat-label">{{ __('ui.welcome_back') }},</div>
                        <div class="stat-value">{{ auth()->user()->name }}</div>
                        <div class="stat-meta">
                            <span class="meta-item">🔥 {{ __('ui.day_streak', ['days' => auth()->user()->streak ?? 0]) }}</span>
                            <span class="meta-item">⚡ {{ auth()->user()->xp ?? 0 }} XP</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                @if(auth()->user()->role === 'admin')
                    <a href="/admin/dashboard" class="nav-item {{ request()->is('admin/dashboard') ? 'active' : '' }}">
                        <span class="nav-icon">📊</span> {{ __('ui.dashboard') }}
                    </a>
                    <a href="/admin/users" class="nav-item {{ request()->is('admin/users') ? 'active' : '' }}">
                        <span class="nav-icon">👥</span> {{ __('ui.users_approval') }}
                    </a>
                    <a href="/admin/questions" class="nav-item {{ request()->is('admin/questions') ? 'active' : '' }}">
                        <span class="nav-icon">📝</span> {{ __('ui.question_bank') }}
                    </a>
                    <a href="/classroom" class="nav-item {{ request()->is('classroom*') ? 'active' : '' }}">
                        <span class="nav-icon">🏫</span> {{ __('ui.classrooms') }}
                    </a>
                    <a href="/courses" class="nav-item {{ request()->is('courses*') ? 'active' : '' }}">
                        <span class="nav-icon">📚</span> {{ __('ui.courses') }}
                    </a>
                @else
                    <a href="/student/dashboard" class="nav-item {{ request()->is('student/dashboard') ? 'active' : '' }}">
                        <span class="nav-icon">🏠</span> {{ __('ui.dashboard') }}
                    </a>
                    <a href="/classroom" class="nav-item {{ request()->is('classroom*') ? 'active' : '' }}">
                        <span class="nav-icon">🏫</span> {{ __('ui.classrooms') }}
                    </a>
                    <a href="/student/practice" class="nav-item {{ request()->is('student/practice') ? 'active' : '' }}">
                        <span class="nav-icon">⚡</span> {{ __('ui.practice') }}
                    </a>
                    <a href="/student/writing" class="nav-item {{ request()->is('student/writing*') ? 'active' : '' }}">
                        <span class="nav-icon">✍️</span> {{ __('ui.writing') }}
                    </a>
                    <a href="/student/speaking" class="nav-item {{ request()->is('student/speaking*') ? 'active' : '' }}">
                        <span class="nav-icon">🗣️</span> {{ __('ui.speaking') }}
                    </a>
                    <a href="/student/flashcards" class="nav-item {{ request()->is('student/flashcards*') ? 'active' : '' }}">
                        <span class="nav-icon">🗂️</span> {{ __('ui.flashcards') }}
                    </a>
                    <a href="/student/test" class="nav-item {{ request()->is('student/test') ? 'active' : '' }}">
                        <span class="nav-icon">🏆</span> {{ __('ui.mock_tests') }}
                    </a>
                    <a href="/student/leaderboard" class="nav-item {{ request()->is('student/leaderboard') ? 'active' : '' }}">
                        <span class="nav-icon">🏅</span> {{ __('ui.leaderboard') }}
                    </a>
                    <a href="/courses" class="nav-item {{ request()->is('courses*') ? 'active' : '' }}">
                        <span class="nav-icon">📚</span> {{ __('ui.courses') }}
                    </a>
                @endif
                
                <a href="/settings" class="nav-item {{ request()->is('settings') ? 'active' : '' }}">
                    <span class="nav-icon">⚙️</span> {{ __('ui.settings') }}
                </a>

                <div style="margin-top: 2rem; padding: 0 1rem;">
                    @if($ai_live)
                        <div style="font-size: 0.7rem; display: flex; align-items: center; gap: 0.5rem; color: #10b981; padding: 0.5rem; background: rgba(16, 185, 129, 0.1); border-radius: 8px;">
                            <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block;"></span>
                            AI CORE: LIVE MODE
                        </div>
                    @else
                        <div style="font-size: 0.7rem; display: flex; align-items: center; gap: 0.5rem; color: #f59e0b; padding: 0.5rem; background: rgba(245, 158, 11, 0.1); border-radius: 8px;">
                            <span style="width: 8px; height: 8px; background: #f59e0b; border-radius: 50%; display: inline-block;"></span>
                            AI CORE: MOCK MODE
                        </div>
                    @endif
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <form method="POST" action="/logout">
                    @csrf
                    <button class="btn btn-outline btn-logout" style="width: 100%">
                        🚪 {{ __('ui.logout') }}
                    </button>
                </form>
            </div>
        </aside>

        <!-- Mobile Overlay Backdrop -->
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-nav">
                <!-- Hamburger (mobile only) -->
                <button class="hamburger-btn" id="hamburger-btn" aria-label="Open menu">☰</button>
                <div style="display: flex; align-items: center; gap: 1.5rem">
                    <!-- Language Switcher -->
                    <div style="display: flex; gap: 0.5rem">
                        <a href="{{ route('set.locale', 'vi') }}" class="glass {{ app()->getLocale() === 'vi' ? 'active-lang' : '' }}" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; border-radius: 50%; color: var(--text-main); text-decoration: none">
                            VI
                        </a>
                        <a href="{{ route('set.locale', 'en') }}" class="glass {{ app()->getLocale() === 'en' ? 'active-lang' : '' }}" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; border-radius: 50%; color: var(--text-main); text-decoration: none">
                            EN
                        </a>
                    </div>

                    <!-- Theme Toggle -->
                    <button id="theme-toggle" class="glass" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; cursor: pointer; border-radius: 50%">
                        🌙
                    </button>

                    <!-- Notification Bell -->
                    <div style="position: relative" id="notification-wrapper">
                        <button id="notification-btn" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-main); position: relative; display: flex; align-items: center">
                            🔔
                            <span id="notification-badge">0</span>
                        </button>

                        <div class="notification-dropdown" id="notification-dropdown">
                            <div class="notification-header">
                                <h4 style="margin: 0; font-size: 1rem; font-weight: 700">Notifications</h4>
                                <button id="mark-read-btn" style="background: none; border: none; color: var(--primary); font-size: 0.75rem; cursor: pointer">Mark all as read</button>
                            </div>
                            <div class="notification-list" id="notification-list">
                                <!-- Notifications will be injected here -->
                                <div style="padding: 2rem; text-align: center; color: var(--text-muted)">
                                    No new notifications
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="user-pill">
                        <span style="margin-right: 0.5rem">👤</span>
                        <span class="user-name">{{ auth()->user()->name }}</span>
                        <span class="badge {{ auth()->user()->status === 'active' ? 'badge-active' : 'badge-pending' }}" style="margin-left: 0.5rem">
                            {{ auth()->user()->status }}
                        </span>
                    </div>
                </div>
            </header>
            
            <div class="content-body">
                @if(session('success'))
                    <div class="glass-card" style="padding: 1rem; margin-bottom: 1.5rem; border-color: #10b981; color: #10b981">
                        ✅ {{ session('success') }}
                    </div>
                @endif

                @if(session('info'))
                    <div class="glass-card" style="padding: 1rem; margin-bottom: 1.5rem; border-color: var(--primary); color: var(--primary)">
                        ℹ️ {{ session('info') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="glass-card" style="padding: 1rem; margin-bottom: 1.5rem; border-color: #ef4444; color: #ef4444">
                        ⚠️ {{ session('error') }}
                    </div>
                @endif

                {{ $slot }}
            </div>
        </main>
    <!-- Theme Toggle & Hamburger JS -->
    <script>
        (function() {
            // Theme Toggle Logic
            const themeToggle = document.getElementById('theme-toggle');
            const body = document.body;
            
            // Check for saved theme
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'light') {
                body.classList.add('light-mode');
                themeToggle.innerText = '☀️';
            }

            themeToggle.addEventListener('click', () => {
                const isLight = body.classList.toggle('light-mode');
                localStorage.setItem('theme', isLight ? 'light' : 'dark');
                themeToggle.innerText = isLight ? '☀️' : '🌙';
            });

            // Hamburger Menu Logic
            const hamburger = document.getElementById('hamburger-btn');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            function openSidebar() {
                sidebar.classList.add('mobile-open');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }

            if (hamburger) hamburger.addEventListener('click', openSidebar);
            if (overlay) overlay.addEventListener('click', closeSidebar);

            // Close sidebar on nav-item click (mobile)
            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', () => {
                    if (window.innerWidth <= 768) closeSidebar();
                });
            });
        })();
    </script>
</body>
</html>
