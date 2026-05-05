<x-app-layout>
    @php
        $selectedSkillFocus = old('skill_focus', $set->skill_focus ? explode(',', (string) $set->skill_focus) : ['reading', 'listening', 'writing', 'speaking']);
        $setPublished = old('is_published', $set->is_published ?? false);
        $sections = old('sections', $formSections ?? []);
    @endphp

    <div style="display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap;">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.35rem;">{{ $set->exists ? 'Edit IELTS Set' : 'Create IELTS Set' }}</h1>
            <p style="color: var(--text-muted); max-width: 780px;">
                Build a structured IELTS set with multiple sections. Questions are assigned from the question bank and must match the selected skill for each section.
            </p>
        </div>
        <a href="{{ route('admin.sets.index') }}" class="btn btn-outline" style="padding: 0.9rem 1.5rem;">Back to Sets</a>
    </div>

    @if($errors->any())
        <div class="glass-card" style="padding: 1rem; margin-bottom: 1.5rem; border-color: #ef4444; color: #fca5a5;">
            <strong>There were validation issues:</strong>
            <ul style="margin: 0.75rem 0 0 1rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $formAction }}" id="ielts-set-form">
        @csrf
        @if($formMethod !== 'POST')
            @method($formMethod)
        @endif

        <div class="set-admin-grid">
            <div class="glass-card" style="padding: 1.5rem;">
                <h3 style="margin-bottom: 1rem;">Set Details</h3>

                <div class="form-group">
                    <label class="feedback-label">Title</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $set->title) }}" required>
                </div>

                <div class="form-group">
                    <label class="feedback-label">Slug</label>
                    <input type="text" name="slug" class="form-control" value="{{ old('slug', $set->slug) }}" required>
                </div>

                <div class="form-group">
                    <label class="feedback-label">Topic</label>
                    <input type="text" name="topic" class="form-control" value="{{ old('topic', $set->topic) }}" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="feedback-label">Set Type</label>
                        <select name="set_type" class="form-control" required>
                            <option value="full" {{ old('set_type', $set->set_type) === 'full' ? 'selected' : '' }}>Full</option>
                            <option value="skill" {{ old('set_type', $set->set_type) === 'skill' ? 'selected' : '' }}>Skill</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="feedback-label">Difficulty</label>
                        <select name="difficulty" class="form-control" required>
                            @foreach(['easy', 'medium', 'hard'] as $difficulty)
                                <option value="{{ $difficulty }}" {{ old('difficulty', $set->difficulty) === $difficulty ? 'selected' : '' }}>
                                    {{ ucfirst($difficulty) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="feedback-label">Target Band</label>
                        <input type="text" name="target_band" class="form-control" value="{{ old('target_band', $set->target_band) }}" required>
                    </div>

                    <div class="form-group">
                        <label class="feedback-label">Duration (minutes)</label>
                        <input type="number" min="1" max="600" name="duration_minutes" class="form-control" value="{{ old('duration_minutes', $set->duration_minutes) }}" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="feedback-label">Description</label>
                    <textarea name="description" class="form-control" style="min-height: 120px;">{{ old('description', $set->description) }}</textarea>
                </div>
            </div>

            <div class="glass-card" style="padding: 1.5rem;">
                <h3 style="margin-bottom: 1rem;">Publishing & Skill Focus</h3>

                <div class="form-group">
                    <label class="feedback-label">Skill Focus</label>
                    <div class="skill-focus-grid">
                        @foreach(['reading', 'listening', 'writing', 'speaking'] as $skill)
                            <label class="focus-chip">
                                <input type="checkbox" name="skill_focus[]" value="{{ $skill }}" {{ in_array($skill, $selectedSkillFocus, true) ? 'checked' : '' }}>
                                <span>{{ ucfirst($skill) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="form-group">
                    <label class="focus-toggle">
                        <input type="checkbox" name="is_published" value="1" {{ $setPublished ? 'checked' : '' }}>
                        <span>Publish this set for students</span>
                    </label>
                </div>

                @if($set->exists)
                    <div class="glass-card" style="padding: 1rem; background: rgba(255,255,255,0.02);">
                        <div style="font-weight: 700;">Current Summary</div>
                        <div style="margin-top: 0.5rem; color: var(--text-muted); font-size: 0.88rem;">
                            <div>Questions: {{ $set->total_questions }}</div>
                            <div>Sections: {{ $set->sections()->count() }}</div>
                            <div>Attempts: {{ $set->attempts()->count() }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="glass-card" style="padding: 1.5rem; margin-top: 1.5rem;">
            <div style="display: flex; justify-content: space-between; gap: 1rem; align-items: center; margin-bottom: 1rem; flex-wrap: wrap;">
                <div>
                    <h3 style="margin-bottom: 0.35rem;">Sections</h3>
                    <p style="color: var(--text-muted); font-size: 0.88rem;">
                        Add sections in the order students should complete them. Each section must only contain questions from the same skill.
                    </p>
                </div>
                <button type="button" class="btn btn-outline" id="add-section-btn" style="padding: 0.8rem 1.2rem;">Add Section</button>
            </div>

            <div id="sections-container"></div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
            <a href="{{ route('admin.sets.index') }}" class="btn btn-outline" style="padding: 0.9rem 1.5rem;">Cancel</a>
            <button class="btn btn-primary" style="padding: 0.9rem 1.5rem;">
                {{ $set->exists ? 'Update Set' : 'Create Set' }}
            </button>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const questionsBySkill = @json($questionsBySkill);
            const sectionsContainer = document.getElementById('sections-container');
            const addSectionBtn = document.getElementById('add-section-btn');
            const initialSections = @json(array_values($sections));

            function sectionCard(index, section = {}) {
                const wrapper = document.createElement('div');
                wrapper.className = 'section-admin-card glass-card';
                wrapper.style.padding = '1.25rem';
                wrapper.style.marginBottom = '1rem';

                wrapper.innerHTML = `
                    <input type="hidden" name="sections[${index}][id]" value="${section.id ?? ''}">
                    <div style="display:flex; justify-content:space-between; gap:1rem; align-items:center; margin-bottom:1rem; flex-wrap:wrap;">
                        <h4 style="margin:0;">Section ${index + 1}</h4>
                        <button type="button" class="btn btn-outline remove-section-btn" style="padding:0.7rem 1rem; border-color: rgba(239, 68, 68, 0.3); color:#fca5a5;">Remove</button>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="feedback-label">Skill</label>
                            <select name="sections[${index}][skill]" class="form-control section-skill" required>
                                <option value="reading">Reading</option>
                                <option value="listening">Listening</option>
                                <option value="writing">Writing</option>
                                <option value="speaking">Speaking</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="feedback-label">Time Limit (minutes)</label>
                            <input type="number" min="1" max="240" name="sections[${index}][time_limit_minutes]" class="form-control" value="${section.time_limit_minutes ?? ''}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="feedback-label">Section Title</label>
                        <input type="text" name="sections[${index}][title]" class="form-control" value="${section.title ?? ''}" required>
                    </div>
                    <div class="form-group">
                        <label class="feedback-label">Instructions</label>
                        <textarea name="sections[${index}][instructions]" class="form-control" style="min-height: 110px;">${section.instructions ?? ''}</textarea>
                    </div>
                    <div class="form-group">
                        <label class="feedback-label">Questions</label>
                        <select name="sections[${index}][question_ids][]" class="form-control section-questions" multiple size="8" required></select>
                        <div style="font-size:0.78rem; color: var(--text-muted); margin-top:0.4rem;">
                            Hold Ctrl/Cmd to select multiple questions.
                        </div>
                    </div>
                `;

                const skillSelect = wrapper.querySelector('.section-skill');
                const questionSelect = wrapper.querySelector('.section-questions');
                skillSelect.value = section.skill ?? 'reading';

                function renderQuestionOptions() {
                    const selectedValues = new Set((section.question_ids ?? []).map(String));
                    const skill = skillSelect.value;
                    const options = questionsBySkill[skill] ?? [];

                    questionSelect.innerHTML = '';

                    options.forEach((question) => {
                        const option = document.createElement('option');
                        option.value = question.id;
                        option.textContent = question.label;
                        option.selected = selectedValues.has(String(question.id));
                        questionSelect.appendChild(option);
                    });
                }

                skillSelect.addEventListener('change', function () {
                    section.question_ids = [];
                    renderQuestionOptions();
                });

                wrapper.querySelector('.remove-section-btn').addEventListener('click', function () {
                    wrapper.remove();
                    reindexSections();
                });

                renderQuestionOptions();

                return wrapper;
            }

            function reindexSections() {
                const cards = Array.from(sectionsContainer.querySelectorAll('.section-admin-card'));

                cards.forEach((card, index) => {
                    card.querySelector('h4').textContent = `Section ${index + 1}`;
                    card.querySelectorAll('input, select, textarea').forEach((field) => {
                        if (!field.name) {
                            return;
                        }

                        field.name = field.name.replace(/sections\[\d+\]/, `sections[${index}]`);
                    });
                });
            }

            function addSection(section = {}) {
                const index = sectionsContainer.querySelectorAll('.section-admin-card').length;
                sectionsContainer.appendChild(sectionCard(index, section));
                reindexSections();
            }

            addSectionBtn.addEventListener('click', function () {
                addSection({
                    skill: 'reading',
                    title: '',
                    instructions: '',
                    time_limit_minutes: '',
                    question_ids: [],
                });
            });

            if (initialSections.length > 0) {
                initialSections.forEach(addSection);
            } else {
                addSection({
                    skill: 'reading',
                    title: 'Reading Section',
                    instructions: '',
                    time_limit_minutes: '',
                    question_ids: [],
                });
            }
        });
    </script>

    <style>
        .set-admin-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .skill-focus-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .focus-chip,
        .focus-toggle {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.85rem 1rem;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            color: var(--text-main);
        }

        @media (max-width: 900px) {
            .set-admin-grid,
            .form-row,
            .skill-focus-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</x-app-layout>
