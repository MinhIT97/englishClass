<x-app-layout>
    <div style="margin-bottom: 2.5rem">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem">🤖 {{ __('ui.telegram_settings_title') }}</h1>
        <p style="color: var(--text-muted)">{{ __('ui.telegram_settings_subtitle') }}</p>
    </div>

    @if(session('status'))
        <div style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 1rem; border-radius: var(--radius); margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.2);">
            {{ session('status') }}
        </div>
    @endif

    @if(session('linking_code'))
        @php
            $code = session('linking_code');
            $expiresIso = session('linking_code_expires_at');
            $expiresAt = $expiresIso ? \Carbon\Carbon::parse($expiresIso) : null;
        @endphp
        <div style="background: rgba(99, 102, 241, 0.1); color: #6366f1; padding: 1.5rem; border-radius: var(--radius); margin-bottom: 2rem; border: 1px solid rgba(99, 102, 241, 0.2);">
            <h3 style="margin-bottom: 0.75rem">{{ __('ui.telegram_linking_code') }}</h3>
            <div style="font-family: monospace; font-size: 1.5rem; letter-spacing: 0.2rem; background: rgba(0,0,0,0.2); padding: 0.75rem; border-radius: var(--radius); display: inline-block;">
                {{ $code }}
            </div>
            @if($expiresAt)
                <p style="margin-top: 0.75rem; font-size: 0.875rem;">
                    {{ __('ui.telegram_code_expires_at') }} <strong>{{ $expiresAt->format('H:i:s') }}</strong>
                </p>
            @endif
            <p style="margin-top: 1rem; font-size: 0.875rem;">
                {{ __('ui.telegram_send_to_bot') }}
                <code style="background: rgba(0,0,0,0.2); padding: 0.25rem 0.5rem; border-radius: 4px;">/start {{ $code }}</code>
            </p>
        </div>
    @endif

    @error('linking_code')
        <div style="background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 1rem; border-radius: var(--radius); margin-bottom: 2rem; border: 1px solid rgba(239, 68, 68, 0.2);">
            {{ $message }}
        </div>
    @enderror

    <div class="glass-card" style="margin-bottom: 2rem;">
        <h3 style="margin-bottom: 1rem">📱 {{ __('ui.telegram_bot_link') }}</h3>

        @if($link)
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
                ✅ <strong>{{ __('ui.telegram_linked_as') }}:</strong>
                @if($link->telegram_username)
                    @{{ $link->telegram_username }}
                @else
                    #{{ $link->telegram_chat_id }}
                @endif
                <br>
                <small style="color: var(--text-muted);">
                    {{ __('ui.telegram_linked_at') }}: {{ $link->linked_at?->format('d/m/Y H:i') }}
                </small>
            </div>

            <form action="{{ route('student.telegram.unlink') }}" method="POST" onsubmit="return confirm('{{ __('ui.telegram_unlink_confirm') }}')">
                @csrf
                <button type="submit" class="btn" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);">
                    {{ __('ui.telegram_unlink_button') }}
                </button>
            </form>
        @else
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                {{ __('ui.telegram_not_linked_hint') }}
            </p>

            <a href="https://t.me/{{ $botUsername }}" target="_blank" rel="noopener"
               class="btn" style="display: inline-block; background: #229ED9; color: white; padding: 0.6rem 1.25rem; border-radius: var(--radius); text-decoration: none; margin-bottom: 1.5rem;">
                ✈️ {{ __('ui.telegram_open_bot') }} @{{ $botUsername }}
            </a>

            <form action="{{ route('student.telegram.linking-code') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    🔗 {{ __('ui.telegram_generate_code') }}
                </button>
            </form>

            <ol style="margin-top: 1.5rem; padding-left: 1.25rem; color: var(--text-muted); font-size: 0.875rem;">
                <li>{!! __('ui.telegram_step_1') !!}</li>
                <li>{!! __('ui.telegram_step_2') !!}</li>
                <li>{!! __('ui.telegram_step_3') !!}</li>
            </ol>
        @endif
    </div>

    @if($profile)
        <div class="glass-card">
            <h3 style="margin-bottom: 1rem">📚 {{ __('ui.telegram_learning_profile') }}</h3>
            <ul style="list-style: none; padding: 0;">
                <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--glass-border);">
                    🎯 {{ __('ui.telegram_purpose') }}:
                    <strong>{{ \Modules\TelegramBot\Models\LearningProfile::purposes()[$profile->purpose] ?? $profile->purpose }}</strong>
                </li>
                <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--glass-border);">
                    📊 {{ __('ui.telegram_level') }}:
                    <strong>{{ \Modules\TelegramBot\Models\LearningProfile::levels()[$profile->level] ?? $profile->level }}</strong>
                </li>
                <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--glass-border);">
                    ⏰ {{ __('ui.telegram_send_hour') }}:
                    <strong>{{ $profile->daily_send_hour }}:00 ({{ $profile->timezone }})</strong>
                </li>
                <li style="padding: 0.5rem 0;">
                    {{ $profile->is_paused ? '⏸' : '▶️' }}
                    {{ $profile->is_paused ? __('ui.telegram_paused') : __('ui.telegram_active') }}
                </li>
            </ul>
            <p style="margin-top: 1rem; color: var(--text-muted); font-size: 0.875rem;">
                {{ __('ui.telegram_change_via_bot') }}
            </p>
        </div>
    @endif
</x-app-layout>
