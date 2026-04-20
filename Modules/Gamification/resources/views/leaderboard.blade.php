<x-app-layout>
    <div style="margin-bottom: 2.5rem">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem">Global Leaderboard</h1>
        <p style="color: var(--text-muted)">See how you rank against other IELTS aspirants world-wide.</p>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem">
        <!-- Top XP -->
        <div class="glass-card" style="padding: 0">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--glass-border)">
                <h3 style="display: flex; align-items: center; gap: 0.5rem">
                    <span>🏆</span> Top Hall of Fame (XP)
                </h3>
            </div>
            
            <table style="width: 100%; border-collapse: collapse">
                <thead>
                    <tr style="text-align: left; color: var(--text-muted); font-size: 0.825rem; border-bottom: 1px solid var(--glass-border)">
                        <th style="padding: 1rem 1.5rem">Rank</th>
                        <th style="padding: 1rem 1.5rem">Student</th>
                        <th style="padding: 1rem 1.5rem">Target</th>
                        <th style="padding: 1rem 1.5rem; text-align: right">Total XP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topStudents as $index => $student)
                        <tr style="border-bottom: 1px solid var(--glass-border); {{ $student->id === auth()->id() ? 'background: rgba(99, 102, 241, 0.1)' : '' }}">
                            <td style="padding: 1.25rem 1.5rem">
                                @if($index === 0) 🥇 @elseif($index === 1) 🥈 @elseif($index === 2) 🥉 @else #{{ $index + 1 }} @endif
                            </td>
                            <td style="padding: 1.25rem 1.5rem">
                                <div style="font-weight: 600">{{ $student->name }}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted)">{{ $student->id === auth()->id() ? '(You)' : 'Member' }}</div>
                            </td>
                            <td style="padding: 1.25rem 1.5rem">
                                <span class="badge" style="background: var(--glass); color: var(--primary)">Band {{ $student->target_band ?? 'N/A' }}</span>
                            </td>
                            <td style="padding: 1.25rem 1.5rem; text-align: right; font-weight: 700; color: var(--accent)">
                                {{ number_format($student->xp) }} XP
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Streaks Sidebar -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem">
            <div class="glass-card" style="border-color: rgba(16, 185, 129, 0.3)">
                <h3 style="margin-bottom: 1.5rem; font-size: 1.125rem">🔥 Burning Streaks</h3>
                <div style="display: flex; flex-direction: column; gap: 1rem">
                    @foreach($activeStreaks as $streakUser)
                        <div style="display: flex; justify-content: space-between; align-items: center">
                            <span style="font-size: 0.875rem">{{ $streakUser->name }}</span>
                            <span style="font-weight: 700; color: #f59e0b">{{ $streakUser->streak }} Days</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="glass-card" style="background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%); border: none">
                <h3 style="color: white; margin-bottom: 0.5rem">Keep it up!</h3>
                <p style="color: rgba(255, 255, 255, 0.8); font-size: 0.875rem; margin-bottom: 1.5rem">Your current streak is <strong>{{ auth()->user()->streak }} days</strong>. Complete a drill today to keep the fire burning!</p>
                <a href="{{ route('student.practice.index') }}" class="btn btn-outline" style="width: 100%; border-color: white; color: white">Practice Now</a>
            </div>
        </div>
    </div>
</x-app-layout>
