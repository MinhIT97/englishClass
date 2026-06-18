{{--
    Accessibility helpers — drop into the layout once.
    - Skip-to-content link (visible on focus, hidden otherwise)
    - Live region for screen reader announcements
    - Cmd/Ctrl+K shortcut to focus search
--}}
@once
<a href="#main-content" class="skip-link" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden">
    Skip to main content
</a>
<div id="sr-live" aria-live="polite" aria-atomic="true" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0"></div>
<style>
    .skip-link:focus {
        position: fixed !important;
        left: 1rem !important;
        top: 1rem !important;
        width: auto !important;
        height: auto !important;
        padding: 0.5rem 1rem !important;
        background: var(--primary);
        color: white;
        border-radius: 6px;
        z-index: 10000;
        text-decoration: none;
        font-weight: 600;
    }
    /* Visible focus indicator for keyboard users */
    :focus-visible {
        outline: 2px solid var(--primary);
        outline-offset: 2px;
        border-radius: 4px;
    }
</style>
@endonce

@once
@push('scripts')
    <script>
        // Announce helper — pages can call window.announce(msg) to
        // push text into the live region without moving focus.
        window.announce = function (msg) {
            const live = document.getElementById('sr-live');
            if (!live) return;
            live.textContent = '';
            setTimeout(() => { live.textContent = msg; }, 50);
        };

        // Cmd/Ctrl+K focuses the global search input if present.
        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                const search = document.querySelector('[data-global-search], input[name="search"]');
                if (search) {
                    e.preventDefault();
                    search.focus();
                }
            }
            // Esc closes any open modal.
            if (e.key === 'Escape') {
                document.querySelectorAll('[data-modal-open="true"]').forEach((m) => {
                    m.setAttribute('data-modal-open', 'false');
                    m.style.display = 'none';
                });
            }
        });
    </script>
@endpush
@endonce