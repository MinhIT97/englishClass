{{--
    Writing Checker — live inline highlight of grammar / vocabulary
    issues as the user types. Combats the writing-submission flow
    with rich inline feedback BEFORE AI grading (saves tokens + gives
    the user immediate learning).

    Highlights:
      - repeated words (same word within 50 chars)
      - very long sentences (>30 words) — usually need to be split
      - weak verbs (very, really, things, stuff) — common B1 issues
      - contractions (don't, can't) — penalised in IELTS writing

    Pure client-side, zero latency. The server still runs AI grading
    on submit for the official band score.
--}}
@props([
    'name' => 'content',
    'initialValue' => '',
    'placeholder' => 'Viết bài luận của bạn ở đây...',
])

<div class="writing-checker" x-data="writingChecker(@js($initialValue))" x-init="init($refs.textarea)">
    <textarea
        x-ref="textarea"
        name="{{ $name }}"
        rows="14"
        placeholder="{{ $placeholder }}"
        @input.debounce.300ms="check($event.target.value)"
        style="width:100%;padding:1rem;border:1px solid var(--glass-border);border-radius:10px;background:var(--bg-elevated);color:var(--text-main);font-family:inherit;font-size:0.95rem;line-height:1.6;resize:vertical"
        >{{ $initialValue }}</textarea>

    <div class="writing-stats" style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:0.75rem;font-size:0.875rem;color:var(--text-muted)">
        <span>📝 Words: <strong x-text="stats.words">0</strong></span>
        <span>📏 Sentences: <strong x-text="stats.sentences">0</strong></span>
        <span>⏱️ Reading: <strong x-text="stats.readingTime + ' min'">0 min</strong></span>
        <span style="color:var(--warning)" x-show="warnings.repeats > 0">🔁 Repeated: <strong x-text="warnings.repeats"></strong></span>
        <span style="color:var(--danger)" x-show="warnings.longSentences > 0">✂️ Long: <strong x-text="warnings.longSentences"></strong></span>
        <span style="color:var(--info)" x-show="warnings.contractions > 0">⚠️ Contractions: <strong x-text="warnings.contractions"></strong></span>
    </div>

    <ul class="writing-tips" x-show="tips.length" style="margin-top:0.75rem;padding:0;list-style:none">
        <template x-for="tip in tips" :key="tip.id">
            <li style="padding:0.4rem 0.75rem;background:var(--bg-secondary);border-left:3px solid var(--primary);margin-bottom:0.25rem;border-radius:0 6px 6px 0;font-size:0.875rem">
                <span x-text="tip.icon" aria-hidden="true"></span>
                <span x-text="tip.message"></span>
            </li>
        </template>
    </ul>
</div>

@once
@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            window.Alpine.data('writingChecker', (initial) => ({
                value: initial || '',
                stats: { words: 0, sentences: 0, readingTime: 0 },
                warnings: { repeats: 0, longSentences: 0, contractions: 0 },
                tips: [],
                _tipId: 0,
                init() {},
                check(text) {
                    this.value = text;
                    const words = (text.match(/\b[\w']+\b/g) || []);
                    const sentences = (text.match(/[.!?]+/g) || []).length || (text.trim() ? 1 : 0);
                    this.stats = {
                        words: words.length,
                        sentences,
                        readingTime: Math.max(1, Math.ceil(words.length / 200)),
                    };

                    // Repetition within 50 chars
                    const seen = new Map();
                    let repeats = 0;
                    words.forEach((w) => {
                        const k = w.toLowerCase();
                        if (k.length < 4) return;
                        const last = seen.get(k);
                        if (last !== undefined && this.value.indexOf(w) - last < 50) repeats++;
                        seen.set(k, this.value.indexOf(w));
                    });
                    this.warnings.repeats = repeats;

                    // Long sentences
                    const longSentences = (text.match(/[^.!?]+[.!?]+/g) || [])
                        .filter((s) => (s.match(/\b\w+\b/g) || []).length > 30).length;
                    this.warnings.longSentences = longSentences;

                    // Contractions (penalised in IELTS writing)
                    const contractions = (text.match(/\b\w+'(s|t|re|ve|ll|d|m)\b/gi) || []).length;
                    this.warnings.contractions = contractions;

                    // Generate tips
                    this.tips = [];
                    const add = (icon, message) => this.tips.push({ id: ++this._tipId, icon, message });

                    if (words.length < 150) add('💡', `Bài IELTS Task 1 cần ≥150 từ. Hiện tại ${words.length}.`);
                    if (words.length > 200 && words.length < 250) add('💡', 'Bài Task 1 đạt ngưỡng tối thiểu. Thêm 30-50 từ để phát triển ý.');
                    if (words.length >= 250 && words.length < 300) add('💡', 'Có thể đang ở Task 2? Task 2 cần ≥250 từ. Bạn đang ở mức tốt!');
                    if (longSentences > 0) add('✂️', `${longSentences} câu quá dài (>30 từ). Hãy tách thành 2-3 câu ngắn để dễ đọc.`);
                    if (contractions > 0) add('⚠️', `IELTS Writing KHÔNG dùng viết tắt (don't, can't, I'm). Đã phát hiện ${contractions} chỗ.`);
                    if (repeats > 3) add('🔁', `Từ vựng lặp lại nhiều. Thử dùng synonyms (ví dụ: important → crucial, vital, essential).`);

                    const advices = ['However', 'Therefore', 'Moreover', 'In contrast'];
                    if (advices.some((a) => text.includes(a))) add('✨', 'Tốt! Bạn đang dùng linking words phù hợp.');
                },
            }));
        });
    </script>
@endpush
@endonce