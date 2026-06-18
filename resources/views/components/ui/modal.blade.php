{{--
    Accessible modal — focus trap + ESC + restore focus on close.
--}}
@props([
    'id' => 'modal-' . \Illuminate\Support\Str::random(6),
    'title' => '',
    'open' => false,
])

<div id="{{ $id }}" data-modal-open="{{ $open ? 'true' : 'false' }}" role="dialog" aria-modal="true"
     aria-labelledby="{{ $id }}-title"
     {{ $attributes->merge(['class' => 'modal', 'style' => $open ? '' : 'display:none']) }}>
    <div class="modal-backdrop" data-modal-dismiss aria-hidden="true" style="position:fixed;inset:0;background:var(--backdrop);z-index:1000"></div>
    <div class="modal-panel" role="document" tabindex="-1"
         style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--bg-elevated);border-radius:14px;padding:1.5rem;max-width:520px;width:90%;max-height:90vh;overflow:auto;z-index:1001;box-shadow:var(--shadow-hover)">
        @if($title)
            <h2 id="{{ $id }}-title" style="margin:0 0 1rem;font-size:1.25rem">{{ $title }}</h2>
        @endif
        {{ $slot }}
    </div>
</div>

@once
@push('scripts')
    <script>
        (function () {
            document.addEventListener('click', (e) => {
                const dismiss = e.target.closest('[data-modal-dismiss]');
                if (!dismiss) return;
                const modal = dismiss.closest('[data-modal-open]');
                if (modal) {
                    modal.setAttribute('data-modal-open', 'false');
                    modal.style.display = 'none';
                }
            });

            // Trap focus inside any visible modal.
            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Tab') return;
                const open = document.querySelector('[data-modal-open="true"] [role="document"]');
                if (!open) return;
                const focusable = open.querySelectorAll(
                    'a[href],button:not([disabled]),input:not([disabled]),textarea:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])'
                );
                if (!focusable.length) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            });
        })();
    </script>
@endpush
@endonce