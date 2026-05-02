<x-app-layout>
    <div class="dashboard-header-wrap">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem">{{ __('ui.hello') }}, {{ auth()->user()->name }}! 👋</h1>
            <p style="color: var(--text-muted)">{!! __('ui.welcome_back_desc', ['band' => auth()->user()->target_band ?? 'N/A']) !!}</p>
        </div>
        <div class="glass-card level-badge-card">
            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px">{{ __('ui.practice') }}</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: var(--primary)">Level {{ $levelData['level'] }}</div>
        </div>
    </div>

    <!-- Progress & Analytics Grid -->
    <div class="dashboard-analytics-grid">
        <!-- XP & Goals -->
        <div class="glass-card">
            <h3 style="margin-bottom: 1.5rem">{{ __('ui.xp_progress') }}</h3>
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem">
                <div style="flex: 1; height: 12px; background: var(--bg-main); border-radius: 6px; overflow: hidden">
                    <div style="width: {{ $levelData['percent'] }}%; height: 100%; background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%); transition: width 1s ease-out"></div>
                </div>
                <span style="font-weight: 700; color: var(--primary); white-space: nowrap;">{{ $levelData['current_xp'] }} / {{ $levelData['xp_to_next'] }} XP</span>
            </div>
            
            <div class="dashboard-stats-row">
                <div style="flex: 1; background: var(--bg-main); padding: 1rem; border-radius: 12px; text-align: center">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.25rem">{{ __('ui.accuracy') }}</div>
                    <div style="font-size: 1.25rem; font-weight: 700">{{ $accuracy }}%</div>
                </div>
                <div style="flex: 1; background: var(--bg-main); padding: 1rem; border-radius: 12px; text-align: center">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.25rem">{{ __('ui.xp_earned') }}</div>
                    <div style="font-size: 1.25rem; font-weight: 700">{{ auth()->user()->xp ?? 0 }}</div>
                </div>
                <div style="flex: 1; background: var(--bg-main); padding: 1rem; border-radius: 12px; text-align: center">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.25rem">{{ __('ui.burning_streaks') }}</div>
                    <div style="font-size: 1.25rem; font-weight: 700; color: var(--accent)">{{ auth()->user()->streak ?? 0 }}🔥</div>
                </div>
            </div>
        </div>

        <!-- Accuracy Chart -->
        <div class="glass-card" style="display: flex; flex-direction: column; align-items: center; justify-content: center">
            <h3 style="margin-bottom: 1rem; width: 100%">{{ __('ui.performance') }}</h3>
            <div style="width: 100%; max-width: 180px">
                <canvas id="accuracyChart"></canvas>
            </div>
            <div style="margin-top: 1rem; text-align: center; font-size: 0.8rem; color: var(--text-muted)">
                {{ __('ui.performance_desc') }}
            </div>
        </div>
    </div>

    <!-- Practice Mode Grid -->
    <h3 style="margin-bottom: 1.5rem">{{ __('ui.practice_skills') }}</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 3rem">
        @foreach([
            [__('ui.speaking'), '🎙️', __('ui.talk_to_ai'), 'speaking', 'primary'],
            [__('ui.writing'), '✍️', __('ui.submit_essays'), 'writing', 'outline'],
            [__('ui.reading'), '📖', __('ui.improve_speed'), 'reading', 'outline'],
            [__('ui.listening'), '🎧', __('ui.listen_accents'), 'listening', 'outline']
        ] as [$title, $icon, $desc, $skill, $btnType])
            <div class="glass-card" style="text-align: center; padding: 1.5rem">
                <div style="font-size: 2.5rem; margin-bottom: 1rem">{{ $icon }}</div>
                <h4 style="margin-bottom: 0.5rem">{{ $title }}</h4>
                <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 1.25rem">{{ $desc }}</p>
                <a href="/student/practice?skill={{ $skill }}" class="btn btn-{{ $btnType }}" style="width: 100%; padding: 0.5rem">{{ __('ui.practice_btn') }}</a>
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
                    labels: [
                        '{{ __('ui.reading') }}', 
                        '{{ __('ui.listening') }}', 
                        '{{ __('ui.writing') }}', 
                        '{{ __('ui.speaking') }}'
                    ],
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
    <style>
        .dashboard-header-wrap {
            margin-bottom: 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .level-badge-card {
            padding: 1rem 2rem;
            text-align: center;
            border-color: var(--primary);
        }
        .dashboard-analytics-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        .dashboard-stats-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        @media (max-width: 768px) {
            .dashboard-header-wrap {
                flex-direction: column;
                align-items: stretch;
            }
            .dashboard-analytics-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</x-app-layout>
