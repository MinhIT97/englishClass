<x-app-layout>
    <div class="speaking-header-wrap">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem">AI Speaking Simulator</h1>
            <p style="color: var(--text-muted)">Practice your speaking with Gemini AI acting as an IELTS examiner.</p>
        </div>
        <button onclick="startSession()" id="start-btn" class="btn btn-primary" style="gap: 0.5rem; padding: 0.8rem 2rem;">
            <span id="start-btn-icon">🔴</span> <span id="start-btn-text">Start Interview Session</span>
        </button>
    </div>

    <div class="instruction-box" id="how-to-use">
        <h3 style="font-size: 1rem; margin-bottom: 1rem">How to use:</h3>
        <p style="margin-bottom: 0.5rem">1. Click <strong>"Start Interview Session"</strong> to begin.</p>
        <p style="margin-bottom: 0.5rem">2. The AI Examiner will introduce themselves and ask the first question via voice.</p>
        <p style="margin-bottom: 0.5rem">3. Respond by speaking or typing. Use the <strong>Mic</strong> button to talk.</p>
        <p>4. Receive real-time grammar tips and feedback on the sidebar.</p>
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
                    <input type="text" id="student-input" class="form-control" placeholder="Type or speak your response..." autocomplete="off">
                    <button onclick="sendMessage()" id="send-btn" class="btn btn-primary" style="padding: 0.8rem 1.5rem">Send</button>
                </div>
                <div style="display: flex; justify-content: center; align-items: center; gap: 1.5rem; margin-top: 1rem">
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0">Tip: Press the Mic to start, and press it again to Stop & Send.</p>
                </div>
            </div>
        </div>

        <!-- Feedback & Stats Sidebar -->
        <div class="speaking-sidebar">
            <div class="glass-card">
                <h3 style="margin-bottom: 1.5rem; font-size: 1rem">✨ Real-time Coaching</h3>
                <div id="live-feedback" style="color: var(--text-muted); font-size: 0.875rem">
                    The AI is analyzing your speaking patterns. Start talking to see feedback.
                </div>
            </div>

            <div class="glass-card" style="border-color: var(--primary)">
                <h3 style="margin-bottom: 1rem; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem">
                    🎯 Exam Tips
                </h3>
                <ul class="tips-list">
                    <li><strong>Fluency:</strong> Keep speaking even if you make a mistake.</li>
                    <li><strong>Coherence:</strong> Use linking words like "However", "In addition".</li>
                    <li><strong>Vocabulary:</strong> Avoid basic words like "good" or "bad".</li>
                    <li><strong>Grammar:</strong> Use a mix of simple and complex tenses.</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        let sessionId = null;
        let isLiveMode = true;
        let isStreaming = false;

        // VAD / Streaming variables
        let audioContext = null;
        let micStream = null;
        let scriptProcessor = null;
        let analyser = null;
        
        // Output audio streaming variables
        let playbackContext = new (window.AudioContext || window.webkitAudioContext)();
        let nextPlayTime = 0;

        function playAudioChunk(base64Audio) {
            const binaryString = window.atob(base64Audio);
            const len = binaryString.length;
            const bytes = new Uint8Array(len);
            for (let i = 0; i < len; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }
            
            // Assume the audio is a complete snippet that can be decoded, or raw PCM.
            // Gemini typically returns raw 24kHz PCM for audio output, or valid webm chunks.
            playbackContext.decodeAudioData(bytes.buffer, (buffer) => {
                const source = playbackContext.createBufferSource();
                source.buffer = buffer;
                source.connect(playbackContext.destination);

                if (nextPlayTime < playbackContext.currentTime) {
                    nextPlayTime = playbackContext.currentTime;
                }
                source.start(nextPlayTime);
                nextPlayTime += buffer.duration;
            }, (e) => {
                console.error("Error decoding audio chunk", e);
            });
        }

        async function initAudioStream() {
            if (audioContext) return;
            try {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                analyser = audioContext.createAnalyser();
                analyser.fftSize = 256;
                
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                micStream = audioContext.createMediaStreamSource(stream);
                
                // Use a smaller buffer size for lower latency streaming
                scriptProcessor = audioContext.createScriptProcessor(4096, 1, 1);
                
                micStream.connect(analyser);
                analyser.connect(scriptProcessor);
                scriptProcessor.connect(audioContext.destination);

                scriptProcessor.onaudioprocess = (e) => {
                    if (!isStreaming || !sessionId) return;
                    
                    const inputData = e.inputBuffer.getChannelData(0);
                    // Convert Float32Array to Int16
                    const pcm16 = new Int16Array(inputData.length);
                    for (let i = 0; i < inputData.length; i++) {
                        const s = Math.max(-1, Math.min(1, inputData[i]));
                        pcm16[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
                    }
                    
                    // Convert Int16Array to Base64
                    const bytes = new Uint8Array(pcm16.buffer);
                    let binary = '';
                    for (let i = 0; i < bytes.byteLength; i++) {
                        binary += String.fromCharCode(bytes[i]);
                    }
                    const base64Audio = window.btoa(binary);

                    // Send strictly using Pusher Reverb format
                    window.Echo.connector.pusher.send_event('client-audio-stream', {
                        session_id: sessionId,
                        audio: base64Audio,
                        is_final: false
                    });
                };

                monitorVolume();
            } catch (err) {
                console.error("Microphone error:", err);
            }
        }

        function monitorVolume() {
            const dataArray = new Uint8Array(analyser.frequencyBinCount);
            
            function check() {
                analyser.getByteFrequencyData(dataArray);
                let sum = 0;
                for (let i = 0; i < dataArray.length; i++) {
                    sum += dataArray[i];
                }
                const average = sum / dataArray.length;
                const volume = average / 255;
                
                const waveform = document.getElementById('waveform');
                if (isStreaming) {
                    const scale = 1 + (volume * 2);
                    waveform.style.transform = `scale(${scale})`;
                    waveform.style.opacity = 0.3 + (volume * 0.7);
                }
                
                requestAnimationFrame(check);
            }
            check();
        }

        function setMicStatus(active) {
            const micBtn = document.getElementById('mic-btn');
            const statusText = document.getElementById('visual-status-text');
            const waveform = document.getElementById('waveform');
            
            if (active) {
                micBtn.classList.add('recording');
                statusText.textContent = 'STREAMING...';
            } else {
                micBtn.classList.remove('recording');
                statusText.textContent = 'IELTS EXAMINER ACTIVE';
                waveform.style.transform = 'scale(1)';
                waveform.style.opacity = '0.3';
            }
        }

        async function toggleMic() {
            if (!audioContext) await initAudioStream();
            
            if (audioContext.state === 'suspended') {
                await audioContext.resume();
            }
            if (playbackContext.state === 'suspended') {
                await playbackContext.resume();
            }

            isStreaming = !isStreaming;
            setMicStatus(isStreaming);
            
            if (!isStreaming && sessionId) {
                // Send an end of stream signal
                window.Echo.connector.pusher.send_event('client-audio-stream', {
                    session_id: sessionId,
                    audio: null,
                    is_final: true
                });
            }
        }

        function monitorVolume() {
            const dataArray = new Uint8Array(analyser.frequencyBinCount);
            
            function check() {
                analyser.getByteFrequencyData(dataArray);
                let sum = 0;
                for (let i = 0; i < dataArray.length; i++) {
                    sum += dataArray[i];
                }
                const average = sum / dataArray.length;
                const volume = average / 255;
                
                // Update pulse based on real volume
                const waveform = document.getElementById('waveform');
                if (mediaRecorder && mediaRecorder.state === 'recording') {
                    const scale = 1 + (volume * 2);
                    waveform.style.transform = `scale(${scale})`;
                    waveform.style.opacity = 0.3 + (volume * 0.7);
                }
                
                requestAnimationFrame(check);
            }
            check();
        }

        function setMicStatus(isListening) {
            const micBtn = document.getElementById('mic-btn');
            const statusText = document.getElementById('visual-status-text');
            const waveform = document.getElementById('waveform');
            
            if (isListening) {
                micBtn.classList.add('recording');
                statusText.textContent = 'LISTENING...';
            } else {
                micBtn.classList.remove('recording');
                statusText.textContent = 'IELTS EXAMINER ACTIVE';
                waveform.style.transform = 'scale(1)';
                waveform.style.opacity = '0.3';
            }
        }

        async function toggleMic() {
            if (!audioContext) await initAudioContext();
            
            // Standard user activation requirement for AudioContext
            if (audioContext.state === 'suspended') {
                await audioContext.resume();
            }

            if (mediaRecorder.state === 'inactive') {
                mediaRecorder.start();
                if (recognition) recognition.start();
                setMicStatus(true);
                document.getElementById('student-input').value = '';
            } else {
                mediaRecorder.stop();
                if (recognition) recognition.stop();
                setMicStatus(false);
            }
        }

        function setupEchoListener(sessId) {
            window.Echo.private(`speaking-session.${sessId}`)
                .listen('.VoiceResponseArrived', (e) => {
                    if (e.textChunk) {
                        // Append text to chat directly
                        document.getElementById('visual-status-text').textContent = 'AI THINKING...';
                        console.log("LLM Stream:", e.textChunk);
                    }
                    if (e.audioChunk) {
                        document.getElementById('waveform').classList.add('ai-speaking');
                        document.getElementById('visual-status-text').textContent = 'AI SPEAKING...';
                        playAudioChunk(e.audioChunk);
                        
                        // Fake remove speaking effect after buffer mostly clears (lazy implementation for demo)
                        setTimeout(() => {
                           document.getElementById('waveform').classList.remove('ai-speaking');
                           document.getElementById('visual-status-text').textContent = 'IELTS EXAMINER ACTIVE';
                        }, 500);
                    }
                });
        }

        async function startSession() {
            const btn = document.getElementById('start-btn');
            const btnText = document.getElementById('start-btn-text');
            const btnIcon = document.getElementById('start-btn-icon');
            
            btn.disabled = true;
            btnText.textContent = 'Connecting...';
            btnIcon.style.animation = 'pulse 1s infinite';

            try {
                // Initialize Reverb / Audio early
                if (playbackContext.state === 'suspended') await playbackContext.resume();

                const response = await fetch('{{ route("student.speaking.start") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                
                const data = await response.json();
                sessionId = data.session_id;
                setupEchoListener(sessionId);
                
                document.getElementById('how-to-use').style.display = 'none';
                document.getElementById('simulator-container').style.display = 'grid';
                document.getElementById('chat-history').innerHTML = '';
                addMessage('AI', "Session connected securely. Press Mic to start streaming.");
                
            } catch (error) {
                alert('Could not start session.');
            } finally {
                btn.disabled = false;
                btnText.textContent = 'Start Interview Session';
            }
        }

        async function sendMessage() {
            // Unused in full streaming setup, keeping for backward compatibility if needed.
            // Everything is streamed automatically via toggleMic now.
            const input = document.getElementById('student-input');
            const message = input.value;
            
            if(!message) return;
            addMessage('Student', message);
            input.value = '';
        }

        function addMessage(sender, text) {
            const history = document.getElementById('chat-history');
            const msgDiv = document.createElement('div');
            msgDiv.style.alignSelf = sender === 'AI' ? 'flex-start' : 'flex-end';
            msgDiv.style.maxWidth = '85%';
            msgDiv.style.animation = 'fadeInUp 0.3s ease-out';
            
            const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            msgDiv.innerHTML = `
                <div style="font-size: 0.65rem; color: var(--text-muted); margin-bottom: 0.25rem; text-align: ${sender === 'AI' ? 'left' : 'right'}">${sender.toUpperCase()} • ${time}</div>
                <div class="glass" style="padding: 1.25rem; border-radius: 16px; position: relative; background: ${sender === 'AI' ? 'var(--glass)' : 'linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%)'}; color: ${sender === 'AI' ? 'inherit' : 'white'}; border: none;">
                    ${text}
                </div>
            `;
            
            history.appendChild(msgDiv);
            history.scrollTo({ top: history.scrollHeight, behavior: 'smooth' });
        }

        function showLiveFeedback(feedback) {
            const fbArea = document.getElementById('live-feedback');
            fbArea.innerHTML = `
                ${feedback.grammar_correction ? `
                    <div style="margin-bottom: 1rem; padding: 1rem; background: rgba(239, 68, 68, 0.1); border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.2)">
                        <div style="color: #ef4444; font-weight: 700; font-size: 0.75rem; margin-bottom: 0.25rem">⚠️ GRAMMAR</div>
                        <div style="color: var(--text-main)">${feedback.grammar_correction}</div>
                    </div>
                ` : ''}
                ${feedback.pronunciation ? `
                    <div style="margin-bottom: 1rem; padding: 1rem; background: rgba(99, 102, 241, 0.1); border-radius: 12px; border: 1px solid rgba(99, 102, 241, 0.2)">
                        <div style="color: var(--primary); font-weight: 700; font-size: 0.75rem; margin-bottom: 0.25rem">🎙️ PRONUNCIATION</div>
                        <div style="color: var(--text-main)">${feedback.pronunciation}</div>
                    </div>
                ` : ''}
                ${feedback.tip ? `
                    <div style="padding: 1rem; background: rgba(16, 185, 129, 0.1); border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.2)">
                        <div style="color: #10b981; font-weight: 700; font-size: 0.75rem; margin-bottom: 0.25rem">💡 IMPROVEMENT TIP</div>
                        <div style="color: var(--text-main)">${feedback.tip}</div>
                    </div>
                ` : ''}
            `;
        }
    </script>

    <style>
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
