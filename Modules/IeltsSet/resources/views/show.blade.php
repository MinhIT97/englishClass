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
            <h3 style="margin-bottom: 1rem">Study Flow</h3>
            <p style="color: var(--text-muted); margin-bottom: 1rem">
                These sets are designed to keep revision focused. Work through each section in order and review the target skill before moving on.
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

    @if(isset($attemptHistory) && $attemptHistory->isNotEmpty())
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
            <div class="glass-card set-section-card">
                <div class="set-section-header">
                    <div>
                        <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: var(--primary); border: 1px solid rgba(99, 102, 241, 0.18)">
                            {{ strtoupper($section->skill) }}
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

                <div class="set-question-preview-list">
                    @foreach($section->questions as $question)
                        <div class="set-question-preview">
                            <strong>Q{{ $loop->iteration }}.</strong>
                            <span>{{ \Illuminate\Support\Str::limit($question->content['question'] ?? $question->content['text'] ?? 'Question prompt', 140) }}</span>
                        </div>
                    @endforeach
                </div>

                <div style="margin-top: 1.25rem; display: flex; justify-content: flex-end">
                    @if($section->skill === 'speaking')
                        <a href="{{ route('student.speaking.index') }}" class="btn btn-outline" style="padding: 0.8rem 1.4rem">
                            Open Speaking Module
                        </a>
                    @else
                        <a href="{{ route('student.sets.section', [$set, $section]) }}" class="btn btn-primary" style="padding: 0.8rem 1.4rem">
                            Work on This Section
                        </a>
                    @endif
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
            const locale = document.documentElement.lang?.startsWith('vi') ? 'vi' : 'en';
            function formatRelativeTime(date) {
                const elapsedSeconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));

                if (locale === 'vi') {
                    if (elapsedSeconds < 60) {
                        return `${elapsedSeconds} giây trước`;
                    }

                    if (elapsedSeconds < 3600) {
                        const minutes = Math.floor(elapsedSeconds / 60);
                        const seconds = elapsedSeconds % 60;
                        return seconds > 0
                            ? `${minutes} phút ${seconds} giây trước`
                            : `${minutes} phút trước`;
                    }

                    if (elapsedSeconds < 86400) {
                        const hours = Math.floor(elapsedSeconds / 3600);
                        const minutes = Math.floor((elapsedSeconds % 3600) / 60);
                        return minutes > 0
                            ? `${hours} giờ ${minutes} phút trước`
                            : `${hours} giờ trước`;
                    }

                    const days = Math.floor(elapsedSeconds / 86400);
                    return `${days} ngày trước`;
                }

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
                const prefix = locale === 'vi' ? 'Bắt đầu' : 'Started';
                startedEl.textContent = `${prefix} ${formatRelativeTime(startedAt)}`;
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

        .set-latest-attempt {
            padding: 1rem;
            border-radius: 14px;
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.18);
            color: #10b981;
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

        @media (max-width: 768px) {
            .set-overview-grid {
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
