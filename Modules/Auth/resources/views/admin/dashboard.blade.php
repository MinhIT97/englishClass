<x-app-layout>
    <div style="margin-bottom: 2.5rem">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem">God-View Dashboard</h1>
        <p style="color: var(--text-muted)">Welcome back, Admin. Here is the platform overview.</p>
    </div>

    <!-- Stats Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 3rem">
        <div class="glass-card" style="padding: 1.5rem">
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 0.5rem">Total Students</p>
            <h3 style="font-size: 2rem; color: var(--text-main)">{{ $stats['total_students'] }}</h3>
        </div>
        <div class="glass-card" style="padding: 1.5rem">
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 0.5rem">Active Students</p>
            <h3 style="font-size: 2rem; color: var(--accent)">{{ $stats['active_students'] }}</h3>
        </div>
        <div class="glass-card" style="padding: 1.5rem; border-color: rgba(245, 158, 11, 0.3)">
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 0.5rem">Pending Approvals</p>
            <h3 style="font-size: 2rem; color: #f59e0b">{{ $stats['pending_approvals'] }}</h3>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="glass-card" style="border-color: var(--primary)">
        <h3 style="margin-bottom: 1.5rem">Quick Intelligence</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem">
            <a href="/admin/users" class="btn btn-primary" style="gap: 0.5rem">
                <span>👥</span> View Access Requests
            </a>
            <a href="/admin/questions" class="btn btn-outline" style="gap: 0.5rem">
                <span>📝</span> Manage Question Bank
            </a>
        </div>
    </div>
</x-app-layout>
