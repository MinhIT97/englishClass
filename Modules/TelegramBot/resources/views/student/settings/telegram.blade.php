<x-app-layout>
    <div style="margin-bottom: 2.5rem">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem">🤖 {{ __('ui.telegram_settings_title') }}</h1>
        <p style="color: var(--text-muted)">{{ __('ui.telegram_settings_subtitle') }}</p>
    </div>

    @if(session('status'))
        <div class="tgb-alert tgb-alert-success">
            ✅ {{ session('status') }}
        </div>
    @endif

    @if(session('linking_code'))
        @php
            $code = session('linking_code');
            $expiresIso = session('linking_code_expires_at');
            $expiresAt = $expiresIso ? \Carbon\Carbon::parse($expiresIso) : null;
        @endphp
        <div class="tgb-alert tgb-alert-info" id="tgb-code-card" data-expires-at="{{ $expiresAt?->toIso8601String() }}">
            <div class="tgb-code-header">
                <h3 style="margin: 0">🔑 {{ __('ui.telegram_linking_code') }}</h3>
                @if($expiresAt)
                    <span id="tgb-countdown" class="tgb-countdown" data-seconds="{{ max(0, (int) now()->diffInSeconds($expiresAt, false)) }}">
                        ⏱ <span id="tgb-countdown-text">--:--</span>
                    </span>
                @endif
            </div>

            <div class="tgb-code-display">
                <code id="tgb-code">{{ $code }}</code>
                <button type="button" class="tgb-copy-btn" onclick="tgbCopyCode()" title="Copy">
                    📋 <span id="tgb-copy-label">Copy</span>
                </button>
            </div>

            <p class="tgb-helper">
                {{ __('ui.telegram_send_to_bot') }}
            </p>
            <div class="tgb-command-example">
                <code>/start {{ $code }}</code>
            </div>
        </div>
    @endif

    @error('linking_code')
        <div class="tgb-alert tgb-alert-error">⚠️ {{ $message }}</div>
    @enderror

    <div class="glass-card" style="margin-bottom: 2rem;">
        <h3 style="margin-bottom: 1rem">📱 {{ __('ui.telegram_bot_link') }}</h3>

        @if($link)
            <div class="tgb-alert tgb-alert-success" style="margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="font-size: 1.5rem;">✅</div>
                    <div style="flex: 1;">
                        <div>
                            <strong>{{ __('ui.telegram_linked_as') }}:</strong>
                            @if($link->telegram_username)
                                @{{ $link->telegram_username }}
                            @else
                                #{{ $link->telegram_chat_id }}
                            @endif
                        </div>
                        <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">
                            {{ __('ui.telegram_linked_at') }}: {{ $link->linked_at?->format('d/m/Y H:i') }}
                        </small>
                    </div>
                </div>
            </div>

            <form action="{{ route('student.telegram.unlink') }}" method="POST"
                  onsubmit="return confirm('{{ __('ui.telegram_unlink_confirm') }}')">
                @csrf
                <button type="submit" class="btn tgb-btn-danger">
                    🔓 {{ __('ui.telegram_unlink_button') }}
                </button>
            </form>
        @else
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                {{ __('ui.telegram_not_linked_hint') }}
            </p>

            <a href="https://t.me/{{ $botUsername }}" target="_blank" rel="noopener"
               class="btn tgb-btn-telegram">
                ✈️ {{ __('ui.telegram_open_bot') }} @{{ $botUsername }}
            </a>

            <form action="{{ route('student.telegram.linking-code') }}" method="POST" style="margin-top: 1.5rem;">
                @csrf
                <button type="submit" class="btn btn-primary tgb-btn-block">
                    🔗 {{ __('ui.telegram_generate_code') }}
                </button>
            </form>

            <div class="tgb-steps">
                <div class="tgb-step">
                    <div class="tgb-step-num">1</div>
                    <div class="tgb-step-content">
                        <div class="tgb-step-title">Mở bot trên Telegram</div>
                        <div class="tgb-step-desc">Nhấn nút "Mở" màu xanh phía trên để mở <strong>@{{ $botUsername }}</strong></div>
                    </div>
                </div>
                <div class="tgb-step">
                    <div class="tgb-step-num">2</div>
                    <div class="tgb-step-content">
                        <div class="tgb-step-title">Tạo mã liên kết</div>
                        <div class="tgb-step-desc">Nhấn nút "Tạo mã liên kết mới" ở trên. Mã có hiệu lực 10 phút.</div>
                    </div>
                </div>
                <div class="tgb-step">
                    <div class="tgb-step-num">3</div>
                    <div class="tgb-step-content">
                        <div class="tgb-step-title">Gửi mã cho bot</div>
                        <div class="tgb-step-desc">Trong Telegram, nhấn nút "Copy" rồi dán mã vào cuộc trò chuyện với bot theo mẫu: <code>/start &lt;MÃ&gt;</code></div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if($profile)
        <div class="glass-card">
            <h3 style="margin-bottom: 1rem">📚 {{ __('ui.telegram_learning_profile') }}</h3>
            <ul class="tgb-profile-list">
                <li>
                    🎯 {{ __('ui.telegram_purpose') }}:
                    <strong>{{ \Modules\TelegramBot\Models\LearningProfile::purposes()[$profile->purpose] ?? $profile->purpose }}</strong>
                </li>
                <li>
                    📊 {{ __('ui.telegram_level') }}:
                    <strong>{{ \Modules\TelegramBot\Models\LearningProfile::levels()[$profile->level] ?? $profile->level }}</strong>
                </li>
                <li>
                    ⏰ {{ __('ui.telegram_send_hour') }}:
                    <strong>{{ sprintf('%02d:00', $profile->daily_send_hour) }} ({{ $profile->timezone }})</strong>
                </li>
                <li>
                    <span class="tgb-status {{ $profile->is_paused ? 'tgb-status-paused' : 'tgb-status-active' }}">
                        {{ $profile->is_paused ? '⏸' : '▶️' }}
                        {{ $profile->is_paused ? __('ui.telegram_paused') : __('ui.telegram_active') }}
                    </span>
                </li>
            </ul>
            <p style="margin-top: 1rem; color: var(--text-muted); font-size: 0.875rem;">
                💡 {{ __('ui.telegram_change_via_bot') }}
            </p>
        </div>
    @endif

    <style>
        .tgb-alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            border: 1px solid;
        }
        .tgb-alert-success {
            background: rgba(16, 185, 129, 0.08);
            color: #10b981;
            border-color: rgba(16, 185, 129, 0.25);
        }
        .tgb-alert-info {
            background: rgba(99, 102, 241, 0.08);
            color: #818cf8;
            border-color: rgba(99, 102, 241, 0.25);
        }
        .tgb-alert-error {
            background: rgba(239, 68, 68, 0.08);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.25);
        }
        .tgb-code-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .tgb-countdown {
            font-family: monospace;
            font-size: 0.875rem;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.2);
            color: #818cf8;
            transition: all 0.3s;
        }
        .tgb-countdown.tgb-warning {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            animation: pulse 1.5s infinite;
        }
        .tgb-countdown.tgb-expired {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        .tgb-code-display {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .tgb-code-display code {
            flex: 1;
            font-family: 'Courier New', monospace;
            font-size: 1.75rem;
            font-weight: bold;
            letter-spacing: 0.4rem;
            background: rgba(0, 0, 0, 0.25);
            padding: 0.875rem 1.25rem;
            border-radius: var(--radius);
            color: #fff;
            text-align: center;
            user-select: all;
        }
        .tgb-copy-btn {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.875rem 1rem;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 0.875rem;
            white-space: nowrap;
            transition: background 0.2s;
        }
        .tgb-copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .tgb-copy-btn.tgb-copied {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border-color: rgba(16, 185, 129, 0.3);
        }
        .tgb-helper {
            color: #c7d2fe;
            font-size: 0.875rem;
            margin: 0 0 0.5rem 0;
        }
        .tgb-command-example {
            background: rgba(0, 0, 0, 0.3);
            padding: 0.75rem 1rem;
            border-radius: var(--radius);
            margin: 0;
        }
        .tgb-command-example code {
            color: #fbbf24;
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            user-select: all;
        }
        .tgb-btn-telegram {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #229ED9 !important;
            color: white !important;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 500;
            border: none;
        }
        .tgb-btn-telegram:hover {
            background: #1e8bc0 !important;
        }
        .tgb-btn-block {
            width: 100%;
        }
        .tgb-btn-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 0.6rem 1.25rem;
            border-radius: var(--radius);
        }
        .tgb-btn-danger:hover {
            background: rgba(239, 68, 68, 0.2);
        }
        .tgb-steps {
            margin-top: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .tgb-step {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        .tgb-step-num {
            flex-shrink: 0;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(99, 102, 241, 0.2);
            color: #818cf8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .tgb-step-content {
            flex: 1;
        }
        .tgb-step-title {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        .tgb-step-desc {
            color: var(--text-muted);
            font-size: 0.875rem;
        }
        .tgb-step-desc code {
            background: rgba(0, 0, 0, 0.2);
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
            font-size: 0.8125rem;
        }
        .tgb-profile-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .tgb-profile-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .tgb-profile-list li:last-child {
            border-bottom: none;
        }
        .tgb-status {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .tgb-status-active {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }
        .tgb-status-paused {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
        }
    </style>

    <script>
        // Real-time countdown for the linking code.
        (function () {
            var el = document.getElementById('tgb-countdown');
            if (!el) return;
            var textEl = document.getElementById('tgb-countdown-text');
            var cardEl = document.getElementById('tgb-code-card');
            var totalSeconds = parseInt(el.dataset.seconds, 10) || 0;
            var deadline = Date.now() + totalSeconds * 1000;

            function tick() {
                var remaining = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
                var m = Math.floor(remaining / 60);
                var s = remaining % 60;
                textEl.textContent = String(m).padStart(1, '0') + ':' + String(s).padStart(2, '0');

                el.classList.remove('tgb-warning', 'tgb-expired');
                if (remaining === 0) {
                    el.classList.add('tgb-expired');
                    textEl.textContent = 'Hết hạn';
                    if (cardEl) cardEl.style.opacity = '0.5';
                    return false;
                } else if (remaining < 60) {
                    el.classList.add('tgb-warning');
                }
                return true;
            }

            if (tick()) {
                setInterval(tick, 1000);
            }
        })();

        // Copy code to clipboard.
        function tgbCopyCode() {
            var code = document.getElementById('tgb-code');
            var btn = document.querySelector('.tgb-copy-btn');
            var label = document.getElementById('tgb-copy-label');
            if (!code || !btn) return;

            var text = code.textContent.trim();
            var done = function () {
                btn.classList.add('tgb-copied');
                label.textContent = 'Copied!';
                setTimeout(function () {
                    btn.classList.remove('tgb-copied');
                    label.textContent = 'Copy';
                }, 2000);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(function () {
                    fallbackCopy(text);
                    done();
                });
            } else {
                fallbackCopy(text);
                done();
            }
        }

        function fallbackCopy(text) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta);
        }
    </script>
</x-app-layout>
