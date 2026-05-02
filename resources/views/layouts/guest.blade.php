<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'IELTS Mastery') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@600;700&display=swap" rel="stylesheet">

    <!-- Styles & Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        .guest-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }
        .auth-logo {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .auth-logo h1 {
            font-size: 3rem;
            font-weight: 800;
            letter-spacing: -0.05em;
        }
    </style>
</head>
<body>
    <!-- Background Orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div style="position: fixed; top: 1.5rem; right: 1.5rem; display: flex; gap: 0.75rem; z-index: 1000">
        <!-- Language Switcher -->
        <div style="display: flex; gap: 0.5rem">
            <a href="{{ route('set.locale', 'vi') }}" class="glass {{ app()->getLocale() === 'vi' ? 'active-lang' : '' }}" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 800; border-radius: 50%; color: var(--text-main); text-decoration: none">
                VI
            </a>
            <a href="{{ route('set.locale', 'en') }}" class="glass {{ app()->getLocale() === 'en' ? 'active-lang' : '' }}" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 800; border-radius: 50%; color: var(--text-main); text-decoration: none">
                EN
            </a>
        </div>

        <button id="theme-toggle" class="glass" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; cursor: pointer; border-radius: 50%">
            🌙
        </button>
    </div>

    <div class="guest-container">
        <div class="animate-fade-in" style="width: 100%; max-width: 480px;">
            <div class="auth-logo">
                <h1>IELTS <span class="text-primary-glow">AI</span></h1>
            </div>
            
            <main>
                {{ $slot }}
            </main>
        </div>
    </div>

    <script>
        (function() {
            const themeToggle = document.getElementById('theme-toggle');
            const body = document.body;
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'light') {
                body.classList.add('light-mode');
                themeToggle.innerText = '☀️';
            }
            themeToggle.addEventListener('click', () => {
                const isLight = body.classList.toggle('light-mode');
                localStorage.setItem('theme', isLight ? 'light' : 'dark');
                themeToggle.innerText = isLight ? '☀️' : '🌙';
            });
        })();
    </script>
</body>
</html>
