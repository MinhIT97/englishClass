<x-app-layout>
    <div style="margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: center">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem">Hello, {{ auth()->user()->name }}! 👋</h1>
            <p style="color: var(--text-muted)">Welcome back. Let's reach for that <strong>Band {{ auth()->user()->target_band ?? 'N/A' }}</strong>!</p>
        </div>
        <div class="glass-card" style="padding: 1rem 2rem; text-align: center; border-color: var(--primary)">
            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px">Luyện tập</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: var(--primary)">Level {{ $levelData['level'] }}</div>
        </div>
    </div>

    <!-- Progress & Analytics Grid -->
    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 1.5rem; margin-bottom: 2.5rem">
        <!-- XP & Goals -->
        <div class="glass-card">
            <h3 style="margin-bottom: 1.5rem">XP Progress</h3>
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem">
                <div style="flex: 1; height: 12px; background: var(--bg-main); border-radius: 6px; overflow: hidden">
                    <div style="width: {{ $levelData['percent'] }}%; height: 100%; background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%); transition: width 1s ease-out"></div>
                </div>
                <span style="font-weight: 700; color: var(--primary)">{{ $levelData['current_xp'] }} / {{ $levelData['xp_to_next'] }} XP</span>
            </div>
            
            <div style="display: flex; gap: 1rem">
                <div style="flex: 1; background: var(--bg-main); padding: 1rem; border-radius: 12px; text-align: center">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.25rem">Accuracy</div>
                    <div style="font-size: 1.25rem; font-weight: 700">{{ $accuracy }}%</div>
                </div>
                <div style="flex: 1; background: var(--bg-main); padding: 1rem; border-radius: 12px; text-align: center">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.25rem">XP Earned</div>
                    <div style="font-size: 1.25rem; font-weight: 700">{{ auth()->user()->xp ?? 0 }}</div>
                </div>
                <div style="flex: 1; background: var(--bg-main); padding: 1rem; border-radius: 12px; text-align: center">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.25rem">Streak</div>
                    <div style="font-size: 1.25rem; font-weight: 700; color: var(--accent)">{{ auth()->user()->streak ?? 0 }}🔥</div>
                </div>
            </div>
        </div>

        <!-- Accuracy Chart -->
        <div class="glass-card" style="display: flex; flex-direction: column; align-items: center">
            <h3 style="margin-bottom: 1rem; width: 100%">Performance</h3>
            <div style="width: 100%; max-width: 180px">
                <canvas id="accuracyChart"></canvas>
            </div>
            <div style="margin-top: 1rem; text-align: center; font-size: 0.8rem; color: var(--text-muted)">
                Based on your last 100 questions.
            </div>
        </div>
    </div>

    <!-- Practice Mode Grid -->
    <h3 style="margin-bottom: 1.5rem">Practice Skills</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 3rem">
        @foreach([
            ['Speaking', '🎙️', 'Talk to Gemini AI', 'speaking', 'primary'],
            ['Writing', '✍️', 'Submit your essays', 'writing', 'outline'],
            ['Reading', '📖', 'Improve your speed', 'reading', 'outline'],
            ['Listening', '🎧', 'Listen to accents', 'listening', 'outline']
        ] as [$title, $icon, $desc, $skill, $btnType])
            <div class="glass-card" style="text-align: center; padding: 1.5rem">
                <div style="font-size: 2.5rem; margin-bottom: 1rem">{{ $icon }}</div>
                <h4 style="margin-bottom: 0.5rem">{{ $title }}</h4>
                <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 1.25rem">{{ $desc }}</p>
                <a href="/student/practice?skill={{ $skill }}" class="btn btn-{{ $btnType }}" style="width: 100%; padding: 0.5rem">Practice</a>
            </div>
        @endforeach
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('accuracyChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Reading', 'Listening', 'Writing', 'Speaking'],
                    datasets: [{
                        data: [
                            {{ $skillStats['reading'] }}, 
                            {{ $skillStats['listening'] }}, 
                            {{ $skillStats['writing'] }}, 
                            {{ $skillStats['speaking'] }}
                        ],
                        backgroundColor: [
                            '#6366f1', // primary
                            '#f59e0b', // accent
                            '#10b981', // success
                            '#ec4899'  // pink
                        ],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    cutout: '70%',
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        });
    </script>
</x-app-layout>
