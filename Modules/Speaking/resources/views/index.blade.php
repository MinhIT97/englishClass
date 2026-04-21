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
        <p style="margin-bottom: 0.5rem">2. The AI Examiner will introduce themselves and ask the first question.</p>
        <p style="margin-bottom: 0.5rem">3. Type your response in the chat or use voice (future update).</p>
        <p>4. Receive real-time grammar tips and feedback on the sidebar.</p>
    </div>

    <div id="simulator-container" class="speaking-grid" style="display: none; height: 650px;">
        <!-- Visual & Chat -->
        <div class="glass-card" style="display: flex; flex-direction: column; padding: 0; overflow: hidden">
            <!-- AI Visualizer Area -->
            <div class="ai-header-visual">
                <div id="waveform" class="waveform-pulse"></div>
                <div class="visual-status">IELTS EXAMINER ACTIVE</div>
            </div>

            <!-- Dialogue Area -->
            <div id="chat-history" class="chat-area">
                <!-- Messages will appear here -->
            </div>

            <!-- Input Area -->
            <div class="chat-input-area">
                <div style="display: flex; gap: 1rem">
                    <input type="text" id="student-input" class="form-control" placeholder="Type your response here..." autocomplete="off">
                    <button onclick="sendMessage()" id="send-btn" class="btn btn-primary">Send</button>
                </div>
                <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.75rem; text-align: center">Tip: Be descriptive and try to use varied vocabulary.</p>
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

        async function startSession() {
            const btn = document.getElementById('start-btn');
            const btnText = document.getElementById('start-btn-text');
            const btnIcon = document.getElementById('start-btn-icon');
            
            btn.disabled = true;
            btnText.textContent = 'Connecting...';
            btnIcon.style.animation = 'pulse 1s infinite';

            try {
                const response = await fetch('{{ route("student.speaking.start") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                
                if (!response.ok) throw new Error('Network response was not ok');
                
                const data = await response.json();
                sessionId = data.session_id;
                
                document.getElementById('how-to-use').style.display = 'none';
                document.getElementById('simulator-container').style.display = 'grid';
                
                // Clear initial status and show actual AI message
                document.getElementById('chat-history').innerHTML = '';
                addMessage('AI', data.ai_message);
            } catch (error) {
                console.error('Start Interview Error:', error);
                alert('Could not start session. Please check your internet or AI service.');
            } finally {
                btn.disabled = false;
                btnText.textContent = 'Start Interview Session';
                btnIcon.style.animation = 'none';
            }
        }

        async function sendMessage() {
            const input = document.getElementById('student-input');
            const btn = document.getElementById('send-btn');
            const message = input.value;
            if(!message || !sessionId) return;

            input.value = '';
            input.disabled = true;
            btn.disabled = true;
            btn.textContent = '...';
            
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
                addMessage('AI', 'System busy. Falling back to local feedback: Keep going, you are doing great!');
            } finally {
                input.disabled = false;
                btn.disabled = false;
                btn.textContent = 'Send';
                input.focus();
            }
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
                    <div style="margin-bottom: 1.5rem; padding: 1rem; background: rgba(239, 68, 68, 0.1); border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.2)">
                        <div style="color: #ef4444; font-weight: 700; font-size: 0.75rem; margin-bottom: 0.5rem">⚠️ CORRECTION</div>
                        <div style="color: var(--text-main)">${feedback.grammar_correction}</div>
                    </div>
                ` : ''}
                ${feedback.tip ? `
                    <div style="padding: 1rem; background: rgba(16, 185, 129, 0.1); border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.2)">
                        <div style="color: #10b981; font-weight: 700; font-size: 0.75rem; margin-bottom: 0.5rem">💡 IMPROVEMENT TIP</div>
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
            background: radial-gradient(circle, var(--primary) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(15px);
            animation: pulseWave 2s infinite;
            opacity: 0.3;
        }

        @keyframes pulseWave {
            0% { transform: scale(1); opacity: 0.2; }
            50% { transform: scale(2); opacity: 0.4; }
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
