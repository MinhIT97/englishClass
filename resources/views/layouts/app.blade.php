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

        <!-- Floating Chat Widget -->
        <div class="chat-widget-trigger" id="chat-trigger" title="Hỗ trợ học tập">
            <span style="font-size: 1.5rem">🤖</span>
        </div>

        <div class="chat-panel" id="chat-panel">
            <div class="chat-header">
                <h3>{{ __('ui.learning_assistant') }}</h3>
                <button class="chat-close" id="chat-close">✕</button>
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
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
            </div>
        </div>

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

            // Chat Widget Logic
            const chatTrigger = document.getElementById('chat-trigger');
            const chatPanel = document.getElementById('chat-panel');
            const chatClose = document.getElementById('chat-close');
            const chatInput = document.getElementById('chat-input');
            const chatSend = document.getElementById('chat-send');
            const chatMessages = document.getElementById('chat-messages');

            chatTrigger.addEventListener('click', () => {
                chatPanel.classList.toggle('active');
            });

            /**
             * API Service Layer
             */
            const ChatService = {
                async sendMessage(message, action = null) {
                    try {
                        const response = await fetch('/api/ai/chat', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ message, action })
                        });
                        
                        if (!response.ok) throw new Error('API Error');
                        return await response.json();
                    } catch (error) {
                        console.error('ChatService Error:', error);
                        // Fallback for demo
                        return this.getMockResponse(message, action);
                    }
                },

                getMockResponse(message, action) {
                    return new Promise(resolve => {
                        setTimeout(() => {
                            let response = {
                                message: "",
                                suggestions: [
                                    { "type": "fix", "label": "{{ __('ui.ai_label_correction') }}" },
                                    { "type": "explain", "label": "{{ __('ui.ai_label_explanation') }}" },
                                    { "type": "natural", "label": "{{ __('ui.ai_label_naturalness') }}" }
                                ],
                                next_question: ""
                            };

                            const lowerMsg = message.toLowerCase();

                            if (action === 'fix') {
                                response.message = lowerMsg.replace(/i is/g, 'I am').replace(/she have/g, 'she has').replace(/he go/g, 'he goes');
                                if (response.message === lowerMsg) response.message = "Câu của bạn có vẻ đã chuẩn ngữ pháp rồi!";
                                response.next_question = "Bạn có muốn tôi giải thích cấu trúc này không?";
                            } else if (action === 'explain') {
                                if (lowerMsg.includes('is')) {
                                    response.message = "Trong tiếng Anh, động từ 'to be' phải chia theo chủ ngữ. 'I' đi với 'am', 'He/She/It' đi với 'is'.";
                                } else {
                                    response.message = "Đây là một cấu trúc thông dụng trong giao tiếp, tập trung vào việc sử dụng đúng thì và hòa hợp chủ-vị.";
                                }
                                response.next_question = "Bạn đã nắm rõ phần này chưa?";
                            } else if (action === 'natural') {
                                response.message = "Câu này đạt khoảng 8/10 điểm về độ tự nhiên. Người bản xứ thường sẽ nói ngắn gọn hơn một chút.";
                                response.next_question = "Bạn muốn học cách nói tự nhiên hơn không?";
                            } else {
                                // Initial Message
                                if (lowerMsg.includes('hello') || lowerMsg.includes('hi')) {
                                    response.message = "Chào bạn! Rất vui được hỗ trợ bạn luyện tập tiếng Anh hôm nay.";
                                } else {
                                    response.message = `Tôi đã nhận được tin nhắn: "${message}". Bạn muốn tôi giúp gì với câu này?`;
                                }
                                response.next_question = "Hãy thử đặt một câu hỏi về ngữ pháp nhé!";
                            }

                            // Filter out current action from suggestions
                            if (action) {
                                response.suggestions = response.suggestions.filter(s => s.type !== action);
                            }

                            resolve(response);
                        }, 1200);
                    });
                }
            };

            /**
             * UI Controller Layer
             */
            const ChatUI = {
                container: document.getElementById('chat-messages'),
                input: document.getElementById('chat-input'),
                lastResponseWrapper: null,
                typingIndicator: null,

                init() {
                    document.getElementById('chat-send').addEventListener('click', () => this.handleUserMessage());
                    this.input.addEventListener('keypress', (e) => e.key === 'Enter' && this.handleUserMessage());
                    
                    // Initial suggestions handled in HTML are now delegated to this handler
                    document.querySelectorAll('.suggestion-pill').forEach(pill => {
                        pill.addEventListener('click', () => {
                            this.input.value = pill.textContent;
                            this.handleUserMessage();
                        });
                    });
                },

                async handleUserMessage(action = null, originalText = null) {
                    const message = action ? originalText : this.input.value.trim();
                    if (!message && !action) return;

                    if (!action) {
                        this.appendUserMessage(message);
                        this.input.value = '';
                        this.lastResponseWrapper = null;
                    }

                    this.showTypingIndicator();
                    
                    const data = await ChatService.sendMessage(message, action);
                    
                    this.hideTypingIndicator();
                    this.renderAIResponse(data, message, action !== null);
                },

                appendUserMessage(text) {
                    const bubble = document.createElement('div');
                    bubble.className = 'chat-bubble user';
                    bubble.textContent = text;
                    this.container.appendChild(bubble);
                    this.scrollToBottom();
                },

                showTypingIndicator() {
                    this.hideTypingIndicator(); // Clear existing
                    this.typingIndicator = document.createElement('div');
                    this.typingIndicator.className = 'typing-indicator';
                    this.typingIndicator.innerHTML = '<div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>';
                    this.container.appendChild(this.typingIndicator);
                    this.scrollToBottom();
                },

                hideTypingIndicator() {
                    if (this.typingIndicator) {
                        this.typingIndicator.remove();
                        this.typingIndicator = null;
                    }
                },

                renderAIResponse(data, originalText, isUpdate) {
                    let wrapper;
                    if (isUpdate && this.lastResponseWrapper) {
                        wrapper = this.lastResponseWrapper;
                        wrapper.innerHTML = '';
                    } else {
                        wrapper = document.createElement('div');
                        wrapper.className = 'ai-response-wrapper';
                        this.container.appendChild(wrapper);
                        this.lastResponseWrapper = wrapper;
                    }

                    // Content
                    const bubble = document.createElement('div');
                    bubble.className = 'chat-bubble ai';
                    bubble.textContent = data.message;
                    wrapper.appendChild(bubble);

                    // Suggestions
                    if (data.suggestions) {
                        const suggestionsDiv = document.createElement('div');
                        suggestionsDiv.className = 'chat-suggestions';
                        data.suggestions.forEach(s => {
                            const btn = document.createElement('button');
                            btn.className = 'suggestion-pill';
                            btn.textContent = s.label;
                            btn.onclick = () => this.handleUserMessage(s.type, originalText);
                            suggestionsDiv.appendChild(btn);
                        });
                        wrapper.appendChild(suggestionsDiv);
                    }

                    // Next Question
                    if (data.next_question) {
                        const nextQ = document.createElement('div');
                        nextQ.className = 'chat-bubble ai';
                        nextQ.style.cssText = 'font-style:italic; background:rgba(99,102,241,0.1)';
                        nextQ.textContent = '➜ ' + data.next_question;
                        wrapper.appendChild(nextQ);
                    }

                    this.scrollToBottom();
                },

                scrollToBottom() {
                    this.container.scrollTop = this.container.scrollHeight;
                }
            };

            ChatUI.init();
        })();
    </script>
</body>
</html>
