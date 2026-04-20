<x-app-layout>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem">
        <div>
            <h1 style="font-size: 1.5rem">Create New Question</h1>
            <p style="color: var(--text-muted)">Add a question manually to the bank.</p>
        </div>
        <a href="{{ route('admin.questions.index') }}" class="btn btn-outline" style="border-radius: 50px">Back to Bank</a>
    </div>

    <div class="glass-card" style="max-width: 800px; margin: 0 auto; padding: 2.5rem">
        <form action="{{ route('admin.questions.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem">Skill</label>
                    <select name="skill" id="skill-select" required onchange="updateFormFields()" style="width: 100%; background: var(--bg-main); border: 1px solid var(--glass-border); padding: 0.75rem; border-radius: 12px">
                        <option value="reading">Reading</option>
                        <option value="listening">Listening</option>
                        <option value="writing">Writing</option>
                        <option value="speaking">Speaking</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem">Type</label>
                    <select name="type" required style="width: 100%; background: var(--bg-main); border: 1px solid var(--glass-border); padding: 0.75rem; border-radius: 12px">
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="gap_fill">Gap Fill</option>
                        <option value="true_false">True / False</option>
                        <option value="short_answer">Short Answer</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem">Topic</label>
                    <input type="text" name="topic" required placeholder="e.g. Environment, Education" style="width: 100%; background: var(--bg-main); border: 1px solid var(--glass-border); padding: 0.75rem; border-radius: 12px">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem">Difficulty</label>
                    <select name="difficulty" style="width: 100%; background: var(--bg-main); border: 1px solid var(--glass-border); padding: 0.75rem; border-radius: 12px">
                        <option value="easy">Easy</option>
                        <option value="medium">Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
            </div>

            <!-- Listening Specific: Audio Upload -->
            <div id="listening-fields" style="display: none; background: rgba(99, 102, 241, 0.05); padding: 1.5rem; border-radius: 16px; margin-bottom: 2rem; border: 1px dashed var(--primary)">
                <h4 style="margin-bottom: 1rem; color: var(--primary)">Listening & Pronunciation 🎧</h4>
                <div style="margin-bottom: 1rem">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem">Upload Audio File (.mp3, .wav)</label>
                    <input type="file" name="audio_file" id="audio-file-input" accept=".mp3,.wav" style="width: 100%" onchange="previewUploadedAudio(this)">
                    <div id="upload-preview-container" style="margin-top: 1rem; display: none;">
                        <audio id="upload-audio-preview" controls style="width: 100%"></audio>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 1rem">
                    <div style="flex: 1; height: 1px; background: var(--glass-border)"></div>
                    <span style="font-size: 0.75rem; color: var(--text-muted)">OR</span>
                    <div style="flex: 1; height: 1px; background: var(--glass-border)"></div>
                </div>
                <div style="margin-top: 1rem">
                    <button type="button" onclick="generateAIVoice()" id="tts-btn" class="btn btn-outline" style="font-size: 0.75rem; border-radius: 50px">Use AI Voice Generator ✨</button>
                    <p style="font-size: 0.7rem; color: var(--text-muted); margin-top: 5px">Generated from the question text below.</p>
                </div>
            </div>

            <!-- Question Content -->
            <div style="background: var(--bg-secondary); padding: 1.5rem; border-radius: 16px; border: 1px solid var(--glass-border); margin-bottom: 2rem">
                <h4 style="margin-bottom: 1rem">Question Content</h4>
                
                <div style="margin-bottom: 1rem">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem">Question / Prompt</label>
                    <textarea name="content[question]" id="question-text" required rows="3" style="width: 100%; background: var(--bg-main); border: 1px solid var(--glass-border); padding: 1rem; border-radius: 12px"></textarea>
                </div>

                <div id="options-container">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem">Options (for Multiple Choice)</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem">
                        <input type="text" name="content[options][]" placeholder="Option A" style="width: 100%; background: var(--bg-main); border: 1px solid var(--glass-border); padding: 0.5rem; border-radius: 8px">
                        <input type="text" name="content[options][]" placeholder="Option B" style="width: 100%; background: var(--bg-main); border: 1px solid var(--glass-border); padding: 0.5rem; border-radius: 8px">
                        <input type="text" name="content[options][]" placeholder="Option C" style="width: 100%; background: var(--bg-main); border: 1px solid var(--glass-border); padding: 0.5rem; border-radius: 8px">
                        <input type="text" name="content[options][]" placeholder="Option D" style="width: 100%; background: var(--bg-main); border: 1px solid var(--glass-border); padding: 0.5rem; border-radius: 8px">
                    </div>
                </div>

                <div style="margin-top: 1.5rem">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem">Correct Answer</label>
                    <input type="text" name="content[answer]" required placeholder="The exact correct answer" style="width: 100%; background: var(--bg-main); border: 1px solid var(--glass-border); padding: 0.75rem; border-radius: 12px">
                </div>

                <div style="margin-top: 1.5rem">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem">Explanation / Feedback</label>
                    <textarea name="content[explanation]" rows="2" style="width: 100%; background: var(--bg-main); border: 1px solid var(--glass-border); padding: 0.75rem; border-radius: 12px"></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-weight: 700; border-radius: 12px">Save Question to Bank</button>
        </form>
    </div>

    <script>
        function updateFormFields() {
            const skill = document.getElementById('skill-select').value;
            const listeningFields = document.getElementById('listening-fields');
            
            if (skill === 'listening') {
                listeningFields.style.display = 'block';
            } else {
                listeningFields.style.display = 'none';
            }
        }

        function previewUploadedAudio(input) {
            const container = document.getElementById('upload-preview-container');
            const audio = document.getElementById('upload-audio-preview');
            if (input.files && input.files[0]) {
                const url = URL.createObjectURL(input.files[0]);
                audio.src = url;
                container.style.display = 'block';
            }
        }

        async function generateAIVoice() {
            const text = document.getElementById('question-text').value;
            if (!text) {
                alert('Please enter some text in the question field first!');
                return;
            }

            const btn = document.getElementById('tts-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Generating Voice... ⌛';
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
                    btn.innerHTML = 'Voice Ready! ✅';
                    
                    let voiceInput = document.getElementById('ai-voice-path');
                    if (!voiceInput) {
                        voiceInput = document.createElement('input');
                        voiceInput.type = 'hidden';
                        voiceInput.name = 'ai_voice_path';
                        voiceInput.id = 'ai-voice-path';
                        btn.parentElement.appendChild(voiceInput);
                    }
                    voiceInput.value = data.path;
                    
                    let preview = document.getElementById('audio-preview');
                    if (!preview) {
                        preview = document.createElement('audio');
                        preview.id = 'audio-preview';
                        preview.controls = true;
                        preview.style.display = 'block';
                        preview.style.marginTop = '1rem';
                        preview.style.width = '100%';
                        btn.parentElement.appendChild(preview);
                    }
                    preview.src = data.path;
                    preview.play();
                } else {
                    alert('Could not generate voice. Please try again.');
                }
            } catch (e) {
                alert('An error occurred.');
            } finally {
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }, 3000);
            }
        }
        
        updateFormFields();
    </script>
</x-app-layout>
