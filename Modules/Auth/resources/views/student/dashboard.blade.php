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

    <div class="dashboard-analytics-grid">
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

        <div class="glass-card performance-card">
            <h3 style="margin-bottom: 1rem; width: 100%">{{ __('ui.performance') }}</h3>
            <div class="performance-chart-wrap">
                <canvas id="accuracyChart"></canvas>
                <div class="performance-chart-center">
                    <strong>{{ $accuracy }}%</strong>
                    <span>overall</span>
                </div>
            </div>
            <div class="performance-summary">
                <div class="performance-summary-pill">
                    <span class="performance-dot performance-dot-correct"></span>
                    <strong>{{ $correctAnswers }}</strong>
                    <small>correct</small>
                </div>
                <div class="performance-summary-pill">
                    <span class="performance-dot performance-dot-incorrect"></span>
                    <strong>{{ $incorrectAnswers }}</strong>
                    <small>incorrect</small>
                </div>
            </div>
            <div class="performance-caption">
                Overall accuracy across your recorded practice answers.
            </div>
        </div>
    </div>

    <div class="glass-card skill-performance-card">
        <div class="skill-performance-header">
            <div>
                <h3 style="margin-bottom: 0.35rem">{{ __('ui.practice_skills') }}</h3>
                <p class="skill-performance-subtitle">Each percentage is calculated independently for that skill.</p>
            </div>
        </div>
        <div class="skill-performance-chart-wrap">
            <canvas id="skillPerformanceChart"></canvas>
        </div>
        <p class="skill-performance-note">
            Formula: <strong>correct answers / total attempts</strong> for each skill. These percentages are not compared against each other as parts of one whole.
        </p>
        <div class="skill-performance-meta">
            @foreach([
                ['label' => __('ui.reading'), 'value' => $skillStats['reading'], 'attempts' => $skillAttempts['reading'], 'correct' => $skillCorrectCounts['reading']],
                ['label' => __('ui.listening'), 'value' => $skillStats['listening'], 'attempts' => $skillAttempts['listening'], 'correct' => $skillCorrectCounts['listening']],
                ['label' => __('ui.writing'), 'value' => $skillStats['writing'], 'attempts' => $skillAttempts['writing'], 'correct' => $skillCorrectCounts['writing']],
                ['label' => __('ui.speaking'), 'value' => $skillStats['speaking'], 'attempts' => $skillAttempts['speaking'], 'correct' => $skillCorrectCounts['speaking']],
            ] as $item)
                <div class="skill-performance-pill">
                    <strong>{{ $item['label'] }}</strong>
                    <span>{{ $item['value'] }}%</span>
                    <small>{{ $item['correct'] }} correct / {{ $item['attempts'] }} attempt{{ $item['attempts'] === 1 ? '' : 's' }}</small>
                </div>
            @endforeach
        </div>
    </div>

    <h3 style="margin-bottom: 1.5rem">{{ __('ui.practice_skills') }}</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 3rem">
        @foreach([
            [__('ui.speaking'), '🗣️', __('ui.talk_to_ai'), route('student.speaking.index'), 'primary', __('ui.start_interview')],
            [__('ui.writing'), '✍️', __('ui.submit_essays'), route('student.writing.index'), 'outline', __('ui.analyze_btn')],
            [__('ui.reading'), '📖', __('ui.improve_speed'), route('student.practice.drill', 'reading'), 'outline', __('ui.start_training')],
            [__('ui.listening'), '🎧', __('ui.listen_accents'), route('student.practice.drill', 'listening'), 'outline', __('ui.start_training')]
        ] as [$title, $icon, $desc, $href, $btnType, $cta])
            <div class="glass-card" style="text-align: center; padding: 1.5rem">
                <div style="font-size: 2.5rem; margin-bottom: 1rem">{{ $icon }}</div>
                <h4 style="margin-bottom: 0.5rem">{{ $title }}</h4>
                <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 1.25rem">{{ $desc }}</p>
                <a href="{{ $href }}" class="btn btn-{{ $btnType }}" style="width: 100%; padding: 0.5rem">{{ $cta }}</a>
            </div>
        @endforeach
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const accuracyCtx = document.getElementById('accuracyChart').getContext('2d');
            const skillCtx = document.getElementById('skillPerformanceChart').getContext('2d');
            const skillValueLabelPlugin = {
                id: 'skillValueLabelPlugin',
                afterDatasetsDraw(chart) {
                    const { ctx } = chart;
                    const meta = chart.getDatasetMeta(0);
                    const values = chart.data.datasets[0].data;

                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    ctx.font = '600 12px Inter';

                    meta.data.forEach((bar, index) => {
                        const value = Number(values[index] ?? 0);
                        const y = value === 0 ? chart.scales.y.getPixelForValue(0) - 6 : bar.y - 6;
                        ctx.fillStyle = value === 0 ? '#94a3b8' : '#f8fafc';
                        ctx.fillText(`${value}%`, bar.x, y);
                    });

                    ctx.restore();
                }
            };

            new Chart(accuracyCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Correct', 'Incorrect'],
                    datasets: [{
                        data: [
                            {{ $correctAnswers }},
                            {{ $incorrectAnswers }}
                        ],
                        backgroundColor: ['#6366f1', 'rgba(148, 163, 184, 0.28)'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    cutout: '70%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = {{ $totalAnswers }};
                                    const value = Number(context.raw ?? 0);
                                    const percent = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${context.label}: ${value} (${percent}%)`;
                                }
                            }
                        }
                    }
                }
            });

            new Chart(skillCtx, {
                type: 'bar',
                data: {
                    labels: [
                        '{{ __('ui.reading') }}',
                        '{{ __('ui.listening') }}',
                        '{{ __('ui.writing') }}',
                        '{{ __('ui.speaking') }}'
                    ],
                    datasets: [{
                        label: '{{ __('ui.accuracy') }}',
                        data: [
                            {{ $skillStats['reading'] }},
                            {{ $skillStats['listening'] }},
                            {{ $skillStats['writing'] }},
                            {{ $skillStats['speaking'] }}
                        ],
                        backgroundColor: [
                            'rgba(99, 102, 241, 0.85)',
                            'rgba(245, 158, 11, 0.85)',
                            'rgba(16, 185, 129, 0.85)',
                            'rgba(236, 72, 153, 0.85)'
                        ],
                        borderColor: ['#6366f1', '#f59e0b', '#10b981', '#ec4899'],
                        borderWidth: 1.5,
                        borderRadius: 12,
                        borderSkipped: false,
                        barThickness: 42
                    }]
                },
                plugins: [skillValueLabelPlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.formattedValue}%`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                stepSize: 20,
                                callback: function(value) {
                                    return `${value}%`;
                                }
                            },
                            grid: {
                                color: 'rgba(148, 163, 184, 0.12)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
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

        .performance-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .performance-chart-wrap {
            position: relative;
            width: 100%;
            max-width: 220px;
            margin: 0 auto;
        }

        .performance-chart-center {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .performance-chart-center strong {
            font-size: 1.8rem;
            line-height: 1;
        }

        .performance-chart-center span {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-top: 0.35rem;
        }

        .performance-summary {
            display: flex;
            gap: 0.75rem;
            width: 100%;
            margin-top: 1rem;
        }

        .performance-summary-pill {
            flex: 1;
            padding: 0.9rem 1rem;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--glass-border);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.15rem;
            text-align: center;
        }

        .performance-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            display: inline-block;
            margin-bottom: 0.2rem;
        }

        .performance-dot-correct {
            background: #6366f1;
        }

        .performance-dot-incorrect {
            background: rgba(148, 163, 184, 0.75);
        }

        .performance-summary-pill strong {
            font-size: 1.1rem;
        }

        .performance-summary-pill small {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .performance-caption {
            margin-top: 0.9rem;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .skill-performance-card {
            margin-bottom: 2.5rem;
        }

        .skill-performance-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .skill-performance-subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .skill-performance-chart-wrap {
            position: relative;
            width: 100%;
            height: 320px;
        }

        .skill-performance-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
            margin-top: 1.25rem;
        }

        .skill-performance-note {
            margin-top: 1rem;
            color: var(--text-muted);
            font-size: 0.85rem;
            line-height: 1.6;
        }

        .skill-performance-pill {
            padding: 0.9rem 1rem;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--glass-border);
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .skill-performance-pill strong {
            font-size: 0.85rem;
        }

        .skill-performance-pill span {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .skill-performance-pill small {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        @media (max-width: 768px) {
            .dashboard-header-wrap {
                flex-direction: column;
                align-items: stretch;
            }

            .dashboard-analytics-grid {
                grid-template-columns: 1fr;
            }

            .skill-performance-chart-wrap {
                height: 260px;
            }
        }
    </style>
</x-app-layout>
