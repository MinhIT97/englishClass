<x-app-layout>
    <div class="speaking-header-wrap">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem">{{ __('ui.ai_speaking_sim') }}</h1>
            <p style="color: var(--text-muted)">{{ __('ui.speaking_desc') }}</p>
        </div>
        <button onclick="startSession()" id="start-btn" class="btn btn-primary" style="gap: 0.5rem; padding: 0.8rem 2rem;">
            <span id="start-btn-icon">🔴</span> <span id="start-btn-text">{{ __('ui.start_interview') }}</span>
        </button>
    </div>

    <div class="instruction-box" id="how-to-use">
        <h3 style="font-size: 1rem; margin-bottom: 1rem">{{ __('ui.how_to_use') }}</h3>
        <p style="margin-bottom: 0.5rem">1. {!! __('ui.step_1') !!}</p>
        <p style="margin-bottom: 0.5rem">2. {{ __('ui.step_2') }}</p>
        <p style="margin-bottom: 0.5rem">3. {!! __('ui.step_3') !!}</p>
        <p>4. {{ __('ui.step_4') }}</p>
    </div>

    <div id="simulator-container" class="speaking-grid" style="display: none; height: 650px;">
        <!-- Visual & Chat -->
        <div class="glass-card" style="display: flex; flex-direction: column; padding: 0; overflow: hidden">
            <!-- AI Visualizer Area -->
            <div class="ai-header-visual">
                <div id="waveform" class="waveform-pulse"></div>
                <div class="visual-status" id="visual-status-text">IELTS EXAMINER ACTIVE</div>
            </div>

            <!-- Dialogue Area -->
            <div id="chat-history" class="chat-area">
                <!-- Messages will appear here -->
            </div>

            <!-- Input Area -->
            <div class="chat-input-area">
                <div style="display: flex; gap: 1rem; align-items: center">
                    <button onclick="toggleMic()" id="mic-btn" class="btn-mic" title="Speak">
                        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>
                    </button>
                    <input type="text" id="student-input" class="form-control" placeholder="{{ __('ui.type_placeholder') }}" autocomplete="off">
                    <button onclick="sendMessage()" id="send-btn" class="btn btn-primary" style="padding: 0.8rem 1.5rem">{{ __('ui.send') }}</button>
                </div>
                <div style="display: flex; justify-content: center; align-items: center; gap: 1.5rem; margin-top: 1rem">
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0">{{ __('ui.mic_tip') }}</p>
                </div>
            </div>
        </div>

        <!-- Feedback & Stats Sidebar -->
        <div class="speaking-sidebar">
            <div class="glass-card">
                <h3 style="margin-bottom: 1.5rem; font-size: 1rem">✨ {{ __('ui.realtime_coaching') }}</h3>
                <div id="live-feedback" style="color: var(--text-muted); font-size: 0.875rem">
                    {{ __('ui.ai_analyzing') }}
                </div>
            </div>

            <div class="glass-card" style="border-color: var(--primary)">
                <h3 style="margin-bottom: 1rem; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem">
                    🎯 {{ __('ui.exam_tips') }}
                </h3>
                <ul class="tips-list">
                    <li><strong>{{ __('ui.fluency') }}:</strong> {{ __('ui.fluency_tip') }}</li>
                    <li><strong>{{ __('ui.coherence') }}:</strong> {{ __('ui.coherence_tip') }}</li>
                    <li><strong>{{ __('ui.vocabulary') }}:</strong> {{ __('ui.vocab_tip') }}</li>
                    <li><strong>{{ __('ui.grammar') }}:</strong> {{ __('ui.grammar_tip') }}</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        let sessionId = null;
        let pollingInterval = null;
        let lastMessageId = null;

        // ── Microphone Recording ───────────────────────────────────────────────
        let mediaRecorder = null;
        let audioChunks  = [];

        async function initAudio() {
            if (mediaRecorder) return;
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });

                mediaRecorder.ondataavailable = e => audioChunks.push(e.data);

                mediaRecorder.onstop = async () => {
                    const blob = new Blob(audioChunks, { type: 'audio/webm' });
                    audioChunks = [];
                    await sendAudioMessage(blob);
                };
            } catch (err) {
                alert('Please allow microphone access to use this feature.');
            }
        }

        async function toggleMic() {
            if (!sessionId) { alert('Please start a session first.'); return; }
            await initAudio();

            if (mediaRecorder.state === 'inactive') {
                audioChunks = [];
                mediaRecorder.start();
                setMicStatus(true);
            } else {
                mediaRecorder.stop();
                setMicStatus(false);
                setStatus('PROCESSING AUDIO...');
            }
        }

        function setMicStatus(active) {
            document.getElementById('mic-btn').classList.toggle('recording', active);
            setStatus(active ? '🔴 RECORDING… Press Mic again to Stop & Send' : 'IELTS EXAMINER ACTIVE');
        }

        function setStatus(text) {
            document.getElementById('visual-status-text').textContent = text;
        }

        // ── Session Start ──────────────────────────────────────────────────────
        async function startSession() {
            const btn     = document.getElementById('start-btn');
            const btnText = document.getElementById('start-btn-text');
            btn.disabled  = true;
            btnText.textContent = '{{ __('ui.connecting') }}';

            try {
                const res = await fetch('{{ route("student.speaking.start") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                });

                if (!res.ok) {
                    const t = await res.text();
                    alert(`Error ${res.status}: ${t.substring(0, 200)}`);
                    return;
                }

                const data = await res.json();
                sessionId     = data.session_id;
                lastMessageId = null;

                document.getElementById('how-to-use').style.display        = 'none';
                document.getElementById('simulator-container').style.display = 'grid';
                document.getElementById('chat-history').innerHTML           = '';

                addMessage('AI', data.ai_message || 'Hello! I am your IELTS Examiner.', null, null, data.voice_url);
                playAudio(data.voice_url);

            } catch (err) {
                console.error(err);
                alert('Could not start session. Check the console.');
            } finally {
                btn.disabled        = false;
                btnText.textContent = '{{ __('ui.restart_session') }}';
            }
        }

        // ── Send Text Message ──────────────────────────────────────────────────
        async function sendMessage() {
            const input   = document.getElementById('student-input');
            const sendBtn = document.getElementById('send-btn');
            const text    = input.value.trim();
            if (!text || !sessionId) return;

            addMessage('Student', text);
            input.value      = '';
            input.disabled   = true;
            sendBtn.disabled = true;
            setStatus('{{ __('ui.ai_thinking') }}');
            showThinking();

            try {
                const res = await fetch('{{ route("student.speaking.chat") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ session_id: sessionId, message: text }),
                });

                if (res.ok) {
                    startPolling();
                } else {
                    const d = await res.json();
                    addMessage('AI', `Error: ${d.error || 'Unknown error'}`);
                    hideThinking();
                }
            } catch (err) {
                addMessage('AI', 'Network error. Please try again.');
                hideThinking();
            } finally {
                input.disabled   = false;
                sendBtn.disabled = false;
                input.focus();
            }
        }

        // ── Send Audio Message ─────────────────────────────────────────────────
        async function sendAudioMessage(blob) {
            if (!sessionId) return;

            // Show the user's recording in chat with playback
            const localUrl = URL.createObjectURL(blob);
            addMessage('Student', '🎤 Voice message', localUrl);
            showThinking();
            setStatus('{{ __('ui.ai_thinking') }}');

            // Convert to base64 for the JSON endpoint
            const base64 = await blobToBase64(blob);

            try {
                const res = await fetch('{{ route("student.speaking.chat") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        session_id: sessionId,
                        message: '[Audio message]',
                        audio: base64,
                    })
                });

                if (res.ok) {
                    startPolling();
                } else {
                    hideThinking();
                    addMessage('AI', 'Could not process your voice. Please try again.');
                }
            } catch (err) {
                hideThinking();
                addMessage('AI', 'Network error sending audio.');
            } finally {
                setStatus('IELTS EXAMINER ACTIVE');
            }
        }

        function blobToBase64(blob) {
            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onloadend = () => resolve(reader.result.split(',')[1]);
                reader.readAsDataURL(blob);
            });
        }

        // ── Polling for AI Response ────────────────────────────────────────────
        function startPolling() {
            if (pollingInterval) return; 
            pollingInterval = setInterval(pollForResponse, 2000);
        }

        function stopPolling() {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }

        async function pollForResponse() {
            if (!sessionId) { stopPolling(); return; }
            try {
                const res  = await fetch(`{{ url('student/speaking/poll') }}?session_id=${sessionId}&after=${lastMessageId || 0}`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                if (!res.ok) { stopPolling(); return; }
                const data = await res.json();

                if (data.message) {
                    stopPolling();
                    hideThinking();
                    lastMessageId = data.message.id;

                    addMessage('AI', data.message.ai_message, null, data.message.ai_feedback, data.message.voice_url);
                    playAudio(data.message.voice_url);

                    if (data.message.ai_feedback) {
                        showLiveFeedback(data.message.ai_feedback);
                    }
                    setStatus('IELTS EXAMINER ACTIVE');
                }
            } catch (err) {
                console.error('Poll error:', err);
            }
        }

        // ── UI Helpers ─────────────────────────────────────────────────────────
        function showThinking() {
            const history = document.getElementById('chat-history');
            const div = document.createElement('div');
            div.id = 'thinking-indicator';
            div.style.alignSelf = 'flex-start';
            div.style.animation = 'fadeInUp 0.3s ease-out';
            div.innerHTML = `
                <div style="font-size:0.65rem;color:var(--text-muted);margin-bottom:0.25rem;">AI • thinking</div>
                <div class="glass" style="padding:1rem 1.5rem;border-radius:16px;background:var(--glass);border:none;display:flex;gap:6px;align-items:center">
                    <span style="width:8px;height:8px;background:var(--primary);border-radius:50%;animation:dotPulse 1.2s 0s infinite"></span>
                    <span style="width:8px;height:8px;background:var(--primary);border-radius:50%;animation:dotPulse 1.2s 0.2s infinite"></span>
                    <span style="width:8px;height:8px;background:var(--primary);border-radius:50%;animation:dotPulse 1.2s 0.4s infinite"></span>
                </div>`;
            history.appendChild(div);
            history.scrollTo({ top: history.scrollHeight, behavior: 'smooth' });
        }

        function hideThinking() {
            const el = document.getElementById('thinking-indicator');
            if (el) el.remove();
        }

        function addMessage(sender, text, audioUrl = null, feedback = null, voiceUrl = null) {
            const history = document.getElementById('chat-history');
            const isUser  = sender === 'Student';
            const time    = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            let audioHtml = '';
            if (audioUrl) {
                audioHtml = `<audio controls src="${audioUrl}" style="margin-top:8px;height:32px;width:100%;border-radius:20px;outline:none;"></audio>`;
            }

            let correctionHtml = '';
            if (feedback && feedback.corrected && feedback.corrected !== feedback.original) {
                correctionHtml = `<div style="margin-top:8px;padding:8px 10px;background:rgba(16,185,129,0.1);border-left:3px solid #10b981;border-radius:6px;font-size:0.8rem;color:#10b981">
                    ✅ Corrected: ${feedback.corrected}</div>`;
            }

            const msgDiv = document.createElement('div');
            msgDiv.style.alignSelf = isUser ? 'flex-end' : 'flex-start';
            msgDiv.style.maxWidth  = '85%';
            msgDiv.style.animation = 'fadeInUp 0.3s ease-out';
            msgDiv.innerHTML = `
                <div style="font-size:0.65rem;color:var(--text-muted);margin-bottom:0.25rem;text-align:${isUser ? 'right' : 'left'}">${sender.toUpperCase()} • ${time}</div>
                <div class="glass" style="padding:1.25rem;border-radius:16px;background:${isUser ? 'linear-gradient(135deg,var(--primary) 0%,#4f46e5 100%)' : 'var(--glass)'};color:${isUser ? 'white' : 'inherit'};border:none;">
                    ${text}
                    ${audioHtml}
                    ${correctionHtml}
                </div>`;

            history.appendChild(msgDiv);
            history.scrollTo({ top: history.scrollHeight, behavior: 'smooth' });
        }

        function playAudio(url) {
            if (!url) return;
            const audio = new Audio(url);
            audio.play().catch(() => {});
            document.getElementById('waveform').classList.add('ai-speaking');
            audio.onended = () => document.getElementById('waveform').classList.remove('ai-speaking');
        }

        function showLiveFeedback(feedback) {
            const fb = document.getElementById('live-feedback');
            fb.innerHTML = `
                ${feedback.original ? `<div style="margin-bottom:1rem;padding:1rem;background:rgba(99,102,241,0.08);border-radius:12px;border:1px solid rgba(99,102,241,0.2)">
                    <div style="color:var(--primary);font-weight:700;font-size:0.7rem;margin-bottom:0.4rem">📝 WHAT YOU SAID</div>
                    <div style="color:var(--text-muted);font-size:0.85rem">${feedback.original}</div>
                </div>` : ''}
                ${feedback.corrected && feedback.corrected !== feedback.original ? `<div style="margin-bottom:1rem;padding:1rem;background:rgba(16,185,129,0.08);border-radius:12px;border:1px solid rgba(16,185,129,0.2)">
                    <div style="color:#10b981;font-weight:700;font-size:0.7rem;margin-bottom:0.4rem">✅ CORRECTED VERSION</div>
                    <div style="color:var(--text-main);font-size:0.85rem">${feedback.corrected}</div>
                </div>` : ''}
                ${feedback.explanation ? `<div style="padding:1rem;background:rgba(245,158,11,0.08);border-radius:12px;border:1px solid rgba(245,158,11,0.2)">
                    <div style="color:#f59e0b;font-weight:700;font-size:0.7rem;margin-bottom:0.4rem">💡 EXPLANATION</div>
                    <div style="color:var(--text-muted);font-size:0.85rem">${feedback.explanation}</div>
                </div>` : ''}
            `;
        }
    </script>

    <style>
        @keyframes dotPulse {
            0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
            40% { transform: scale(1); opacity: 1; }
        }

        .instruction-box {
            background: rgba(99, 102, 241, 0.05);
            padding: 2rem;
            border-radius: 20px;
            border: 1px dashed var(--glass-border);
            margin-bottom: 2rem;
            color: var(--text-muted);
        }

        .ai-header-visual {
            height: 180px; 
            background: #0a0a0f; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            position: relative; 
            overflow: hidden;
        }

        .visual-status {
            position: absolute; 
            bottom: 1.5rem; 
            left: 1.5rem; 
            font-size: 0.7rem; 
            color: #4a4a4a; 
            text-transform: uppercase; 
            letter-spacing: 3px;
        }

        .chat-area {
            flex: 1; 
            padding: 2.5rem; 
            overflow-y: auto; 
            display: flex; 
            flex-direction: column; 
            gap: 2rem;
            background: rgba(0,0,0,0.02);
        }

        .chat-input-area {
            padding: 2rem; 
            border-top: 1px solid var(--glass-border);
            background: var(--bg-card);
        }

        .btn-mic {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #6b7280;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .btn-mic:hover {
            background: #e5e7eb;
            color: var(--primary);
        }

        .btn-mic.recording {
            background: #fee2e2;
            border-color: #fecaca;
            color: #ef4444;
            animation: micPulse 1.5s infinite;
        }

        @keyframes micPulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .tips-list {
            padding-left: 0; 
            font-size: 0.8rem; 
            color: var(--text-muted); 
            display: flex; 
            flex-direction: column; 
            gap: 1rem;
            list-style: none;
        }

        .tips-list li {
            position: relative;
            padding-left: 1.5rem;
        }

        .tips-list li::before {
            content: '•';
            position: absolute;
            left: 0;
            color: var(--primary);
            font-weight: bold;
        }

        .waveform-pulse {
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, var(--primary, #4f46e5) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(15px);
            transition: transform 0.1s, opacity 0.1s;
        }

        .waveform-pulse.ai-speaking {
            animation: pulseWave 1s infinite !important;
            background: radial-gradient(circle, #ef4444 0%, transparent 70%) !important;
            opacity: 0.6 !important;
        }

        @keyframes pulseWave {
            0% { transform: scale(1); opacity: 0.2; }
            50% { transform: scale(1.5); opacity: 0.4; }
            100% { transform: scale(1); opacity: 0.2; }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .speaking-header-wrap {
            margin-bottom: 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .speaking-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 1.5rem;
        }
        
        .speaking-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        @media (max-width: 900px) {
            #simulator-container {
                height: auto !important;
                display: flex !important;
                flex-direction: column;
            }
            .chat-area {
                height: 400px;
            }
            .speaking-grid {
                grid-template-columns: 1fr;
            }
            .speaking-header-wrap {
                flex-direction: column;
                align-items: stretch;
            }
            .speaking-header-wrap button {
                width: 100%;
            }
        }
    </style>
</x-app-layout>
