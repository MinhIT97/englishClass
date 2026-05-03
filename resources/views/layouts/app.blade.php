<!DOCTYPE html><!-- VERSION_FEEDBACK_FIX_FORCE_DARK -->
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

    <title>{{ $title ?? config('app.name', 'IELTS Mastery') }}</title>
    <meta name="description" content="{{ $meta_description ?? 'Nền tảng luyện thi IELTS thông minh với AI. Chấm bài Writing, luyện Speaking 24/7 và hệ thống đề thi sát thực tế.' }}">
    <link rel="canonical" href="{{ url()->current() }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $title ?? config('app.name', 'IELTS Mastery') }}">
    <meta property="og:description" content="{{ $meta_description ?? 'Nền tảng luyện thi IELTS thông minh với AI. Chấm bài Writing, luyện Speaking 24/7 và hệ thống đề thi sát thực tế.' }}">
    <meta property="og:image" content="{{ asset('images/og-image.png') }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:title" content="{{ $title ?? config('app.name', 'IELTS Mastery') }}">
    <meta property="twitter:description" content="{{ $meta_description ?? 'Nền tảng luyện thi IELTS thông minh với AI. Chấm bài Writing, luyện Speaking 24/7 và hệ thống đề thi sát thực tế.' }}">
    <meta property="twitter:image" content="{{ asset('images/og-image.png') }}">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('images/favicon_io/favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicon_io/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon_io/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon_io/favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('images/favicon_io/site.webmanifest') }}">


    <!-- Structured Data -->
    <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "Organization",
            "name": "IELTS AI",
            "url": "{{ url('/') }}",
            "logo": "{{ asset('images/logo.png') }}",
            "sameAs": [
                "https://facebook.com/ieltsai",
                "https://twitter.com/ieltsai"
            ]
        }
    </script>
    <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "WebSite",
            "url": "{{ url('/') }}",
            "potentialAction": {
                "@@type": "SearchAction",
                "target": "{{ url('/') }}/search?q={search_term_string}",
                "query-input": "required name=search_term_string"
            }
        }
    </script>


    <!-- Google Analytics (GA4) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-HNJ2GMR1T8"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', 'G-HNJ2GMR1T8');
    </script>


    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@600;700&display=swap" rel="stylesheet">

    <!-- Styles & Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Inline Fix for Server-side CSS Cache/Build Issues -->
    <style>
        .feedback-select, .feedback-input, .feedback-textarea {
            background: rgba(15, 23, 42, 0.9) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
        }
        .feedback-select option {
            background: #0f172a !important;
            color: white !important;
        }
        .feedback-input:-webkit-autofill {
            -webkit-text-fill-color: white !important;
            -webkit-box-shadow: 0 0 0px 1000px rgba(15, 23, 42, 0.9) inset !important;
        }
    </style>
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

                <!-- Quick User Stats -->
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
                    <a href="{{ route('admin.feedback.index') }}" class="nav-item {{ request()->is('admin/feedback*') ? 'active' : '' }}">
                        <span class="nav-icon">💬</span> Feedback
                        @if(isset($pendingFeedbackCount) && $pendingFeedbackCount > 0)
                            <span class="badge" style="background: var(--primary); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; margin-left: auto; font-weight: 700;">{{ $pendingFeedbackCount }}</span>
                        @endif
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
                <button class="btn btn-outline" id="feedback-trigger" style="border-color: var(--accent); color: var(--accent)">
                    <span>💬</span> {{ __('ui.feedback_title') }}
                </button>
                <form method="POST" action="/logout" style="width: 100%">
                    @csrf
                    <button class="btn btn-outline btn-logout">
                        <span>🚪</span> {{ __('ui.logout') }}
                    </button>
                </form>
            </div>
        </aside>

        <!-- Mobile Overlay Backdrop -->
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-nav">
                <button class="hamburger-btn" id="hamburger-btn" aria-label="Open menu">☰</button>
                <div class="top-nav-actions">
                    <!-- Language Switcher -->
                    <div class="top-nav-lang">
                        <a href="{{ route('set.locale', 'vi') }}" class="glass top-nav-chip {{ app()->getLocale() === 'vi' ? 'active-lang' : '' }}">
                            VI
                        </a>
                        <a href="{{ route('set.locale', 'en') }}" class="glass top-nav-chip {{ app()->getLocale() === 'en' ? 'active-lang' : '' }}">
                            EN
                        </a>
                    </div>

                    <!-- Theme Toggle -->
                    <button id="theme-toggle" class="glass top-nav-chip top-nav-icon-btn">
                        🌙
                    </button>

                    <!-- Notification Bell -->
                    <div class="notification-wrapper" id="notification-wrapper">
                        <button id="notification-btn" class="top-nav-icon-btn notification-btn">
                            🔔
                            <span id="notification-badge">0</span>
                        </button>

                        <div class="notification-dropdown" id="notification-dropdown">
                            <div class="notification-header">
                                <h4 style="margin: 0; font-size: 1rem; font-weight: 700">Notifications</h4>
                                <button id="mark-read-btn" style="background: none; border: none; color: var(--primary); font-size: 0.75rem; cursor: pointer">Mark all as read</button>
                            </div>
                            <div class="notification-list" id="notification-list">
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

<!-- Feedback Modal -->
<div class="feedback-modal" id="feedback-modal" role="dialog" aria-modal="true" aria-labelledby="feedback-title">
    <div class="feedback-content glass animate-fade-in" style="padding: 2.5rem; border-radius: 24px;">
        <button class="feedback-close" id="feedback-close" type="button" aria-label="{{ __('ui.close') }}">×</button>

        <div id="feedback-form-panel">
            <h3 id="feedback-title" style="margin-bottom: 0.5rem; font-size: 1.5rem">{{ __('ui.feedback_title') }}</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 2rem">{{ __('ui.feedback_desc') }}</p>

            <form id="feedback-form">
                <div class="form-group">
                    <label class="feedback-label">{{ __('ui.feedback_type') }}</label>
                    <select id="feedback-type" name="feedback_type" required class="feedback-select" style="background: rgba(15, 23, 42, 0.9) !important; color: white !important; border: 1px solid rgba(255, 255, 255, 0.1) !important;">
                        <option value="" style="background: #0f172a; color: white;">{{ __('ui.feedback_type_placeholder') }}</option>
                        <option value="bug" style="background: #0f172a; color: white;">{{ __('ui.cat_bug') }}</option>
                        <option value="ux" style="background: #0f172a; color: white;">{{ __('ui.cat_ux') }}</option>
                        <option value="feature" style="background: #0f172a; color: white;">{{ __('ui.cat_feature') }}</option>
                        <option value="other" style="background: #0f172a; color: white;">{{ __('ui.cat_other') }}</option>
                    </select>

                </div>

                <div class="form-group">
                    <label class="feedback-label">{{ __('ui.feedback_rating') }}</label>
                    <div class="rating-grid">
                        @foreach([1, 2, 3, 4, 5] as $rating)
                            <label class="rating-pill" data-rating="{{ $rating }}">
                                <input type="radio" name="rating" value="{{ $rating }}" required style="display: none">
                                <span>{{ $rating }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="form-group">
                    <label class="feedback-label">{{ __('ui.feedback_message') }}</label>
                    <textarea id="feedback-message" name="message" required placeholder="{{ __('ui.feedback_placeholder') }}" class="feedback-textarea" style="background: rgba(15, 23, 42, 0.9) !important; color: white !important; border: 1px solid rgba(255, 255, 255, 0.1) !important;"></textarea>
                </div>

                <div class="form-group">
                    <label class="feedback-label">{{ __('ui.feedback_email') }}</label>
                    <input id="feedback-email" type="email" name="email" placeholder="name@example.com" class="feedback-input" style="background: rgba(15, 23, 42, 0.9) !important; color: white !important; border: 1px solid rgba(255, 255, 255, 0.1) !important;">
                </div>


                <p id="feedback-error" style="display: none; color: #ef4444; font-size: 0.8rem; margin-bottom: 1rem;"></p>

                <button type="submit" class="feedback-submit-btn">
                    🚀 {{ __('ui.submit_feedback') }}
                </button>
            </form>
        </div>

        <div id="feedback-success-panel" style="display: none; text-align: center; padding: 2rem 0;">
            <div style="font-size: 4rem; margin-bottom: 1.5rem">🎉</div>
            <h3 style="margin-bottom: 0.5rem">{{ __('ui.feedback_success') }}</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem">{{ __('ui.feedback_success_desc') }}</p>
            <button type="button" id="feedback-success-close" class="btn btn-outline" style="min-width: 120px">
                {{ __('ui.close') }}
            </button>
        </div>
    </div>
</div>



        <!-- Floating Chat Widget -->
        <div class="chat-widget-trigger" id="chat-trigger" title="Hỗ trợ học tập">
            <span style="font-size: 1.5rem">🤖</span>
        </div>

        <div class="chat-panel" id="chat-panel">
            <div class="chat-header">
                <h3>{{ __('ui.learning_assistant') }}</h3>
                <button class="chat-close" id="chat-close">×</button>
            </div>
            <div class="chat-messages" id="chat-messages">
                <div class="chat-bubble ai">
                    {{ __('ui.chat_welcome') }}
                </div>
            </div>
            <div class="chat-suggestions">
                <button class="suggestion-pill">{{ __('ui.chat_suggestion_1') }}</button>
                <button class="suggestion-pill">{{ __('ui.chat_suggestion_2') }}</button>
                <button class="suggestion-pill">{{ __('ui.chat_suggestion_3') }}</button>
            </div>
            <div class="chat-input-container">
                <input type="text" class="chat-input" placeholder="{{ __('ui.chat_placeholder') }}" id="chat-input">
                <button class="chat-send" id="chat-send">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        </div>

        <script>
            (function() {
                const themeToggle = document.getElementById('theme-toggle');
                const body = document.body;
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

                document.querySelectorAll('.nav-item').forEach(item => {
                    item.addEventListener('click', () => {
                        if (window.innerWidth <= 768) closeSidebar();
                    });
                });

                const feedbackModal = document.getElementById('feedback-modal');
                const feedbackTrigger = document.getElementById('feedback-trigger');
                const feedbackClose = document.getElementById('feedback-close');
                const feedbackForm = document.getElementById('feedback-form');
                const feedbackError = document.getElementById('feedback-error');
                const feedbackFormPanel = document.getElementById('feedback-form-panel');
                const feedbackSuccessPanel = document.getElementById('feedback-success-panel');
                const feedbackSuccessClose = document.getElementById('feedback-success-close');

                const closeFeedbackModal = () => {
                    feedbackModal.classList.remove('active');
                };

                const openFeedbackModal = () => {
                    feedbackModal.classList.add('active');
                    feedbackFormPanel.style.display = 'block';
                    feedbackSuccessPanel.style.display = 'none';
                    feedbackError.style.display = 'none';
                    feedbackError.textContent = '';
                };

                if (feedbackTrigger) {
                    feedbackTrigger.addEventListener('click', openFeedbackModal);
                }
                if (feedbackClose) {
                    feedbackClose.addEventListener('click', closeFeedbackModal);
                }
                if (feedbackSuccessClose) {
                    feedbackSuccessClose.addEventListener('click', closeFeedbackModal);
                }

                // Rating Logic - Improved
                document.querySelectorAll('.rating-pill').forEach(pill => {
                    pill.addEventListener('click', () => {
                        document.querySelectorAll('.rating-pill').forEach(p => p.classList.remove('active'));
                        pill.classList.add('active');
                        pill.querySelector('input').checked = true;
                    });
                });

                // Submission Mock
                if (feedbackForm) {
                    feedbackForm.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const submitBtn = feedbackForm.querySelector('button[type="submit"]');
                        
                        feedbackError.style.display = 'none';
                        feedbackError.textContent = '';

                        submitBtn.disabled = true;
                        const defaultLabel = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<div class="mini-spinner"></div>';


                        try {
                            const response = await fetch('{{ route('feedback.store') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify(Object.fromEntries(new FormData(feedbackForm)))
                            });

                            if (!response.ok) throw new Error('Network response was not ok');

                            feedbackForm.reset();
                            document.querySelectorAll('.rating-pill').forEach(p => p.classList.remove('active'));
                            feedbackFormPanel.style.display = 'none';
                            feedbackSuccessPanel.style.display = 'block';
                        } catch (error) {

                            feedbackError.textContent = '{{ __('ui.feedback_error') }}';
                            feedbackError.style.display = 'block';
                        } finally {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = defaultLabel;
                        }
                    });
                }


                const chatTrigger = document.getElementById('chat-trigger');

                const chatPanel = document.getElementById('chat-panel');
                const chatClose = document.getElementById('chat-close');
                const chatInput = document.getElementById('chat-input');
                const chatMessages = document.getElementById('chat-messages');

                chatTrigger.addEventListener('click', () => chatPanel.classList.toggle('active'));
                chatClose.addEventListener('click', () => chatPanel.classList.remove('active'));

                const ChatService = {
                    async sendMessage(message, action = null, history = []) {
                        try {
                            const response = await fetch('/ai/chat', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ message, action, history })
                            });
                            if (!response.ok) throw new Error('API Error');
                            return await response.json();
                        } catch (error) {
                            return { message: "I am having trouble connecting to the AI. Please try again later." };
                        }
                    }
                };

                const ChatUI = {
                    container: document.getElementById('chat-messages'),
                    input: document.getElementById('chat-input'),
                    init() {
                        document.getElementById('chat-send').addEventListener('click', () => this.handleUserMessage());
                        this.input.addEventListener('keypress', (e) => e.key === 'Enter' && this.handleUserMessage());
                    },
                    async handleUserMessage() {
                        const text = this.input.value.trim();
                        if (!text) return;
                        this.appendMessage(text, 'user');
                        this.input.value = '';
                        const data = await ChatService.sendMessage(text);
                        this.appendMessage(data.message, 'ai');
                    },
                    appendMessage(text, type) {
                        const bubble = document.createElement('div');
                        bubble.className = `chat-bubble ${type}`;
                        bubble.textContent = text;
                        this.container.appendChild(bubble);
                        this.container.scrollTop = this.container.scrollHeight;
                    }
                };
                ChatUI.init();
            })();
        </script>
        <!-- Final Force Style Fix -->
        <style>
            .feedback-select, .feedback-input, .feedback-textarea {
                background-color: #0f172a !important;
                background: rgba(15, 23, 42, 0.95) !important;
                color: white !important;
                border: 1px solid rgba(255, 255, 255, 0.2) !important;
            }
            .feedback-select option {
                background: #0f172a !important;
                color: white !important;
            }
        </style>
    </body>
</html>
