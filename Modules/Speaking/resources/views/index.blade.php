<x-app-layout>
    <div class="speaking-header-wrap">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem">AI Speaking Simulator</h1>
            <p style="color: var(--text-muted)">Practice your speaking with Gemini AI acting as an IELTS examiner.</p>
        </div>
        <button onclick="startSession()" id="start-btn" class="btn btn-primary" style="gap: 0.5rem">
            <span>🔴</span> Start Interview Session
        </button>
    </div>

    <div id="simulator-container" class="speaking-grid" style="display: none; height: 600px;">
        <!-- Visual & Chat -->
        <div class="glass-card" style="display: flex; flex-direction: column; padding: 0">
            <!-- AI Visualizer Area -->
            <div style="height: 200px; background: #000; border-radius: 12px 12px 0 0; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden">
                <div id="waveform" class="waveform-pulse"></div>
                <div style="position: absolute; bottom: 1rem; left: 1rem; font-size: 0.75rem; color: #666; text-transform: uppercase; letter-spacing: 2px">IELTS EXAMINER ACTIVE</div>
            </div>

            <!-- Dialogue Area -->
            <div id="chat-history" style="flex: 1; padding: 2rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1.5rem">
                <!-- Messages will appear here -->
            </div>

            <!-- Input Area -->
            <div style="padding: 1.5rem; border-top: 1px solid var(--glass-border)">
                <div style="display: flex; gap: 1rem">
                    <input type="text" id="student-input" class="form-control" placeholder="Type your response here..." autocomplete="off">
                    <button onclick="sendMessage()" class="btn btn-primary">Send</button>
                </div>
                <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.75rem; text-align: center">Tip: Try to expand your answers with reasons and examples.</p>
            </div>
        </div>

        <!-- Feedback & Stats Sidebar -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem">
            <div class="glass-card">
                <h3 style="margin-bottom: 1.5rem; font-size: 1rem">Real-time Coaching</h3>
                <div id="live-feedback" style="color: var(--text-muted); font-size: 0.875rem">
                    Start the session to receive real-time tips and grammar corrections from the AI.
                </div>
            </div>

            <div class="glass-card" style="border-color: var(--primary)">
                <h3 style="margin-bottom: 1rem; font-size: 1rem">Tips for Performance</h3>
                <ul style="padding-left: 1.25rem; font-size: 0.75rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 0.75rem">
                    <li>Maintain fluency and avoid long pauses.</li>
                    <li>Use a wide range of vocabulary (lexical resource).</li>
                    <li>Ensure your grammar is accurate even with complex structures.</li>
                    <li>Answer the examiner's question directly then expand.</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        let sessionId = null;

        async function startSession() {
            document.getElementById('start-btn').disabled = true;
            document.getElementById('simulator-container').style.display = 'grid';
            
            addMessage('AI', 'Connecting to IELTS Examiner...');

            try {
                const response = await fetch('{{ route("student.speaking.start") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                const data = await response.json();
                sessionId = data.session_id;
                
                // Clear initial status and show actual AI message
                document.getElementById('chat-history').innerHTML = '';
                addMessage('AI', data.ai_message);
            } catch (error) {
                addMessage('AI', 'Failed to connect. Please try again.');
            }
        }

        async function sendMessage() {
            const input = document.getElementById('student-input');
            const message = input.value;
            if(!message || !sessionId) return;

            input.value = '';
            addMessage('Student', message);

            try {
                const response = await fetch('{{ route("student.speaking.chat") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ session_id: sessionId, message: message })
                });
                const data = await response.json();
                
                addMessage('AI', data.ai_message);
                if(data.feedback) showLiveFeedback(data.feedback);
            } catch (error) {
                addMessage('AI', 'Lost connection. Please check your internet.');
            }
        }

        function addMessage(sender, text) {
            const history = document.getElementById('chat-history');
            const msgDiv = document.createElement('div');
            msgDiv.style.alignSelf = sender === 'AI' ? 'flex-start' : 'flex-end';
            msgDiv.style.maxWidth = '80%';
            
            msgDiv.innerHTML = `
                <div style="font-size: 0.65rem; color: var(--text-muted); margin-bottom: 0.25rem; text-align: ${sender === 'AI' ? 'left' : 'right'}">${sender.toUpperCase()}</div>
                <div class="glass" style="padding: 1rem; border-radius: 12px; background: ${sender === 'AI' ? 'var(--glass)' : 'rgba(99, 102, 241, 0.2)'}">
                    ${text}
                </div>
            `;
            
            history.appendChild(msgDiv);
            history.scrollTop = history.scrollHeight;
        }

        function showLiveFeedback(feedback) {
            const fbArea = document.getElementById('live-feedback');
            fbArea.innerHTML = `
                ${feedback.grammar_correction ? `<div style="margin-bottom: 1rem; color: #ef4444"><strong>Correction:</strong> ${feedback.grammar_correction}</div>` : ''}
                ${feedback.tip ? `<div style="color: var(--accent)"><strong>Tip:</strong> ${feedback.tip}</div>` : ''}
            `;
        }
    </script>

    <style>
        .waveform-pulse {
            width: 80px;
            height: 80px;
            background: var(--primary);
            border-radius: 50%;
            filter: blur(10px);
            animation: pulseWave 2s infinite;
            opacity: 0.4;
        }
        @keyframes pulseWave {
            0% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.5); opacity: 0.6; }
            100% { transform: scale(1); opacity: 0.3; }
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
            #chat-history {
                height: 300px;
            }
        }
    </style>
</x-app-layout>
