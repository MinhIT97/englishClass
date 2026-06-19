{{--
    Shared form for create + edit. Used by:
      - create.blade.php  (passage = null)
      - edit.blade.php    (passage = existing ReadingPassage)
    Expects:
      $passage ? ReadingPassage|null
      $action  string  the form action URL
      $method  string  'POST' for create, 'PUT' for edit
--}}
@php
    $isEdit = $passage !== null;
    $existingQuestions = $isEdit
        ? $passage->passageQuestions->pluck('question')->filter()->values()
        : collect();
@endphp

<form action="{{ $action }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    {{-- Section 1: Passage metadata --}}
    <div class="glass-card" style="padding: 1.75rem; margin-bottom: 1.5rem">
        <h3 style="font-size: 1.05rem; font-weight: 600; margin-bottom: 1rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em">📖 Passage Info</h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem">
            <div>
                <label style="display: block; margin-bottom: 0.4rem; font-size: 0.875rem; font-weight: 500">Title <span style="color: var(--danger)">*</span></label>
                <input type="text" name="title" value="{{ old('title', $passage?->title) }}" required maxlength="255" class="form-control" style="width: 100%; padding: 0.6rem 0.9rem">
                @error('title')<div style="color: var(--danger); font-size: 0.8rem; margin-top: 0.25rem">{{ $message }}</div>@enderror
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.4rem; font-size: 0.875rem; font-weight: 500">Slug (auto if blank)</label>
                <input type="text" name="slug" value="{{ old('slug', $passage?->slug) }}" maxlength="128" pattern="[a-z0-9\-]+" class="form-control" style="width: 100%; padding: 0.6rem 0.9rem; font-family: monospace">
                @error('slug')<div style="color: var(--danger); font-size: 0.8rem; margin-top: 0.25rem">{{ $message }}</div>@enderror
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem">
            <div>
                <label style="display: block; margin-bottom: 0.4rem; font-size: 0.875rem; font-weight: 500">Topic</label>
                <select name="topic_id" class="form-control" style="width: 100%; padding: 0.6rem 0.9rem">
                    <option value="">— No topic —</option>
                    @foreach($topics as $topic)
                        <option value="{{ $topic->id }}" @selected(old('topic_id', $passage?->topic_id) == $topic->id)>
                            {{ $topic->name_en }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.4rem; font-size: 0.875rem; font-weight: 500">Difficulty <span style="color: var(--danger)">*</span></label>
                <select name="difficulty" required class="form-control" style="width: 100%; padding: 0.6rem 0.9rem">
                    @foreach(['easy', 'medium', 'hard'] as $diff)
                        <option value="{{ $diff }}" @selected(old('difficulty', $passage?->difficulty ?? 'medium') === $diff)>{{ ucfirst($diff) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.4rem; font-size: 0.875rem; font-weight: 500">Word count</label>
                <input type="number" name="word_count" value="{{ old('word_count', $passage?->word_count) }}" min="1" max="20000" class="form-control" style="width: 100%; padding: 0.6rem 0.9rem">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.4rem; font-size: 0.875rem; font-weight: 500">Est. minutes</label>
                <input type="number" name="estimated_minutes" value="{{ old('estimated_minutes', $passage?->estimated_minutes) }}" min="1" max="60" class="form-control" style="width: 100%; padding: 0.6rem 0.9rem">
            </div>
        </div>

        <div style="margin-bottom: 1rem">
            <label style="display: block; margin-bottom: 0.4rem; font-size: 0.875rem; font-weight: 500">Body <span style="color: var(--danger)">*</span></label>
            <textarea name="body" required rows="12" minlength="50" maxlength="20000" class="form-control" style="width: 100%; padding: 0.75rem 0.9rem; font-family: Georgia, serif; line-height: 1.6; font-size: 0.95rem">{{ old('body', $passage?->body) }}</textarea>
            @error('body')<div style="color: var(--danger); font-size: 0.8rem; margin-top: 0.25rem">{{ $message }}</div>@enderror
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem">
            <div>
                <label style="display: block; margin-bottom: 0.4rem; font-size: 0.875rem; font-weight: 500">Source</label>
                <input type="text" name="source" value="{{ old('source', $passage?->source) }}" maxlength="128" placeholder="e.g. Cambridge IELTS 17" class="form-control" style="width: 100%; padding: 0.6rem 0.9rem">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.4rem; font-size: 0.875rem; font-weight: 500">Tags (comma separated)</label>
                <input type="text" name="tags" value="{{ old('tags', is_array($passage?->tags) ? implode(', ', $passage->tags) : '') }}" maxlength="255" class="form-control" style="width: 100%; padding: 0.6rem 0.9rem">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.4rem; font-size: 0.875rem; font-weight: 500">Status</label>
                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 0; cursor: pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $passage?->is_active ?? true))>
                    <span>Active (visible to students)</span>
                </label>
            </div>
        </div>
    </div>

    {{-- Section 2: Questions --}}
    <div class="glass-card" style="padding: 1.75rem; margin-bottom: 1.5rem">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem">
            <h3 style="font-size: 1.05rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin: 0">❓ Questions</h3>
            <button type="button" id="add-question-btn" class="btn btn-outline" style="padding: 0.4rem 0.9rem; font-size: 0.85rem">+ Add Question</button>
        </div>

        <div id="questions-container">
            @if($existingQuestions->isNotEmpty())
                @foreach($existingQuestions as $i => $q)
                    @include('telegrambot::admin.reading-passages._question_block', [
                        'index' => $i,
                        'question' => $q,
                    ])
                @endforeach
            @else
                @include('telegrambot::admin.reading-passages._question_block', [
                    'index' => 0,
                    'question' => null,
                ])
            @endif
        </div>

        <template id="question-template">
            @include('telegrambot::admin.reading-passages._question_block', [
                'index' => '__INDEX__',
                'question' => null,
            ])
        </template>
    </div>

    {{-- Actions --}}
    <div style="display: flex; gap: 0.75rem; justify-content: flex-end; align-items: center">
        <a href="{{ route('admin.reading-passages.index') }}" class="btn btn-outline" style="padding: 0.7rem 1.25rem">Cancel</a>
        <button type="submit" class="btn btn-primary" style="padding: 0.7rem 1.5rem">
            {{ $isEdit ? '💾 Save Changes' : '✨ Create Passage' }}
        </button>
    </div>
</form>

<script>
    (function () {
        const container = document.getElementById('questions-container');
        const addBtn = document.getElementById('add-question-btn');
        const template = document.getElementById('question-template');
        if (!container || !addBtn || !template) return;

        let index = container.querySelectorAll('.question-block').length;

        function renumber() {
            container.querySelectorAll('.question-block').forEach((block, i) => {
                block.dataset.index = i;
                block.querySelector('.question-index').textContent = (i + 1);
                // Update all name="questions[__INDEX__][...]" -> "questions[i][...]"
                block.querySelectorAll('[name]').forEach((el) => {
                    el.name = el.name.replace(/questions\[\d+\]/, 'questions[' + i + ']');
                });
            });
            index = container.querySelectorAll('.question-block').length;
        }

        addBtn.addEventListener('click', () => {
            const html = template.innerHTML.replace(/__INDEX__/g, index);
            const wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            container.appendChild(wrap.firstChild);
            renumber();
        });

        container.addEventListener('click', (e) => {
            const remove = e.target.closest('.remove-question-btn');
            if (!remove) return;
            const block = remove.closest('.question-block');
            if (!block) return;
            if (container.querySelectorAll('.question-block').length <= 1) {
                alert('A passage must have at least one question.');
                return;
            }
            block.remove();
            renumber();
        });

        container.addEventListener('change', (e) => {
            const typeSelect = e.target.closest('select[name$="[type]"]');
            if (!typeSelect) return;
            const block = typeSelect.closest('.question-block');
            const optionsWrap = block.querySelector('.options-wrap');
            if (typeSelect.value === 'mcq') {
                optionsWrap.style.display = 'block';
            } else {
                optionsWrap.style.display = 'none';
            }
        });
    })();
</script>
