{{--
    PWA registration. Drop once into the main layout. Registers the
    service worker, exposes a beforeinstallprompt handler, and
    surfaces an "Add to Home Screen" CTA to the user.
--}}
@once
@push('head')
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#6366f1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
@endpush
@endonce

@once
@push('scripts')
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(() => {});
            });
        }

        // Surface "Add to Home Screen" CTA on browsers that support it.
        let deferredPrompt = null;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            document.dispatchEvent(new CustomEvent('pwa-installable'));
        });

        window.installPwa = async function () {
            if (!deferredPrompt) return false;
            deferredPrompt.prompt();
            const choice = await deferredPrompt.userChoice;
            deferredPrompt = null;
            return choice.outcome === 'accepted';
        };
    </script>
@endpush
@endonce