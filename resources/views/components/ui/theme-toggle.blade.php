{{--
    Theme switcher. Three options: light, dark, system. Persisted to
    localStorage AND mirrored to <html data-theme="..."> so first
    paint matches the saved preference (no FOUC).
--}}
@once
@push('head')
    <script>
        // Apply persisted theme before first paint to avoid flash.
        (function () {
            try {
                var saved = localStorage.getItem('theme');
                var system = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                var theme = saved === 'light' || saved === 'dark' ? saved : system;
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) { /* localStorage blocked — fall back to light */ }
        })();
    </script>
@endpush
@endonce

<button type="button" class="theme-toggle" aria-label="Toggle theme" data-theme-toggle
    style="background:none;border:1px solid var(--glass-border);color:inherit;border-radius:8px;padding:0.4rem 0.6rem;cursor:pointer;font-size:1rem;line-height:1">
    <span data-theme-icon-light>🌞</span>
    <span data-theme-icon-dark style="display:none">🌙</span>
    <span data-theme-icon-system style="display:none">💻</span>
</button>

@once
@push('scripts')
    <script>
        (function () {
            const btn = document.querySelector('[data-theme-toggle]');
            if (!btn) return;

            const ICONS = {
                light:  btn.querySelector('[data-theme-icon-light]'),
                dark:   btn.querySelector('[data-theme-icon-dark]'),
                system: btn.querySelector('[data-theme-icon-system]'),
            };

            const ORDER = ['system', 'light', 'dark'];

            function apply(theme) {
                let resolved = theme;
                if (theme === 'system') {
                    resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    document.documentElement.setAttribute('data-theme', resolved);
                    document.documentElement.setAttribute('data-theme-pref', 'system');
                } else {
                    document.documentElement.setAttribute('data-theme', theme);
                    document.documentElement.setAttribute('data-theme-pref', theme);
                }
                try { localStorage.setItem('theme', theme); } catch (e) {}

                Object.entries(ICONS).forEach(([k, el]) => {
                    if (!el) return;
                    el.style.display = k === theme ? '' : 'none';
                });
            }

            let current;
            try { current = localStorage.getItem('theme') || 'system'; }
            catch (e) { current = 'system'; }
            apply(current);

            btn.addEventListener('click', () => {
                const idx = ORDER.indexOf(current);
                current = ORDER[(idx + 1) % ORDER.length];
                apply(current);
            });

            // React to system theme change when user is in 'system' mode.
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (current === 'system') apply('system');
            });
        })();
    </script>
@endpush
@endonce