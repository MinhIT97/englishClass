<x-app-layout>
    @push('head')
        <title>Flashcards - Luyện từ vựng SRS</title>
    @endpush

    <div style="max-width: 720px; margin: 0 auto">
        <header style="margin-bottom: 1.5rem">
            <h1 style="font-size: 1.75rem; margin-bottom: 0.25rem">📚 Flashcards</h1>
            <p style="color: var(--text-muted)">Lặp lại cách quãng giúp nhớ từ lâu hơn. Hôm nay bạn có <strong>{{ $count }}</strong> thẻ cần ôn.</p>
        </header>

        {{-- Progress bar --}}
        <div style="margin-bottom: 1rem">
            <div style="display:flex;justify-content:space-between;font-size:0.875rem;margin-bottom:0.25rem;color:var(--text-muted)">
                <span id="progress-text">Tiến độ: 0 / {{ max($count, 1) }}</span>
                <span id="streak-text">🔥 Streak: {{ auth()->user()->streak ?? 0 }} ngày</span>
            </div>
            <div style="height:8px;background:var(--bg-secondary);border-radius:999px;overflow:hidden">
                <div id="progress-bar" style="width:0%;height:100%;background:linear-gradient(90deg,var(--primary),var(--accent));transition:width 0.3s"></div>
            </div>
        </div>

        {{-- Empty state --}}
        @if($count === 0)
            <x-empty-state
                icon="🎉"
                title="Không có thẻ nào cần ôn hôm nay"
                description="Bạn đã hoàn thành tất cả các thẻ trong hàng đợi. Hãy học thêm bài mới để có thêm từ vựng."
            />
        @endif

        {{-- Card stack --}}
        <div id="flashcard-stack" style="position:relative;height:360px;margin-bottom:1rem">
            @foreach($cards as $i => $card)
                <article class="flashcard" data-index="{{ $i }}" data-schedule="{{ $card->schedule_id }}"
                         style="position:{{ $i === 0 ? 'relative' : 'absolute' }};inset:0;background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:14px;padding:1.5rem;display:{{ $i === 0 ? 'flex' : 'none' }};flex-direction:column;justify-content:space-between;box-shadow:var(--shadow-card)">

                    <div style="text-align:center;flex:1;display:flex;flex-direction:column;justify-content:center">
                        <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem">
                            @if($card->pos) {{ $card->pos }} @endif
                            @if($card->ipa) · {{ $card->ipa }} @endif
                        </div>
                        <h2 class="flashcard-front" style="font-size:2.5rem;font-weight:700;margin:0 0 1rem">{{ $card->word }}</h2>

                        <div class="flashcard-back" style="display:none;font-size:1rem;line-height:1.6">
                            <p style="margin-bottom:0.5rem"><strong>🇻🇳</strong> {{ $card->meaning_vi }}</p>
                            @if($card->meaning_en)
                                <p style="margin-bottom:0.5rem;color:var(--text-muted)"><strong>🇬🇧</strong> {{ $card->meaning_en }}</p>
                            @endif
                            @if($card->example_en)
                                <p style="margin-top:1rem;padding:0.75rem;background:var(--bg-secondary);border-radius:8px;font-style:italic">
                                    "{{ $card->example_en }}"
                                    @if($card->example_vi)<br><span style="color:var(--text-muted);font-style:normal">{{ $card->example_vi }}</span>@endif
                                </p>
                            @endif
                        </div>
                    </div>

                    <div style="display:flex;gap:0.5rem;justify-content:center;margin-top:1rem">
                        <button type="button" data-action="flip" class="btn btn-outline" style="flex:1;padding:0.6rem">
                            🔄 Lật thẻ (<kbd>Space</kbd>)
                        </button>
                    </div>

                    <div class="grade-row" style="display:none;gap:0.5rem;margin-top:0.5rem">
                        <button type="button" data-grade="0" class="btn btn-outline" style="flex:1;border-color:var(--danger);color:var(--danger);padding:0.6rem">😖 Lại <kbd>1</kbd></button>
                        <button type="button" data-grade="1" class="btn btn-outline" style="flex:1;border-color:var(--warning);color:var(--warning);padding:0.6rem">😣 Khó <kbd>2</kbd></button>
                        <button type="button" data-grade="2" class="btn btn-outline" style="flex:1;border-color:var(--info);color:var(--info);padding:0.6rem">👍 Tốt <kbd>3</kbd></button>
                        <button type="button" data-grade="3" class="btn btn-outline" style="flex:1;border-color:var(--accent);color:var(--accent);padding:0.6rem">🎉 Dễ <kbd>4</kbd></button>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Keyboard hint --}}
        <p style="text-align:center;color:var(--text-muted);font-size:0.75rem">
            Phím tắt: <kbd>Space</kbd> lật thẻ · <kbd>1-4</kbd> chấm điểm · <kbd>N</kbd> thẻ tiếp
        </p>
    </div>

    @once
    @push('scripts')
        <script>
            (function () {
                const stack = document.getElementById('flashcard-stack');
                if (!stack) return;

                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const total = stack.querySelectorAll('.flashcard').length;
                let reviewed = 0;

                function updateProgress() {
                    document.getElementById('progress-text').textContent = `Tiến độ: ${reviewed} / ${total}`;
                    const pct = total > 0 ? (reviewed / total) * 100 : 100;
                    document.getElementById('progress-bar').style.width = pct + '%';
                }

                async function grade(card, grade) {
                    const scheduleId = card.dataset.schedule;
                    card.style.transition = 'transform 0.3s, opacity 0.3s';
                    card.style.transform = 'translateX(-100%)';
                    card.style.opacity = '0';
                    try {
                        await fetch(`/flashcards/${scheduleId}/grade`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ grade }),
                        });
                    } catch (e) { /* offline — will retry next session */ }
                    reviewed++;
                    updateProgress();
                    if (reviewed === total && window.celebrate) window.celebrate({ particleCount: 150 });
                    setTimeout(() => {
                        card.style.display = 'none';
                        const next = stack.querySelector(`.flashcard[data-index="${parseInt(card.dataset.index, 10) + 1}"]`);
                        if (next) {
                            next.style.display = 'flex';
                            next.style.opacity = '0';
                            requestAnimationFrame(() => {
                                next.style.transition = 'opacity 0.3s';
                                next.style.opacity = '1';
                            });
                        }
                    }, 300);
                }

                function flipCard(card) {
                    card.querySelector('.flashcard-front').style.display = 'none';
                    card.querySelector('.flashcard-back').style.display = 'block';
                    card.querySelector('[data-action="flip"]').style.display = 'none';
                    card.querySelector('.grade-row').style.display = 'flex';
                }

                stack.addEventListener('click', (e) => {
                    const flip = e.target.closest('[data-action="flip"]');
                    if (flip) return flipCard(flip.closest('.flashcard'));
                    const gradeBtn = e.target.closest('[data-grade]');
                    if (gradeBtn) {
                        grade(gradeBtn.closest('.flashcard'), parseInt(gradeBtn.dataset.grade, 10));
                    }
                });

                document.addEventListener('keydown', (e) => {
                    if (e.target.matches('input,textarea')) return;
                    const visible = stack.querySelector('.flashcard:not([style*="display: none"])');
                    if (!visible) return;
                    if (e.key === ' ' || e.key === 'Spacebar') {
                        e.preventDefault();
                        const flipped = visible.querySelector('.flashcard-back').style.display === 'block';
                        if (!flipped) flipCard(visible);
                    } else if (['1','2','3','4'].includes(e.key)) {
                        const flipped = visible.querySelector('.flashcard-back').style.display === 'block';
                        if (flipped) grade(visible, parseInt(e.key, 10) - 1);
                    }
                });
            })();
        </script>
    @endpush
    @endonce
</x-app-layout>