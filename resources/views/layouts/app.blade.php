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
        .layout-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-secondary);
            border-right: 1px solid var(--glass-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 100;
        }
        
        .sidebar-header {
            padding: 2rem;
            text-align: center;
        }
        
        .sidebar-header h2 {
            font-size: 1.5rem;
            color: var(--text-main);
        }
        
        .sidebar-nav {
            flex: 1;
            padding: 1rem;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.25rem;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: var(--radius);
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .nav-item:hover, .nav-item.active {
            background: var(--glass);
            color: var(--primary);
        }
        
        .nav-icon {
            margin-right: 1rem;
            width: 20px;
            text-align: center;
        }
        
        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--glass-border);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
        }
        
        .top-nav {
            height: var(--header-height);
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 90;
        }
        
        .user-pill {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            font-size: 0.875rem;
            color: var(--text-main);
        }
        
        .content-body {
            padding: 2.5rem;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-pending { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .badge-active { background: rgba(16, 185, 129, 0.2); color: #10b981; }
    </style>
</head>
<body>
    <div class="layout-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>IELTS <span style="color: var(--primary)">AI</span></h2>
            </div>
            
            <nav class="sidebar-nav">
                @if(auth()->user()->role === 'admin')
                    <a href="/admin/dashboard" class="nav-item {{ request()->is('admin/dashboard') ? 'active' : '' }}">
                        <span class="nav-icon">📊</span> Dashboard
                    </a>
                    <a href="/admin/users" class="nav-item {{ request()->is('admin/users') ? 'active' : '' }}">
                        <span class="nav-icon">👥</span> Users Approval
                    </a>
                    <a href="/admin/questions" class="nav-item {{ request()->is('admin/questions') ? 'active' : '' }}">
                        <span class="nav-icon">📝</span> Question Bank
                    </a>
                @else
                    <a href="/student/dashboard" class="nav-item {{ request()->is('student/dashboard') ? 'active' : '' }}">
                        <span class="nav-icon">🏠</span> Home
                    </a>
                    <a href="/student/practice" class="nav-item {{ request()->is('student/practice') ? 'active' : '' }}">
                        <span class="nav-icon">⚡</span> Practice Mode
                    </a>
                    <a href="/student/test" class="nav-item {{ request()->is('student/test') ? 'active' : '' }}">
                        <span class="nav-icon">🏆</span> Mock Tests
                    </a>
                @endif
                
                <a href="/settings" class="nav-item {{ request()->is('settings') ? 'active' : '' }}">
                    <span class="nav-icon">⚙️</span> Settings
                </a>

                <div style="margin-top: 2rem; padding: 0 1rem;">
                    @if($ai_live)
                        <div style="font-size: 0.7rem; display: flex; align-items: center; gap: 0.5rem; color: #10b981; padding: 0.5rem; background: rgba(16, 185, 129, 0.1); border-radius: 8px;">
                            <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block;"></span>
                            AI CORE: LIVE MODE
                        </div>
                    @else
                        <div style="font-size: 0.7rem; display: flex; align-items: center; gap: 0.5rem; color: #f59e0b; padding: 0.5rem; background: rgba(245, 158, 11, 0.1); border-radius: 8px;">
                            <span style="width: 8px; height: 8px; background: #f59e0b; border-radius: 50%; display: inline-block;"></span>
                            AI CORE: MOCK MODE
                        </div>
                    @endif
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <form method="POST" action="/logout">
                    @csrf
                    <button class="btn btn-outline" style="width: 100%; border-color: rgba(239, 68, 68, 0.3); color: #ef4444;">
                        🚪 Logout
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-nav">
                <div class="user-pill">
                    <span style="margin-right: 0.5rem">👤</span>
                    {{ auth()->user()->name }}
                    <span class="badge {{ auth()->user()->status === 'active' ? 'badge-active' : 'badge-pending' }}" style="margin-left: 1rem">
                        {{ auth()->user()->status }}
                    </span>
                </div>
            </header>
            
            <div class="content-body">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
