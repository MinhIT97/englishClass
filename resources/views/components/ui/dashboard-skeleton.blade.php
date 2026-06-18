{{--
    Dashboard skeleton — replaces the entire page during initial load.
    Mirrors the layout of student.dashboard and admin.dashboard so
    the perceived load time drops to ~0.
--}}
@props(['role' => 'student']) {{-- 'student' | 'teacher' | 'admin' --}}

<div class="dashboard-skeleton">
    <div class="skeleton-loader" style="height: 32px; width: 240px; margin-bottom: 0.5rem"></div>
    <div class="skeleton-loader" style="height: 16px; width: 380px; margin-bottom: 2rem"></div>

    {{-- Stat row --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem">
        @for($i = 0; $i < 3; $i++)
            <x-ui.skeleton variant="stat" />
        @endfor
    </div>

    {{-- Two-column main content --}}
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem">
        <div>
            <x-ui.skeleton variant="card" />
            <x-ui.skeleton variant="card" />
        </div>
        <div>
            <x-ui.skeleton variant="card" />
        </div>
    </div>
</div>