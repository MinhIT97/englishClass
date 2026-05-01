import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: document.querySelector('meta[name="reverb-key"]')?.content,
    wsHost: document.querySelector('meta[name="reverb-host"]')?.content || window.location.hostname,
    wsPort: document.querySelector('meta[name="reverb-port"]')?.content || 8080,
    wssPort: document.querySelector('meta[name="reverb-port"]')?.content || 8080,
    forceTLS: (document.querySelector('meta[name="reverb-scheme"]')?.content || 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
        }
    }
});
