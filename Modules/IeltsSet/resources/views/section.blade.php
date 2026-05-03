<x-app-layout>
    <div class="set-section-page-header">
        <div>
            <div class="set-show-kicker">{{ $set->title }}</div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem">{{ $section->title }}</h1>
            <p style="color: var(--text-muted); max-width: 760px">{{ $section->instructions }}</p>
        </div>
        <a href="{{ route('student.sets.show', $set) }}" class="btn btn-outline" style="padding: 0.9rem 1.5rem">
            Back to Set
        </a>
    </div>

    <form method="POST" action="{{ route('student.sets.section.submit', [$set, $section]) }}">
        @csrf
        <input type="hidden" name="active_seconds_delta" id="active-seconds-delta" value="0">

        <div class="set-workspace-meta">
            <div class="glass-card set-workspace-stat">
                <strong>{{ strtoupper($section->skill) }}</strong>
                <span>Skill</span>
            </div>
            <div class="glass-card set-workspace-stat">
                <strong>{{ $section->questions->count() }}</strong>
                <span>Questions</span>
            </div>
            @if($section->time_limit_minutes)
                <div class="glass-card set-workspace-stat">
                    <strong>{{ $section->time_limit_minutes }} min</strong>
                    <span>Suggested time</span>
                </div>
            @endif
            @if($sectionTimer && !empty($sectionTimer['started_at']))
                <div
                    class="glass-card set-workspace-stat"
                    id="section-timer-card"
                    data-started-at="{{ $sectionTimer['started_at'] }}"
                    data-active-seconds="{{ (int) ($sectionTimer['active_seconds'] ?? 0) }}"
                    data-sync-url="{{ route('student.sets.section.time', [$set, $section]) }}"
                    data-csrf-token="{{ csrf_token() }}"
                >
                    <strong id="section-timer-display">00:00</strong>
                    <span>Elapsed time</span>
                </div>
            @endif
        </div>

        <div class="set-question-stack">
            @foreach($section->questions as $question)
                @php
                    $questionPrompt = $question->content['question'] ?? $question->content['text'] ?? 'Question prompt';
                    $saved = $savedAnswers[$question->id] ?? null;
                @endphp
                <div class="glass-card set-question-card">
                    <div class="set-question-card-header">
                        <div>
                            <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: var(--primary); border: 1px solid rgba(99, 102, 241, 0.18)">
                                {{ strtoupper($question->type) }}
                            </span>
                            <h3 style="margin-top: 0.85rem">Question {{ $loop->iteration }}</h3>
                        </div>
                        <div style="text-align: right; color: var(--text-muted); font-size: 0.8rem">
                            <div>{{ ucfirst($question->difficulty) }}</div>
                            <div>{{ $question->topic }}</div>
                        </div>
                    </div>

                    @if($section->skill === 'listening' && isset($question->content['audio_path']))
                        <div class="set-audio-box">
                            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem">
                                Listen to the recording before answering.
                            </div>
                            <audio controls style="width: 100%">
                                <source src="{{ $question->content['audio_path'] }}" type="audio/mpeg">
                            </audio>
                        </div>
                    @endif

                    <div class="set-question-prompt">
                        {!! nl2br(e($questionPrompt)) !!}
                    </div>

                    @if($question->type === 'mcq' && isset($question->content['options']))
                        <div class="set-options-list">
                            @foreach($question->content['options'] as $option)
                                <label class="set-option-item">
                                    <input
                                        type="radio"
                                        name="answers[{{ $question->id }}]"
                                        value="{{ $option }}"
                                        {{ old("answers.{$question->id}", $saved?->answer_text) === $option ? 'checked' : '' }}
                                    >
                                    <span>{{ $option }}</span>
                                </label>
                            @endforeach
                        </div>
                    @elseif($section->skill === 'writing')
                        <textarea
                            name="answers[{{ $question->id }}]"
                            class="form-control"
                            style="min-height: 220px; resize: vertical"
                            placeholder="Write your response here..."
                        >{{ old("answers.{$question->id}", $saved?->answer_text) }}</textarea>
                    @else
                        <input
                            type="text"
                            name="answers[{{ $question->id }}]"
                            class="form-control"
                            value="{{ old("answers.{$question->id}", $saved?->answer_text) }}"
                            placeholder="Type your answer here..."
                        >
                    @endif

                    @if($saved)
                        <div class="set-feedback-box {{ $saved->is_correct ? 'is-correct' : 'is-incorrect' }}">
                            <strong>
                                @if(empty($saved->answer_text))
                                    Skipped
                                @elseif($saved->is_correct)
                                    Correct
                                @else
                                    Needs improvement
                                @endif
                            </strong>
                            <div style="margin-top: 0.5rem">
                                <strong>Reference:</strong> {{ $saved->correct_answer ?: 'No reference answer available.' }}
                            </div>
                            <div style="margin-top: 0.5rem">{!! $saved->feedback ?: 'No feedback available.' !!}</div>
                            <div style="margin-top: 0.5rem">
                                <strong>Points:</strong> {{ $saved->points_earned }}
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <button class="btn btn-primary" style="width: 100%; padding: 1rem; margin-top: 1.5rem">
            Submit Section
        </button>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const timerCard = document.getElementById('section-timer-card');
            const timerDisplay = document.getElementById('section-timer-display');

            if (!timerCard || !timerDisplay) {
                return;
            }

            const syncUrl = timerCard.dataset.syncUrl;
            const csrfToken = timerCard.dataset.csrfToken;
            const deltaInput = document.getElementById('active-seconds-delta');
            let activeSeconds = Number(timerCard.dataset.activeSeconds || 0);
            let running = document.visibilityState === 'visible';
            let lastResumeAt = running ? Date.now() : null;
            let syncInFlight = false;

            function renderTimer() {
                const liveSeconds = running && lastResumeAt
                    ? Math.max(0, Math.floor((Date.now() - lastResumeAt) / 1000))
                    : 0;

                const elapsedSeconds = activeSeconds + liveSeconds;
                const hours = Math.floor(elapsedSeconds / 3600);
                const minutes = Math.floor((elapsedSeconds % 3600) / 60);
                const seconds = elapsedSeconds % 60;

                timerDisplay.textContent = hours > 0
                    ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
                    : `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }

            async function syncElapsedTime() {
                if (!running || !lastResumeAt || syncInFlight) {
                    return;
                }

                const deltaSeconds = Math.max(0, Math.floor((Date.now() - lastResumeAt) / 1000));
                if (deltaSeconds <= 0) {
                    return;
                }

                activeSeconds += deltaSeconds;
                lastResumeAt = Date.now();
                syncInFlight = true;

                try {
                    await fetch(syncUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ seconds: deltaSeconds }),
                        keepalive: true,
                    });
                } catch (error) {
                    // Keep local timer running even if the sync request fails.
                } finally {
                    syncInFlight = false;
                    renderTimer();
                }
            }

            function pauseTimer() {
                if (!running) {
                    return;
                }

                syncElapsedTime();
                running = false;
                lastResumeAt = null;
                renderTimer();
            }

            function captureUnsyncedDelta() {
                if (!running || !lastResumeAt || !deltaInput) {
                    return;
                }

                const deltaSeconds = Math.max(0, Math.floor((Date.now() - lastResumeAt) / 1000));
                deltaInput.value = String(deltaSeconds);
            }

            function resumeTimer() {
                if (running) {
                    return;
                }

                running = true;
                lastResumeAt = Date.now();
                renderTimer();
            }

            renderTimer();
            window.setInterval(renderTimer, 1000);
            window.setInterval(syncElapsedTime, 15000);

            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'hidden') {
                    pauseTimer();
                } else {
                    resumeTimer();
                }
            });

            window.addEventListener('pagehide', pauseTimer);
            window.addEventListener('beforeunload', pauseTimer);
            document.querySelector('form')?.addEventListener('submit', captureUnsyncedDelta);
        });
    </script>

    <style>
        .set-section-page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .set-workspace-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .set-workspace-stat {
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .set-workspace-stat strong {
            font-size: 1rem;
        }

        .set-workspace-stat span {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        .set-question-stack {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .set-question-card {
            padding: 1.5rem;
        }

        .set-question-card-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .set-question-prompt {
            font-size: 1rem;
            line-height: 1.8;
            margin-bottom: 1rem;
        }

        .set-options-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .set-option-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.9rem 1rem;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            cursor: pointer;
        }

        .set-option-item input {
            margin-top: 0.25rem;
        }

        .set-audio-box {
            padding: 1rem;
            border-radius: 14px;
            background: rgba(99, 102, 241, 0.05);
            border: 1px solid rgba(99, 102, 241, 0.16);
            margin-bottom: 1rem;
        }

        .set-feedback-box {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 14px;
            border: 1px solid transparent;
            line-height: 1.7;
        }

        .set-feedback-box.is-correct {
            background: rgba(16, 185, 129, 0.08);
            border-color: rgba(16, 185, 129, 0.16);
            color: #10b981;
        }

        .set-feedback-box.is-incorrect {
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.16);
            color: #fca5a5;
        }

        @media (max-width: 768px) {
            .set-question-card-header {
                flex-direction: column;
            }
        }
    </style>
</x-app-layout>
