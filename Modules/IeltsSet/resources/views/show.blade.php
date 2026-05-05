<x-app-layout>
    <div class="set-show-header">
        <div>
            <div class="set-show-kicker">IELTS Set</div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem">{{ $set->title }}</h1>
            <p style="color: var(--text-muted); max-width: 760px">{{ $set->description }}</p>
        </div>
        <form method="POST" action="{{ route('student.sets.start', $set) }}">
            @csrf
            <button class="btn btn-primary" style="padding: 0.9rem 2rem">
                {{ $latestAttempt ? 'Resume Set' : 'Start Set' }}
            </button>
        </form>
    </div>

    <div class="set-overview-grid">
        <div class="glass-card">
            <h3 style="margin-bottom: 1rem">Overview</h3>
            <div class="set-overview-meta">
                <div><strong>Band:</strong> {{ $set->target_band }}</div>
                <div><strong>Topic:</strong> {{ $set->topic }}</div>
                <div><strong>Difficulty:</strong> {{ ucfirst($set->difficulty) }}</div>
                <div><strong>Duration:</strong> {{ $set->duration_minutes }} minutes</div>
                <div><strong>Questions:</strong> {{ $set->total_questions }}</div>
                <div><strong>Sections:</strong> {{ $set->sections->count() }}</div>
            </div>
        </div>

        <div class="glass-card">
            <h3 style="margin-bottom: 1rem">Set Progress</h3>
            @php
                $totalActiveSeconds = $progress['total_active_seconds'] ?? 0;
                $progressHours = intdiv($totalActiveSeconds, 3600);
                $progressMinutes = intdiv($totalActiveSeconds % 3600, 60);
                $progressSeconds = $totalActiveSeconds % 60;
            @endphp
            <div class="set-progress-grid">
                <div class="set-progress-stat">
                    <strong>{{ $progress['completed_sections'] }}/{{ $progress['total_sections'] }}</strong>
                    <span>Sections completed</span>
                </div>
                <div class="set-progress-stat">
                    <strong>{{ $latestAttempt?->score_percent !== null ? number_format((float) $latestAttempt->score_percent, 0) . '%' : 'Pending' }}</strong>
                    <span>Auto-scored accuracy</span>
                </div>
                <div class="set-progress-stat">
                    <strong>{{ $progressHours > 0 ? sprintf('%02d:%02d:%02d', $progressHours, $progressMinutes, $progressSeconds) : sprintf('%02d:%02d', $progressMinutes, $progressSeconds) }}</strong>
                    <span>Tracked active time</span>
                </div>
            </div>
            <p style="color: var(--text-muted); margin-top: 1rem">
                A full IELTS set should cover reading, listening, writing, and speaking. Reading, listening, and writing are submitted inside the set. Speaking is completed through the AI speaking simulator and then marked back into the same set attempt.
            </p>
            @if($latestAttempt)
                <div class="set-latest-attempt">
                    Latest status: <strong>{{ str_replace('_', ' ', $latestAttempt->status) }}</strong>
                    @if($latestAttempt->started_at)
                        <div
                            id="set-attempt-started"
                            data-started-at="{{ $latestAttempt->started_at->toIso8601String() }}"
                            style="font-size: 0.8rem; margin-top: 0.35rem"
                        >
                            Started {{ $latestAttempt->started_at->diffForHumans() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if($attemptHistory->isNotEmpty())
        <div class="glass-card" style="margin-bottom: 2rem">
            <h3 style="margin-bottom: 1rem">Attempt History</h3>
            <div class="set-history-list">
                @foreach($attemptHistory as $attempt)
                    <div class="set-history-item">
                        <div>
                            <strong>{{ ucfirst(str_replace('_', ' ', $attempt->status)) }}</strong>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem">
                                Started {{ $attempt->started_at?->diffForHumans() ?? 'N/A' }}
                            </div>
                        </div>
                        <div style="text-align: right">
                            <strong>{{ $attempt->score_percent !== null ? number_format((float) $attempt->score_percent, 0) . '%' : 'Pending' }}</strong>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem">
                                {{ $attempt->submitted_at ? 'Submitted ' . $attempt->submitted_at->diffForHumans() : 'Not submitted yet' }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="set-sections-list">
        @foreach($set->sections as $section)
            @php
                $sectionState = $progress['sections'][$section->id] ?? [
                    'status' => 'not_started',
                    'answered_count' => 0,
                    'question_count' => $section->questions->count(),
                    'correct_count' => 0,
                    'active_seconds' => 0,
                    'score_percent' => null,
                ];
                $sectionSeconds = (int) ($sectionState['active_seconds'] ?? 0);
                $sectionHours = intdiv($sectionSeconds, 3600);
                $sectionMinutes = intdiv($sectionSeconds % 3600, 60);
                $sectionRemainder = $sectionSeconds % 60;
            @endphp
            <div class="glass-card set-section-card">
                <div class="set-section-header">
                    <div>
                        <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: var(--primary); border: 1px solid rgba(99, 102, 241, 0.18)">
                            {{ strtoupper($section->skill) }}
                        </span>
                        <span class="badge set-status-badge set-status-{{ $sectionState['status'] }}" style="margin-left: 0.5rem">
                            {{ str_replace('_', ' ', $sectionState['status']) }}
                        </span>
                        <h3 style="margin-top: 0.85rem">{{ $loop->iteration }}. {{ $section->title }}</h3>
                    </div>
                    <div style="text-align: right; color: var(--text-muted); font-size: 0.85rem">
                        @if($section->time_limit_minutes)
                            <div>{{ $section->time_limit_minutes }} min</div>
                        @endif
                        <div>{{ $section->questions->count() }} prompts</div>
                    </div>
                </div>

                <p style="color: var(--text-muted); margin: 1rem 0 1.25rem">{{ $section->instructions }}</p>

                <div class="set-section-stats">
                    <div class="set-section-stat-chip">
                        <strong>
                            {{ $section->skill === 'speaking'
                                ? ($sectionState['status'] === 'completed' ? 'Done' : 'Pending')
                                : $sectionState['answered_count'] . '/' . $sectionState['question_count'] }}
                        </strong>
                        <span>{{ $section->skill === 'speaking' ? 'Completion' : 'Answered' }}</span>
                    </div>
                    <div class="set-section-stat-chip">
                        <strong>{{ $sectionState['score_percent'] !== null ? $sectionState['score_percent'] . '%' : 'Pending' }}</strong>
                        <span>{{ $section->skill === 'speaking' ? 'Scored outside set' : 'Section score' }}</span>
                    </div>
                    <div class="set-section-stat-chip">
                        <strong>{{ $sectionHours > 0 ? sprintf('%02d:%02d:%02d', $sectionHours, $sectionMinutes, $sectionRemainder) : sprintf('%02d:%02d', $sectionMinutes, $sectionRemainder) }}</strong>
                        <span>Active time</span>
                    </div>
                </div>

                <div class="set-question-preview-list">
                    @foreach($section->questions as $question)
                        <div class="set-question-preview">
                            <strong>Q{{ $loop->iteration }}.</strong>
                            <span>{{ \Illuminate\Support\Str::limit($question->content['question'] ?? $question->content['text'] ?? 'Question prompt', 140) }}</span>
                        </div>
                    @endforeach
                </div>

                <div style="margin-top: 1.25rem; display: flex; justify-content: flex-end">
                    <a
                        href="{{ route('student.sets.section', [$set, $section]) }}"
                        class="btn {{ $sectionState['status'] === 'completed' ? 'btn-outline' : 'btn-primary' }}"
                        style="padding: 0.8rem 1.4rem"
                    >
                        @if($sectionState['status'] === 'completed')
                            Review Section
                        @elseif($sectionState['status'] === 'in_progress')
                            Continue Section
                        @elseif($section->skill === 'speaking')
                            Start Speaking Section
                        @else
                            Work on This Section
                        @endif
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const startedEl = document.getElementById('set-attempt-started');
            if (!startedEl) {
                return;
            }

            const startedAt = new Date(startedEl.dataset.startedAt);

            function formatRelativeTime(date) {
                const elapsedSeconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));

                if (elapsedSeconds < 60) {
                    return `${elapsedSeconds} second${elapsedSeconds === 1 ? '' : 's'} ago`;
                }

                if (elapsedSeconds < 3600) {
                    const minutes = Math.floor(elapsedSeconds / 60);
                    const seconds = elapsedSeconds % 60;
                    return seconds > 0
                        ? `${minutes} minute${minutes === 1 ? '' : 's'} ${seconds} second${seconds === 1 ? '' : 's'} ago`
                        : `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
                }

                if (elapsedSeconds < 86400) {
                    const hours = Math.floor(elapsedSeconds / 3600);
                    const minutes = Math.floor((elapsedSeconds % 3600) / 60);
                    return minutes > 0
                        ? `${hours} hour${hours === 1 ? '' : 's'} ${minutes} minute${minutes === 1 ? '' : 's'} ago`
                        : `${hours} hour${hours === 1 ? '' : 's'} ago`;
                }

                const days = Math.floor(elapsedSeconds / 86400);
                return `${days} day${days === 1 ? '' : 's'} ago`;
            }

            function renderStartedTime() {
                startedEl.textContent = `Started ${formatRelativeTime(startedAt)}`;
            }

            renderStartedTime();
            window.setInterval(renderStartedTime, 1000);
        });
    </script>

    <style>
        .set-show-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .set-show-kicker {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--primary);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .set-overview-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .set-overview-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
            color: var(--text-muted);
        }

        .set-overview-meta strong {
            color: var(--text-main);
        }

        .set-progress-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .set-progress-stat {
            padding: 0.95rem 1rem;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .set-progress-stat strong {
            font-size: 1rem;
        }

        .set-progress-stat span {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        .set-latest-attempt {
            padding: 1rem;
            border-radius: 14px;
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.18);
            color: #10b981;
            margin-top: 1rem;
        }

        .set-sections-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .set-history-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .set-history-item {
            padding: 0.95rem 1rem;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
        }

        .set-section-card {
            padding: 1.5rem;
        }

        .set-section-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
        }

        .set-section-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.85rem;
            margin-bottom: 1rem;
        }

        .set-section-stat-chip {
            padding: 0.85rem 0.95rem;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .set-section-stat-chip strong {
            font-size: 0.95rem;
        }

        .set-section-stat-chip span {
            color: var(--text-muted);
            font-size: 0.78rem;
        }

        .set-question-preview-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .set-question-preview {
            padding: 0.95rem 1rem;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            display: flex;
            gap: 0.65rem;
            color: var(--text-muted);
        }

        .set-question-preview strong {
            color: var(--text-main);
            flex-shrink: 0;
        }

        .set-status-badge {
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .set-status-completed {
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.18);
        }

        .set-status-in_progress {
            background: rgba(245, 158, 11, 0.12);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.18);
        }

        .set-status-not_started {
            background: rgba(148, 163, 184, 0.12);
            color: #cbd5e1;
            border: 1px solid rgba(148, 163, 184, 0.18);
        }

        @media (max-width: 768px) {
            .set-overview-grid,
            .set-progress-grid,
            .set-section-stats {
                grid-template-columns: 1fr;
            }

            .set-overview-meta {
                grid-template-columns: 1fr;
            }

            .set-section-header {
                flex-direction: column;
            }
        }
    </style>
</x-app-layout>
