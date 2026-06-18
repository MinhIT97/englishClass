{{--
    Mock Test Timer — realistic IELTS-style countdown.

    Usage:
      <x-ui.mock-test-timer :duration-seconds="3600" :auto-submit="true" />
      When time runs out, dispatches a 'mock-test:timeout' CustomEvent
      that the parent page can listen to.

    data-warning-at-seconds: change color of the timer when remaining
    time drops below this. Default 300s (5 minutes).
--}}
@props([
    'durationSeconds' => 3600,
    'autoSubmit' => true,
    'warningAtSeconds' => 300,
])

<div x-data="mockTestTimer({
    duration: {{ (int) $durationSeconds }},
    autoSubmit: {{ $autoSubmit ? 'true' : 'false' }},
    warningAt: {{ (int) $warningAtSeconds }}
})"
     x-init="init()"
     @mock-test:timeout.window="if (autoSubmit) document.querySelector('form[data-mock-test-form]')?.submit()"
     class="mock-test-timer"
     :class="{ 'is-warning': remainingSeconds <= warningAt, 'is-critical': remainingSeconds <= 60 }"
     role="timer"
     aria-live="polite"
     style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.5rem 1rem;background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:10px;font-variant-numeric:tabular-nums;font-weight:600">
    <span aria-hidden="true">⏱️</span>
    <span x-text="formatted" style="min-width:5.5ch;text-align:center"></span>
    <span x-show="paused" x-cloak style="font-size:0.75rem;color:var(--warning)">(đã tạm dừng)</span>
</div>

<style>
    .mock-test-timer.is-warning { background: rgba(245, 158, 11, 0.1); border-color: var(--warning); color: var(--warning); }
    .mock-test-timer.is-critical { background: rgba(239, 68, 68, 0.15); border-color: var(--danger); color: var(--danger); animation: pulse-critical 1s ease-in-out infinite; }
    @keyframes pulse-critical {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    [x-cloak] { display: none !important; }
</style>

@once
@push('scripts')
    <script>
        // Alpine.js-free inline implementation
        document.addEventListener('alpine:init', () => {
            window.Alpine.data('mockTestTimer', (cfg) => ({
                duration: cfg.duration,
                warningAt: cfg.warningAt,
                autoSubmit: cfg.autoSubmit,
                remainingSeconds: cfg.duration,
                paused: false,
                interval: null,
                get formatted() {
                    const s = Math.max(0, this.remainingSeconds);
                    const h = Math.floor(s / 3600);
                    const m = Math.floor((s % 3600) / 60);
                    const sec = s % 60;
                    return (h > 0 ? h + ':' : '') +
                        String(m).padStart(2, '0') + ':' +
                        String(sec).padStart(2, '0');
                },
                init() {
                    this.start();
                    document.addEventListener('visibilitychange', () => {
                        if (document.hidden) this.pause();
                        else this.resume();
                    });
                },
                start() {
                    if (this.interval) return;
                    const startedAt = Date.now();
                    const startRemaining = this.remainingSeconds;
                    this.interval = setInterval(() => {
                        const elapsed = Math.floor((Date.now() - startedAt) / 1000);
                        this.remainingSeconds = Math.max(0, startRemaining - elapsed);
                        if (this.remainingSeconds <= 0) {
                            clearInterval(this.interval);
                            this.interval = null;
                            window.dispatchEvent(new CustomEvent('mock-test:timeout'));
                        }
                    }, 250);
                },
                pause() { this.paused = true; clearInterval(this.interval); this.interval = null; },
                resume() { this.paused = false; this.start(); },
            }));
        });
    </script>
@endpush
@endonce