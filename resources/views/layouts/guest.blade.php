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

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    
    <style>
        .guest-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.15), transparent),
                        radial-gradient(circle at bottom left, rgba(16, 185, 129, 0.1), transparent);
        }
        .auth-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .auth-logo h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #fff 0%, var(--text-muted) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body>
    <div class="guest-container">
        <div class="animate-fade-in" style="width: 100%; max-width: 450px;">
            <div class="auth-logo">
                <h1>IELTS <span style="color: var(--primary)">AI</span></h1>
            </div>
            
            <main>
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
