{{--
    Toast host — reads JSON from window.__toasts (set via Blade) and
    renders them on page load. JS in resources/js/toast.js wires the
    show/dismiss animation.
--}}
@once
<div id="toast-host" aria-live="polite" aria-atomic="true" style="position: fixed; top: 1rem; right: 1rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; max-width: 360px;"></div>
@endonce

@php
    $toastPayload = [];
    if (session('success')) $toastPayload[] = ['type' => 'success', 'message' => session('success')];
    if (session('error'))   $toastPayload[] = ['type' => 'error',   'message' => session('error')];
    if (session('warning')) $toastPayload[] = ['type' => 'warning', 'message' => session('warning')];
    if (session('info'))    $toastPayload[] = ['type' => 'info',    'message' => session('info')];
@endphp

@if(count($toastPayload))
    <script>
        window.__toasts = window.__toasts || [];
        window.__toasts.push(...@json($toastPayload));
    </script>
@endif