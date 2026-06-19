{{--
    Single question block — included by _form.blade.php and reused via
    <template> for new rows. Variables:
      $index    int
      $question ?Question  (null for an empty new row)
--}}
@php
    $content = $question?->content ?? [];
    $options = $content['options'] ?? ['', '', '', ''];
    $type = old('questions.' . $index . '.type', $question?->type ?? 'mcq');
    $showOptions = $type === 'mcq';
@endphp
<div class="question-block" data-index="{{ $index }}" style="padding: 1.25rem; border: 1px solid var(--glass-border); border-radius: 12px; margin-bottom: 1rem; background: var(--bg-secondary)">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem">
        <strong style="font-size: 0.95rem">
            <span class="question-index">{{ $index + 1 }}</span>. Question
        </strong>
        <button type="button" class="remove-question-btn btn" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; background: transparent; color: var(--danger); border: 1px solid var(--danger)">✕ Remove</button>
    </div>

    <input type="hidden" name="questions[{{ $index }}][skill]" value="reading">

    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem">
        <div>
            <label style="display: block; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem">Type</label>
            <select name="questions[{{ $index }}][type]" class="form-control" style="width: 100%; padding: 0.4rem 0.6rem; font-size: 0.875rem">
                <option value="mcq" @selected($type === 'mcq')>Multiple Choice</option>
                <option value="gap_fill" @selected($type === 'gap_fill')>Gap Fill</option>
                <option value="short_answer" @selected($type === 'short_answer')>Short Answer</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem">Topic</label>
            <input type="text" name="questions[{{ $index }}][topic]" value="{{ old("questions.$index.topic", $question?->topic ?? 'General') }}" required maxlength="128" class="form-control" style="width: 100%; padding: 0.4rem 0.6rem; font-size: 0.875rem">
        </div>
        <div>
            <label style="display: block; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem">Difficulty</label>
            <select name="questions[{{ $index }}][difficulty]" class="form-control" style="width: 100%; padding: 0.4rem 0.6rem; font-size: 0.875rem">
                @foreach(['easy', 'medium', 'hard'] as $d)
                    <option value="{{ $d }}" @selected(old("questions.$index.difficulty", $question?->difficulty ?? 'medium') === $d)>{{ ucfirst($d) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div style="margin-bottom: 0.75rem">
        <label style="display: block; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem">Question text <span style="color: var(--danger)">*</span></label>
        <input type="text" name="questions[{{ $index }}][content][question]" value="{{ old("questions.$index.content.question", $content['question'] ?? '') }}" required maxlength="1000" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem">
    </div>

    <div class="options-wrap" style="display: {{ $showOptions ? 'block' : 'none' }}; margin-bottom: 0.75rem">
        <label style="display: block; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem">Options (MCQ)</label>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem">
            @for($i = 0; $i < 4; $i++)
                <input type="text" name="questions[{{ $index }}][content][options][]" value="{{ old("questions.$index.content.options.$i", $options[$i] ?? '') }}" maxlength="500" placeholder="Option {{ chr(65 + $i) }}" class="form-control" style="padding: 0.4rem 0.6rem; font-size: 0.875rem">
            @endfor
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem">
        <div>
            <label style="display: block; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem">Correct answer <span style="color: var(--danger)">*</span></label>
            <input type="text" name="questions[{{ $index }}][content][answer]" value="{{ old("questions.$index.content.answer", $content['answer'] ?? '') }}" required maxlength="1000" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem">
        </div>
        <div>
            <label style="display: block; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem">Explanation (optional)</label>
            <input type="text" name="questions[{{ $index }}][content][explanation]" value="{{ old("questions.$index.content.explanation", $content['explanation'] ?? '') }}" maxlength="2000" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem">
        </div>
    </div>
</div>
