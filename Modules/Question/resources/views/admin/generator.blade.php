<x-app-layout>
    <div style="margin-bottom: 2.5rem">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem">AI Content Generator</h1>
        <p style="color: var(--text-muted)">Harness Gemini AI to bootstrap your question bank in seconds.</p>
    </div>

    <div style="display: grid; grid-template-columns: 350px 1fr; gap: 2rem">
        <!-- Configuration -->
        <div class="glass-card" style="height: fit-content">
            <h3 style="margin-bottom: 1.5rem">Generator Settings</h3>
            <div class="form-group">
                <label class="form-label">Target Skill</label>
                <select id="gen-skill" class="form-control">
                    <option value="reading">Reading</option>
                    <option value="listening">Listening</option>
                    <option value="writing">Writing (Task 1 Data)</option>
                    <option value="speaking">Speaking (Part 1 Q&A)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Topic / Keyword</label>
                <input type="text" id="gen-topic" class="form-control" list="topic-presets" placeholder="e.g. Climate Change, Education">
                <datalist id="topic-presets">
                    @foreach($topics as $topic)
                        <option value="{{ $topic }}"></option>
                    @endforeach
                </datalist>
            </div>
            <div class="form-group">
                <label class="form-label">Target Band</label>
                <select id="gen-band" class="form-control">
                    @foreach($bands as $band)
                        <option value="{{ $band }}">{{ $band }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Number of Questions</label>
                <input type="number" id="gen-count" class="form-control" value="6" min="1" max="12">
            </div>

            <button onclick="generateAI()" class="btn btn-primary" style="width: 100%; gap: 0.5rem">
                <span>🪄</span> Generate with Gemini
            </button>
        </div>

        <!-- Preview & Review -->
        <div>
            <div id="loading-state" style="display: none; text-align: center; padding: 5rem">
                <div style="font-size: 3rem; animation: pulse 1.5s infinite">✨</div>
                <p style="color: var(--text-muted)">AI is composing questions... Please wait.</p>
            </div>

            <div id="empty-state" style="text-align: center; padding: 5rem" class="glass">
                <p style="color: var(--text-muted)">Set the configuration and start generating content.</p>
            </div>

            <div id="results-container" style="display: none">
                <h3 style="margin-bottom: 1.5rem">Review Generated Content</h3>
                <div id="questions-list" style="display: flex; flex-direction: column; gap: 1.5rem; margin-bottom: 2rem"></div>
                
                <button onclick="saveQuestions()" class="btn btn-primary" style="width: 100%; padding: 1rem; background-color: var(--accent)">
                    ✅ Add All to Question Bank
                </button>
            </div>
        </div>
    </div>

    <script>
        let generatedData = [];

        async function generateAI() {
            const skill = document.getElementById('gen-skill').value;
            const topic = document.getElementById('gen-topic').value;
            const band = document.getElementById('gen-band').value;
            const count = document.getElementById('gen-count').value;

            if(!topic) return alert('Please enter a topic');

            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('results-container').style.display = 'none';
            document.getElementById('loading-state').style.display = 'block';

            try {
                const response = await fetch('{{ route("admin.questions.generate") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ skill, topic, band, count })
                });

                generatedData = await response.json();
                renderQuestions();
            } catch (error) {
                alert('Generation failed. Check console.');
                document.getElementById('empty-state').style.display = 'block';
            } finally {
                document.getElementById('loading-state').style.display = 'none';
            }
        }

        function renderQuestions() {
            const list = document.getElementById('questions-list');
            const skill = document.getElementById('gen-skill').value;
            list.innerHTML = '';
            
            generatedData.forEach((q, index) => {
                const card = document.createElement('div');
                card.className = 'glass-card';
                
                let audioHtml = '';
                if (skill === 'listening') {
                    audioHtml = `
                        <div style="margin-top: 1rem; padding: 1rem; background: rgba(99, 102, 241, 0.05); border-radius: 12px; border: 1px dashed var(--primary)">
                            <button onclick="generateVoiceForQuestion(${index}, this)" class="btn btn-outline" style="font-size: 0.75rem; border-radius: 50px">
                                🔊 Generate AI Voice
                            </button>
                            <div id="audio-preview-${index}" style="margin-top: 1rem; display: none;"></div>
                        </div>
                    `;
                }

                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem">
                        <span class="badge" style="background: var(--primary)">${q.type.toUpperCase()}</span>
                        <span style="color: var(--text-muted); font-size: 0.75rem">#${index + 1}</span>
                    </div>
                    <p style="margin-bottom: 1rem; line-height: 1.6">${q.content.question || q.content.text}</p>
                    ${audioHtml}
                    <div style="background: rgba(16, 185, 129, 0.1); padding: 1rem; border-radius: 8px; margin-top: 1rem">
                        <strong style="color: var(--accent)">Correct Answer:</strong> ${q.content.answer}
                    </div>
                `;
                list.appendChild(card);
            });

            document.getElementById('results-container').style.display = 'block';
        }

        async function generateVoiceForQuestion(index, btn) {
            const text = generatedData[index].content.question || generatedData[index].content.text;
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Generating... ⌛';
            btn.disabled = true;

            try {
                const response = await fetch('{{ route("admin.questions.generate_voice") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ text: text })
                });

                const data = await response.json();
                if (data.path) {
                    btn.innerHTML = 'Voice Generated! ✅';
                    generatedData[index].audio_path = data.path;
                    
                    const previewContainer = document.getElementById(`audio-preview-${index}`);
                    previewContainer.innerHTML = `<audio controls style="width: 100%"><source src="${data.path}" type="audio/mpeg"></audio>`;
                    previewContainer.style.display = 'block';
                }
            } catch (e) {
                alert('TTS Generation failed.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        async function saveQuestions() {
            try {
                const response = await fetch('{{ route("admin.questions.store_batch") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ questions: generatedData })
                });

                if(response.ok) {
                    alert('Questions saved successfully!');
                    location.href = '{{ route("admin.questions.index") }}';
                }
            } catch (error) {
                alert('Save failed.');
            }
        }
    </script>

    <style>
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 1; }
            100% { transform: scale(1); opacity: 0.5; }
        }
    </style>
</x-app-layout>
