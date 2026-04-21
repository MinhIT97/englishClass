<x-app-layout>
    <div class="leaderboard-header">
        <h1 class="page-title">Global Leaderboard</h1>
        <p class="page-subtitle">See how you rank against other IELTS aspirants world-wide.</p>
    </div>

    <div class="leaderboard-grid">
        <!-- Top XP -->
        <div class="glass-card table-card" style="padding: 0">
            <div class="card-header">
                <h3 style="display: flex; align-items: center; gap: 0.5rem; margin: 0">
                    <span>🏆</span> Top Hall of Fame (XP)
                </h3>
            </div>
            
            <div class="table-responsive">
                <table class="leaderboard-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Student</th>
                            <th class="hide-mobile">Target</th>
                            <th style="text-align: right">Total XP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topStudents as $index => $student)
                            <tr class="{{ $student->id === auth()->id() ? 'current-user-row' : '' }}">
                                <td class="rank-col">
                                    @if($index === 0) 🥇 @elseif($index === 1) 🥈 @elseif($index === 2) 🥉 @else #{{ $index + 1 }} @endif
                                </td>
                                <td class="student-col">
                                    <div class="student-name">{{ $student->name }}</div>
                                    <div class="student-meta">
                                        <span class="role">{{ $student->id === auth()->id() ? '(You)' : 'Member' }}</span>
                                        <span class="target-mobile"> • Band {{ $student->target_band ?? 'N/A' }}</span>
                                    </div>
                                </td>
                                <td class="hide-mobile">
                                    <span class="badge" style="background: var(--glass); color: var(--primary); white-space: nowrap;">Band {{ $student->target_band ?? 'N/A' }}</span>
                                </td>
                                <td class="xp-col">
                                    {{ number_format($student->xp) }} XP
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Streaks Sidebar -->
        <div class="sidebar-grid">
            <!-- Burning Streaks -->
            <div class="glass-card" style="border-color: rgba(16, 185, 129, 0.3)">
                <h3 style="margin-bottom: 1.5rem; font-size: 1.125rem">🔥 Burning Streaks</h3>
                <div class="streak-list">
                    @foreach($activeStreaks as $streakUser)
                        <div class="streak-item">
                            <span class="streak-name">{{ $streakUser->name }}</span>
                            <span class="streak-value">{{ $streakUser->streak }} Days</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Keep it up -->
            <div class="glass-card active-card">
                <h3 style="color: white; margin-bottom: 0.5rem">Keep it up!</h3>
                <p style="color: rgba(255, 255, 255, 0.8); font-size: 0.875rem; margin-bottom: 1.5rem">
                    Your current streak is <strong>{{ auth()->user()->streak }} days</strong>. Complete a drill today to keep the fire burning!
                </p>
                <a href="{{ route('student.practice.index') }}" class="btn btn-outline" style="width: 100%; border-color: white; color: white">Practice Now</a>
            </div>
        </div>
    </div>

    <style>
        .leaderboard-header { margin-bottom: 2rem; }
        .page-title { font-size: 2rem; margin-bottom: 0.5rem; }
        .page-subtitle { color: var(--text-muted); }

        .leaderboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            align-items: start;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--glass-border);
        }

        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
        }

        .leaderboard-table th {
            text-align: left;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--glass-border);
        }

        .leaderboard-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--glass-border);
            vertical-align: middle;
        }

        .current-user-row { background: rgba(99, 102, 241, 0.1); }
        .rank-col { font-weight: 700; width: 60px; }
        .student-name { font-weight: 600; font-size: 1rem; }
        .student-meta { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem; }
        .target-mobile { display: none; }
        .xp-col { text-align: right; font-weight: 700; color: var(--accent); }

        .streak-list { display: flex; flex-direction: column; gap: 1rem; }
        .streak-item { display: flex; justify-content: space-between; align-items: center; }
        .streak-name { font-size: 0.875rem; }
        .streak-value { font-weight: 700; color: #f59e0b; }

        .active-card {
            background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
            border: none;
        }

        @media (max-width: 991px) {
            .leaderboard-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 640px) {
            .page-title { font-size: 1.5rem; }
            .leaderboard-table th, .leaderboard-table td { padding: 0.75rem 1rem; }
            .rank-col { width: 50px; }
            .hide-mobile { display: none; }
            .target-mobile { display: inline; }
            .student-name { font-size: 0.9375rem; }
            .xp-col { font-size: 0.875rem; }
        }
    </style>
</x-app-layout>
