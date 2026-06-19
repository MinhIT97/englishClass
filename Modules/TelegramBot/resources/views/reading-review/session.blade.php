<x-app-layout>
    @push('head')
        <title>{{ __('ui.reading_review_session_title') }} - {{ config('app.name') }}</title>
    @endpush

    <div class="reading-review-wrap">
        <div class="reading-review-header">
            <div>
                <h1 style="font-size: 1.75rem; margin-bottom: 0.25rem">📖 {{ __('ui.reading_review_session_title') }}</h1>
                <p style="color: var(--text-muted)">{{ __('ui.reading_review_session_subtitle') }}</p>
            </div>
            <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap">
                <span style="font-size:0.85rem;color:var(--text-muted)">📅 {{ $stats['due_today'] }} {{ __('ui.reading_review_due') }}</span>
                <a href="{{ route('reading-review.index') }}" class="btn btn-outline" style="font-size: 0.85rem">{{ __('ui.back') }}</a>
            </div>
        </div>

        @if(!$passage)
            <x-ui.empty-state
                icon="🎉"
                :title="__('ui.reading_review_session_empty_title')"
                :description="__('ui.reading_review_session_empty_desc')"
            />
            <div style="text-align:center;margin-top:1.5rem">
                <a href="{{ route('reading-review.index') }}" class="btn btn-primary">
                    📚 {{ __('ui.reading_review_browse_library') }}
                </a>
            </div>
        @else
            <div id="passage-container" class="glass-card" style="max-width: 900px; margin: 0 auto; padding: 2rem">
                {{-- Title + meta --}}
                <div style="margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--glass-border)">
                    <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem">{{ $passage->title }}</h2>
                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;font-size:0.8rem;color:var(--text-muted)">
                        <span>📊 {{ strtoupper($passage->difficulty) }}</span>
                        @if($passage->topic)<span>📁 {{ $passage->topic->name_en }}</span>@endif
                        @if($passage->source)<span>📰 {{ $passage->source }}</span>@endif
                        @if($passage->word_count)<span>🔤 {{ $passage->word_count }} {{ __('ui.reading_review_words') }}</span>@endif
                        <span>⏱️ ~{{ $passage->estimated_minutes }} min</span>
                    </div>
                </div>

                {{-- Passage body --}}
                <div id="passage-body" style="font-size: 1.05rem; line-height: 1.75; color: var(--text-main); margin-bottom: 2rem; max-height: 400px; overflow-y: auto; padding: 1rem; background: var(--bg-secondary); border-radius: 12px">
                    {!! nl2br(e($passage->body)) !!}
                </div>

                {{-- Questions --}}
                <form id="grade-form">
                    @csrf
                    <input type="hidden" name="time_spent_ms" id="time_spent_ms" value="0">
                    <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem">❓ {{ __('ui.reading_review_answer_questions') }}</h3>

                    @foreach($passage->passageQuestions as $idx => $pq)
                        @php $q = $pq->question; @endphp
                        <div class="question-block" data-question-id="{{ $q->id }}" style="margin-bottom: 1.5rem; padding: 1.25rem; background: var(--bg-elevated); border: 1px solid var(--glass-border); border-radius: 12px">
                            <div style="display:flex;gap:0.75rem;margin-bottom:0.75rem">
                                <span style="flex-shrink:0;width:1.75rem;height:1.75rem;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem">{{ $idx + 1 }}</span>
                                <p style="margin:0;font-weight:500;line-height:1.5">
                                    {{ $q->content['question'] ?? $q->content['text'] ?? 'Question' }}
                                </p>
                            </div>

                            @if(($q->type ?? null) === 'mcq' && !empty($q->content['options']))
                                <div style="display:grid;gap:0.5rem;margin-top:0.5rem">
                                    @foreach($q->content['options'] as $option)
                                        <label class="option-label" style="display:flex;gap:0.75rem;align-items:center;padding:0.75rem 1rem;background:var(--bg-secondary);border:1px solid var(--glass-border);border-radius:8px;cursor:pointer;transition:all 0.2s">
                                            <input type="radio" name="answers[{{ $q->id }}]" value="{{ $option }}" style="accent-color:var(--primary)">
                                            <span style="flex:1">{{ $option }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <input type="text" name="answers[{{ $q->id }}]" class="form-control" style="font-size:0.95rem;padding:0.6rem 0.9rem" placeholder="Type your answer..." autocomplete="off">
                            @endif
                        </div>
                    @endforeach

                    <div style="display:flex;gap:0.5rem;margin-top:1.5rem">
                        <button type="submit" class="btn btn-primary" style="flex:1;padding:0.75rem">
                            {{ __('ui.reading_review_submit') }} ➜
                        </button>
                    </div>
                </form>

                {{-- Feedback area (hidden by default) --}}
                <div id="feedback-area" style="display:none;border-top:1px solid var(--glass-border);margin-top:2rem;padding-top:2rem;animation:fadeIn 0.4s ease">
                    <div id="feedback-status" style="font-size:1.5rem;font-weight:700;margin-bottom:0.5rem"></div>
                    <div id="feedback-summary" style="color:var(--text-muted);margin-bottom:1.5rem"></div>

                    <h4 style="font-size:1rem;font-weight:600;margin-bottom:0.75rem">{{ __('ui.reading_review_grade_prompt') }}</h4>
                    <div id="grade-row" style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.5rem;margin-bottom:1rem">
                        <button type="button" data-grade="0" class="btn btn-outline grade-btn" style="border-color:var(--danger);color:var(--danger);padding:0.75rem 0.5rem">
                            😖 {{ __('ui.grade_again') }}
                        </button>
                        <button type="button" data-grade="1" class="btn btn-outline grade-btn" style="border-color:var(--warning);color:var(--warning);padding:0.75rem 0.5rem">
                            😣 {{ __('ui.grade_hard') }}
                        </button>
                        <button type="button" data-grade="2" class="btn btn-outline grade-btn" style="border-color:var(--info);color:var(--info);padding:0.75rem 0.5rem">
                            👍 {{ __('ui.grade_good') }}
                        </button>
                        <button type="button" data-grade="3" class="btn btn-outline grade-btn" style="border-color:var(--accent);color:var(--accent);padding:0.75rem 0.5rem">
                            🎉 {{ __('ui.grade_easy') }}
                        </button>
                    </div>

                    <div style="display:flex;gap:0.5rem">
                        <a href="{{ route('reading-review.session') }}" class="btn btn-primary" style="flex:1;padding:0.6rem">
                            {{ __('ui.reading_review_next') }} ➜
                        </a>
                        <a href="{{ route('reading-review.index') }}" class="btn btn-outline" style="flex:1;padding:0.6rem">
                            📚 {{ __('ui.reading_review_back_library') }}
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @once
        @push('scripts')
            <script>
                (function () {
                    const form = document.getElementById('grade-form');
                    if (!form) return;

                    const csrf = document.querySelector('meta[name="csrf-token"]').content;
                    const passageId = {{ $passage?->id ?? 'null' }};
                    const startedAt = Date.now();
                    const feedbackArea = document.getElementById('feedback-area');
                    const gradeFormEl = form;

                    // Live timer — updates hidden field on submit.
                    setInterval(() => {
                        const el = document.getElementById('time_spent_ms');
                        if (el) el.value = Date.now() - startedAt;
                    }, 1000);

                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const submitBtn = form.querySelector('button[type="submit"]');
                        submitBtn.disabled = true;
                        submitBtn.textContent = '⏳ ...';

                        const fd = new FormData(form);
                        const answers = {};
                        for (const [k, v] of fd.entries()) {
                            if (k.startsWith('answers[')) {
                                const qid = k.match(/\d+/)[0];
                                answers[qid] = v;
                            }
                        }

                        try {
                            const res = await fetch(`/reading-review/passages/${passageId}/grade`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    answers,
                                    time_spent_ms: Date.now() - startedAt,
                                }),
                            });
                            const data = await res.json();
                            showFeedback(data);
                        } catch (err) {
                            console.error(err);
                            alert('Submission failed, please try again.');
                            submitBtn.disabled = false;
                            submitBtn.textContent = '{{ __('ui.reading_review_submit') }} ➜';
                        }
                    });

                    function showFeedback(data) {
                        if (!data.ok) {
                            alert(data.message || data.reason || 'Submission failed.');
                            return;
                        }

                        gradeFormEl.style.display = 'none';
                        feedbackArea.style.display = 'block';

                        const status = document.getElementById('feedback-status');
                        const summary = document.getElementById('feedback-summary');
                        const accuracy = Math.round((data.accuracy || 0) * 100);

                        if (accuracy >= 75) {
                            status.textContent = `🎉 ${data.correct}/${data.total} ${data.correct === data.total ? '— ' + '{{ __('ui.reading_review_perfect') }}' : ''}`;
                            status.style.color = 'var(--accent)';
                        } else if (accuracy >= 50) {
                            status.textContent = `👍 ${data.correct}/${data.total}`;
                            status.style.color = 'var(--info)';
                        } else {
                            status.textContent = `💪 ${data.correct}/${data.total}`;
                            status.style.color = 'var(--warning)';
                        }

                        summary.innerHTML = `<strong>+${data.points_earned} XP</strong> · {{ __('ui.reading_review_next_review') }}: <em>${data.next_review_at ? new Date(data.next_review_at).toLocaleString() : '—'}</em><br>{{ __('ui.reading_review_due_remaining') }}: <strong>${data.due_remaining ?? 0}</strong>`;

                        // Wire grade buttons to call the API again with explicit grade.
                        document.querySelectorAll('.grade-btn').forEach((btn) => {
                            btn.addEventListener('click', async () => {
                                const grade = parseInt(btn.dataset.grade, 10);
                                btn.disabled = true;
                                btn.textContent = '⏳ ...';
                                try {
                                    const res = await fetch(`/reading-review/passages/${passageId}/grade`, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': csrf,
                                            'Accept': 'application/json',
                                        },
                                        body: JSON.stringify({
                                            answers: collectAnswers(),
                                            grade,
                                        }),
                                    });
                                    const data = await res.json();
                                    if (data.ok) {
                                        window.location.href = '{{ route('reading-review.session') }}';
                                    } else {
                                        alert('Could not save grade.');
                                        btn.disabled = false;
                                    }
                                } catch (err) {
                                    console.error(err);
                                    btn.disabled = false;
                                }
                            });
                        });
                    }

                    function collectAnswers() {
                        const answers = {};
                        document.querySelectorAll('[data-question-id]').forEach((block) => {
                            const qid = block.dataset.questionId;
                            const checked = block.querySelector('input[type="radio"]:checked');
                            const text = block.querySelector('input[type="text"]');
                            if (checked) answers[qid] = checked.value;
                            else if (text) answers[qid] = text.value;
                        });
                        return answers;
                    }
                })();
            </script>
        @endpush
    @endonce

    <style>
        .reading-review-wrap {
            max-width: 1100px;
            margin: 0 auto;
        }
        .reading-review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .option-label:hover {
            border-color: var(--primary) !important;
            background: rgba(99, 102, 241, 0.1) !important;
        }
        .option-label:has(input:checked) {
            border-color: var(--primary) !important;
            background: rgba(99, 102, 241, 0.15) !important;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</x-app-layout>
