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
                @php
                    $text = $question->content['question'] ?? $question->content['text'] ?? 'No text provided';
                    if ($skill === 'listening') {
                        $text = preg_replace('/\s*\(Audio transcript hint:.*?\)\s*/i', '', $text);
                    }
                @endphp
                {!! nl2br(e($text)) !!}
            </div>
        </div>

        <div id="interaction-area">
            @if($skill === 'speaking')
                <div id="speaking-control" style="text-align: center; padding: 2.5rem; background: rgba(99, 102, 241, 0.05); border-radius: 20px; border: 2px dashed var(--primary); transition: all 0.3s ease">
                    <div id="recording-status" style="margin-bottom: 1.5rem; font-weight: 500; color: var(--text-muted);">Tap to start recording your answer</div>
                    <button id="record-btn" class="btn" style="width: 100px; height: 100px; border-radius: 50%; font-size: 2.5rem; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto; transition: all 0.3s ease; box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4)">
                        🎤
                    </button>
                    
                    <div id="recording-timer" style="display: none; margin-top: 1rem; font-family: monospace; font-size: 1.25rem; color: #ef4444">00:00</div>

                    <div id="audio-preview-wrap" style="display: none; margin-top: 2rem; animation: slideUp 0.3s ease">
                        <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.5rem">Preview your response:</p>
                        <audio id="audio-preview" controls style="width: 100%; margin-bottom: 1.5rem"></audio>
                        <div style="display: flex; gap: 1rem">
                            <button onclick="resetRecording()" class="btn btn-outline" style="flex: 1">Try Again</button>
                            <button onclick="uploadSpeakingAnswer()" class="btn btn-primary" style="flex: 2">Submit for AI Grading</button>
                        </div>
                    </div>
                </div>
            @elseif($question->type === 'mcq' && isset($question->content['options']))
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
            
            let feedbackHtml = `<strong>Correct Answer:</strong> ${result.correct_answer}<br><br>${result.feedback}`;
            
            if (result.pronunciation_feedback) {
                feedbackHtml += `<div style="margin-top: 1.5rem; padding: 1rem; background: rgba(99, 102, 241, 0.1); border-radius: 8px; border-left: 4px solid var(--primary)">
                    <strong>Pronunciation Analysis:</strong><br>${result.pronunciation_feedback}
                </div>`;
            }
            
            explanation.innerHTML = feedbackHtml;
            
            overlay.style.display = 'block';
        }

        // Speaking Logic
        let mediaRecorder;
        let audioChunks = [];
        let timerInterval;
        let audioBlob;

        const recordBtn = document.getElementById('record-btn');
        if (recordBtn) {
            recordBtn.addEventListener('click', toggleRecording);
        }

        async function toggleRecording() {
            if (!mediaRecorder || mediaRecorder.state === 'inactive') {
                startRecording();
            } else {
                stopRecording();
            }
        }

        async function startRecording() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];

                mediaRecorder.ondataavailable = event => {
                    audioChunks.push(event.data);
                };

                mediaRecorder.onstop = () => {
                    audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    const audioUrl = URL.createObjectURL(audioBlob);
                    document.getElementById('audio-preview').src = audioUrl;
                    document.getElementById('audio-preview-wrap').style.display = 'block';
                };

                mediaRecorder.start();
                recordBtn.innerHTML = '⏹️';
                recordBtn.style.background = '#ef4444';
                recordBtn.style.animation = 'pulse 1.5s infinite';
                document.getElementById('recording-status').textContent = 'Recording... Press stop when finished';
                
                startTimer();
            } catch (err) {
                alert('Microphone access denied or not available.');
                console.error(err);
            }
        }

        function stopRecording() {
            mediaRecorder.stop();
            mediaRecorder.stream.getTracks().forEach(track => track.stop());
            
            recordBtn.innerHTML = '🎤';
            recordBtn.style.background = 'var(--primary)';
            recordBtn.style.animation = 'none';
            document.getElementById('recording-status').textContent = 'Recording finished';
            stopTimer();
        }

        function resetRecording() {
            document.getElementById('audio-preview-wrap').style.display = 'none';
            document.getElementById('recording-status').textContent = 'Tap to start recording your answer';
            audioBlob = null;
        }

        function startTimer() {
            let seconds = 0;
            const timerDisplay = document.getElementById('recording-timer');
            timerDisplay.style.display = 'block';
            timerInterval = setInterval(() => {
                seconds++;
                const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
                const secs = (seconds % 60).toString().padStart(2, '0');
                timerDisplay.textContent = `${mins}:${secs}`;
            }, 1000);
        }

        function stopTimer() {
            clearInterval(timerInterval);
            document.getElementById('recording-timer').style.display = 'none';
        }

        async function uploadSpeakingAnswer() {
            if (!audioBlob) return;

            const reader = new FileReader();
            reader.readAsDataURL(audioBlob);
            reader.onloadend = async () => {
                const base64Audio = reader.result.split(',')[1];
                
                document.getElementById('audio-preview-wrap').innerHTML = '<div style="padding: 2rem; text-align: center">Analyzing your pronunciation... 🧠</div>';

                try {
                    const response = await fetch('{{ route("student.practice.submit.speaking") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            question_id: {{ $question->id }},
                            audio: base64Audio
                        })
                    });

                    const result = await response.json();
                    showFeedback(result);
                } catch (error) {
                    console.error('Error submitting speaking answer:', error);
                    alert('Submission failed. Please try again.');
                }
            };
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
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            70% { box-shadow: 0 0 0 20px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</x-app-layout>
