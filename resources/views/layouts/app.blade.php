<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    {{ $head ?? '' }}

    <title>{{ config('app.name', 'IELTS Mastery') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@600;700&display=swap" rel="stylesheet">

    <!-- Styles & Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        .layout-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* ===== Sidebar ===== */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-secondary);
            border-right: 1px solid var(--glass-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 200;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 2rem;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            color: var(--text-main);
        }

        .sidebar-nav {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.25rem;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: var(--radius);
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
        }

        .nav-item:hover, .nav-item.active {
            background: var(--glass);
            color: var(--primary);
        }

        .nav-icon {
            margin-right: 1rem;
            width: 20px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--glass-border);
            flex-shrink: 0;
        }

        /* ===== Mobile overlay backdrop ===== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 150;
            backdrop-filter: blur(4px);
        }
        .sidebar-overlay.active { display: block; }

        /* ===== Hamburger Button ===== */
        .hamburger-btn {
            display: none;
            background: none;
            border: 1px solid var(--glass-border);
            color: var(--text-main);
            border-radius: 8px;
            padding: 0.4rem 0.6rem;
            font-size: 1.2rem;
            cursor: pointer;
            margin-right: auto;
            transition: background 0.2s;
        }
        .hamburger-btn:hover { background: var(--glass); }

        /* ===== Main Content ===== */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .top-nav {
            height: var(--header-height);
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 90;
            gap: 1rem;
        }

        .user-pill {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            font-size: 0.875rem;
            color: var(--text-main);
        }

        .content-body {
            padding: 2.5rem;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }

        /* ===== RESPONSIVE BREAKPOINTS ===== */
        @media (max-width: 1024px) {
            :root { --sidebar-width: 240px; }
            .content-body { padding: 2rem 1.5rem; }
        }

        @media (max-width: 768px) {
            /* Sidebar hidden off-screen by default on mobile */
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            /* Main content takes full width */
            .main-content {
                margin-left: 0;
            }
            /* Show hamburger */
            .hamburger-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            /* Content padding adjustments */
            .content-body {
                padding: 1.25rem 1rem;
            }
            /* Top nav: compact */
            .top-nav {
                padding: 0 1rem;
                justify-content: flex-start;
            }
            /* User pill: collapse name on mobile */
            .user-pill .user-name { display: none; }
            /* Glass card responsive */
            .glass-card { padding: 1.25rem; }
            /* Notification dropdown full width */
            .notification-dropdown {
                width: calc(100vw - 2rem);
                right: auto;
                left: 1rem;
            }
        }

        @media (max-width: 480px) {
            h1 { font-size: 1.5rem !important; }
            h2 { font-size: 1.25rem !important; }
        }
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-pending { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .badge-active { background: rgba(16, 185, 129, 0.2); color: #10b981; }

        /* Notifications */
        .notification-dropdown {
            position: absolute;
            top: calc(var(--header-height) - 10px);
            right: 0;
            width: 360px;
            max-height: 480px;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 1000;
        }

        .notification-dropdown.active {
            display: flex;
        }

        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-list {
            overflow-y: auto;
            flex: 1;
        }

        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            gap: 1rem;
            transition: background 0.2s;
            text-decoration: none;
            color: inherit;
        }

        .notification-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .notification-item.unread {
            background: rgba(59, 130, 246, 0.1);
        }

        #notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            padding: 0 4px;
            border-radius: 10px;
            min-width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #0f172a;
            display: none;
        }
    </style>
</head>
<body>
    <div class="layout-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>IELTS <span style="color: var(--primary)">AI</span></h2>
            </div>
            
            <nav class="sidebar-nav">
                @if(auth()->user()->role === 'admin')
                    <a href="/admin/dashboard" class="nav-item {{ request()->is('admin/dashboard') ? 'active' : '' }}">
                        <span class="nav-icon">📊</span> Dashboard
                    </a>
                    <a href="/admin/users" class="nav-item {{ request()->is('admin/users') ? 'active' : '' }}">
                        <span class="nav-icon">👥</span> Users Approval
                    </a>
                    <a href="/admin/questions" class="nav-item {{ request()->is('admin/questions') ? 'active' : '' }}">
                        <span class="nav-icon">📝</span> Question Bank
                    </a>
                    <a href="/classroom" class="nav-item {{ request()->is('classroom*') ? 'active' : '' }}">
                        <span class="nav-icon">🏫</span> Classrooms
                    </a>
                    <a href="/courses" class="nav-item {{ request()->is('courses*') ? 'active' : '' }}">
                        <span class="nav-icon">📚</span> Courses
                    </a>
                @else
                    <a href="/student/dashboard" class="nav-item {{ request()->is('student/dashboard') ? 'active' : '' }}">
                        <span class="nav-icon">🏠</span> Home
                    </a>
                    <a href="/classroom" class="nav-item {{ request()->is('classroom*') ? 'active' : '' }}">
                        <span class="nav-icon">🏫</span> Classrooms
                    </a>
                    <a href="/student/practice" class="nav-item {{ request()->is('student/practice') ? 'active' : '' }}">
                        <span class="nav-icon">⚡</span> Practice Mode
                    </a>
                    <a href="/student/writing" class="nav-item {{ request()->is('student/writing*') ? 'active' : '' }}">
                        <span class="nav-icon">✍️</span> Writing AI
                    </a>
                    <a href="/student/speaking" class="nav-item {{ request()->is('student/speaking*') ? 'active' : '' }}">
                        <span class="nav-icon">🗣️</span> Speaking AI
                    </a>
                    <a href="/student/flashcards" class="nav-item {{ request()->is('student/flashcards*') ? 'active' : '' }}">
                        <span class="nav-icon">🗂️</span> Flashcards
                    </a>
                    <a href="/student/test" class="nav-item {{ request()->is('student/test') ? 'active' : '' }}">
                        <span class="nav-icon">🏆</span> Mock Tests
                    </a>
                    <a href="/student/leaderboard" class="nav-item {{ request()->is('student/leaderboard') ? 'active' : '' }}">
                        <span class="nav-icon">🏅</span> Leaderboard
                    </a>
                    <a href="/courses" class="nav-item {{ request()->is('courses*') ? 'active' : '' }}">
                        <span class="nav-icon">📚</span> Courses
                    </a>
                @endif
                
                <a href="/settings" class="nav-item {{ request()->is('settings') ? 'active' : '' }}">
                    <span class="nav-icon">⚙️</span> Settings
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
                    <button class="btn btn-outline" style="width: 100%; border-color: rgba(239, 68, 68, 0.3); color: #ef4444;">
                        🚪 Logout
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
                {{ $slot }}
            </div>
        </main>
    <!-- Hamburger Menu JS -->
    <script>
        (function() {
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
