@php
    use Modules\TelegramBot\Models\UserTelegramLink;

    // Show banner only when:
    // - User is logged in
    // - User is a student
    // - User has NOT linked Telegram yet
    // - Banner hasn't been dismissed in this session
    $showBanner = false;
    $dismissed = false;

    if (auth()->check() && !auth()->user()->isAdmin() && !auth()->user()->isTeacher()) {
        $link = UserTelegramLink::query()
            ->where('user_id', auth()->id())
            ->exists();
        $dismissed = session()->has('tgb_banner_dismissed');
        $showBanner = !$link && !$dismissed;
    }
@endphp

@if($showBanner)
    <div class="tgb-dashboard-banner" id="tgb-dashboard-banner">
        <div class="tgb-banner-icon">🤖</div>
        <div class="tgb-banner-content">
            <div class="tgb-banner-title">📣 {{ __('ui.telegram_dashboard_banner_title') }}</div>
            <div class="tgb-banner-desc">{{ __('ui.telegram_dashboard_banner_desc') }}</div>
        </div>
        <div class="tgb-banner-actions">
            <a href="{{ route('student.telegram.settings') }}" class="tgb-banner-cta">
                {{ __('ui.telegram_dashboard_banner_cta') }} →
            </a>
            <button type="button" class="tgb-banner-dismiss" onclick="tgbDismissBanner()" aria-label="Dismiss">
                ✕
            </button>
        </div>
    </div>

    <script>
        function tgbDismissBanner() {
            var banner = document.getElementById('tgb-dashboard-banner');
            if (banner) {
                banner.style.opacity = '0';
                banner.style.transform = 'translateY(-10px)';
                setTimeout(function () { banner.style.display = 'none'; }, 300);
            }
            // Persist dismissal for the rest of the session.
            fetch('{{ route('student.telegram.dismiss-banner') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                credentials: 'same-origin'
            }).catch(function () {});
        }
    </script>

    <style>
        .tgb-dashboard-banner {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 1.25rem 1.5rem;
            background: linear-gradient(135deg, rgba(34, 158, 217, 0.12) 0%, rgba(99, 102, 241, 0.12) 100%);
            border: 1px solid rgba(99, 102, 241, 0.25);
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            transition: opacity 0.3s, transform 0.3s;
        }
        .tgb-banner-icon {
            font-size: 2.25rem;
            flex-shrink: 0;
        }
        .tgb-banner-content {
            flex: 1;
            min-width: 0;
        }
        .tgb-banner-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #c7d2fe;
        }
        .tgb-banner-desc {
            font-size: 0.875rem;
            color: var(--text-muted);
        }
        .tgb-banner-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }
        .tgb-banner-cta {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.5rem 1.25rem;
            background: #229ED9;
            color: white;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            white-space: nowrap;
            transition: background 0.2s;
        }
        .tgb-banner-cta:hover {
            background: #1e8bc0;
        }
        .tgb-banner-dismiss {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            font-size: 1rem;
            line-height: 1;
            transition: color 0.2s;
        }
        .tgb-banner-dismiss:hover {
            color: var(--text-main);
        }
        @media (max-width: 640px) {
            .tgb-dashboard-banner {
                flex-wrap: wrap;
                padding: 1rem;
            }
            .tgb-banner-icon {
                font-size: 1.75rem;
            }
            .tgb-banner-content {
                flex-basis: calc(100% - 3rem);
            }
            .tgb-banner-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
@endif
