<x-app-layout>
    <div class="drill-header-wrap">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem">{{ ucfirst($skill) }} Drill</h1>
            <p style="color: var(--text-muted)">Question #{{ rand(100,999) }} • Difficulty: <span style="color: var(--primary)">{{ ucfirst($question->difficulty) }}</span></p>
        </div>
        <a href="{{ route('student.practice.index') }}" class="btn btn-outline" style="font-size: 0.875rem">Exit Practice</a>
    </div>

    <!-- Question Container -->
    <div id="question-container" class="glass-card drill-container" style="max-width: 800px; margin: 0 auto;">
        <div style="margin-bottom: 2rem">
            <h3 style="color: var(--text-muted); font-size: 1rem; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.1em">Question</h3>
            
            @if($skill === 'listening' && isset($question->content['audio_path']))
                <div style="margin-bottom: 2rem; background: rgba(99, 102, 241, 0.05); padding: 1.5rem; border-radius: 16px; border: 1px solid var(--glass-border)">
                    <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1rem">🎧 Listen to the recording and answer the question below:</p>
                    <audio id="listening-audio" controls style="width: 100%">
                        <source src="{{ $question->content['audio_path'] }}" type="audio/mpeg">
                        Your browser does not support the audio element.
                    </audio>
                </div>
            @endif

            <div id="question-text" style="font-size: 1.5rem; line-height: 1.6; color: var(--text-main)">
                {!! nl2br(e($question->content['question'] ?? $question->content['text'] ?? 'No text provided')) !!}
            </div>
        </div>

        <div id="interaction-area">
            @if($question->type === 'mcq' && isset($question->content['options']))
                <div style="display: grid; gap: 1rem">
                    @foreach($question->content['options'] as $option)
                        <button class="option-btn glass" onclick="submitAnswer('{{ $option }}')" style="text-align: left; padding: 1.25rem; width: 100%; transition: all 0.2s ease; cursor: pointer">
                            {{ $option }}
                        </button>
                    @endforeach
                </div>
            @else
                <div style="margin-bottom: 1.5rem">
                    <input type="text" id="gap-fill-input" class="form-control" style="font-size: 1.25rem; padding: 1.25rem" placeholder="Type your answer here..." autocomplete="off">
                </div>
                <button onclick="submitGapFill()" class="btn btn-primary" style="width: 100%; padding: 1rem">Submit Answer</button>
            @endif
        </div>

        <!-- Feedback Overlay (Hidden by default) -->
        <div id="feedback-overlay" style="display: none; border-top: 1px solid var(--glass-border); margin-top: 2rem; padding-top: 2rem; animation: fadeIn 0.4s ease">
            <div id="feedback-status" style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem"></div>
            <p id="feedback-explanation" style="color: var(--text-muted); margin-bottom: 2rem"></p>
            <button onclick="location.reload()" class="btn btn-primary">Next Question ➜</button>
        </div>
    </div>

    <script>
        function submitAnswer(answer) {
            const interacts = document.querySelectorAll('.option-btn');
            interacts.forEach(btn => btn.style.pointerEvents = 'none');

            processSubmission(answer);
        }

        function submitGapFill() {
            const input = document.getElementById('gap-fill-input');
            if(!input.value) return;
            input.disabled = true;
            processSubmission(input.value);
        }

        async function processSubmission(answer) {
            try {
                const response = await fetch('{{ route("student.practice.submit") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        question_id: {{ $question->id }},
                        answer: answer
                    })
                });

                const result = await response.json();
                showFeedback(result);
            } catch (error) {
                console.error('Error submitting answer:', error);
            }
        }

        function showFeedback(result) {
            const overlay = document.getElementById('feedback-overlay');
            const status = document.getElementById('feedback-status');
            const explanation = document.getElementById('feedback-explanation');
            const area = document.getElementById('interaction-area');

            area.style.opacity = '0.3';
            area.style.pointerEvents = 'none';

            status.textContent = result.is_correct ? '✅ Amazing! +'+result.points_earned+' XP' : '❌ Not quite. +'+result.points_earned+' XP';
            status.style.color = result.is_correct ? 'var(--accent)' : '#ef4444';
            
            explanation.innerHTML = `<strong>Correct Answer:</strong> ${result.correct_answer}<br><br>${result.feedback}`;
            
            overlay.style.display = 'block';
        }
    </script>

    <style>
        .option-btn:hover {
            border-color: var(--primary) !important;
            background: rgba(99, 102, 241, 0.1) !important;
            transform: translateX(10px);
        }
        .drill-header-wrap {
            margin-bottom: 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .drill-container {
            padding: 3rem;
        }
        @media (max-width: 768px) {
            .drill-header-wrap {
                flex-direction: column;
                align-items: stretch;
            }
            .drill-header-wrap a {
                text-align: center;
            }
            .drill-container {
                padding: 1.5rem;
            }
        }
    </style>
</x-app-layout>
