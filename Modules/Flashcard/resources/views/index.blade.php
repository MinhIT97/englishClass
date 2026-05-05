<x-app-layout>

    {{-- ===== HEADER ===== --}}
    <div class="fc-header">
        <div>
            <h1 style="font-size:2rem;margin-bottom:.25rem">{{ __('ui.flashcards_title') }}</h1>
            <p style="color:var(--text-muted)">{{ __('ui.flashcards_subtitle') }}</p>
        </div>
        <div class="fc-tabs">
            <button onclick="switchTab('study')" id="tab-study" class="fc-tab active">{{ __('ui.study_tab') }}</button>
            <button onclick="switchTab('notebook')" id="tab-notebook" class="fc-tab">{{ __('ui.my_words_tab') }} <span id="notebook-count" class="fc-badge">{{ count($myVocab) }}</span></button>
        </div>
    </div>

    {{-- ===== STUDY TAB ===== --}}
    <div id="section-study">

        {{-- Topic Filter --}}
        <div class="fc-filter-row">
            @foreach(['all' => '🌐 All', 'Environment' => __('ui.env_chip'), 'Technology' => __('ui.tech_chip'), 'Education' => __('ui.edu_chip'), 'Society' => __('ui.society_chip')] as $key => $label)
                <button onclick="filterByTopic('{{ $key }}')" class="fc-chip {{ $key === 'all' ? 'active' : '' }}" data-topic="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>

        {{-- Progress Bar --}}
        <div style="max-width:560px;margin:0 auto 1.5rem auto">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
                <span style="font-size:.8rem;color:var(--text-muted)">{{ __('ui.progress') }}</span>
                <span id="progress-label" style="font-size:.8rem;color:var(--text-muted)">0 / 0</span>
            </div>
            <div style="height:6px;background:var(--glass);border-radius:10px;overflow:hidden">
                <div id="progress-bar" style="height:100%;width:0%;background:linear-gradient(90deg,var(--primary),var(--accent));border-radius:10px;transition:width .4s ease"></div>
            </div>
            <div style="display:flex;gap:1.5rem;margin-top:.75rem;font-size:.8rem">
                <span style="color:#10b981">{{ __('ui.know_count') }} <span id="count-know">0</span></span>
                <span style="color:#ef4444">{{ __('ui.dont_know_count') }} <span id="count-dontknow">0</span></span>
                <span style="color:var(--text-muted)">{{ __('ui.skipped_count') }} <span id="count-skip">0</span></span>
            </div>
        </div>

        {{-- Card Area --}}
        <div style="max-width:560px;margin:0 auto;position:relative">

            {{-- Stack shadow --}}
            <div class="fc-stack-shadow fc-shadow2"></div>
            <div class="fc-stack-shadow fc-shadow1"></div>

            {{-- Bookmark --}}
            <button onclick="bookmarkWord()" id="bookmark-btn" class="fc-bookmark-btn" title="Save to notebook">🔖</button>

            {{-- Main Card --}}
            <div id="flashcard" class="fc-card" onclick="toggleFlip()">
                {{-- Front --}}
                <div class="fc-face fc-front">
                    <div id="card-topic-label" class="fc-topic-pill">Vocabulary</div>
                    <div id="card-word" class="fc-word">Loading…</div>
                    <div class="fc-flip-hint">{{ __('ui.tap_to_flip') }}</div>
                </div>
                {{-- Back --}}
                <div class="fc-face fc-back">
                    <div class="fc-back-label">{{ __('ui.definition') }}</div>
                    <p id="card-definition" class="fc-definition"></p>
                    <div class="fc-divider"></div>
                    <div class="fc-back-label" style="color:var(--accent)">{{ __('ui.example_label') }}</div>
                    <p id="card-example" class="fc-example"></p>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="fc-actions">
                <button onclick="markCard('dontknow')" class="fc-action-btn fc-wrong" title="Don't Know (←)">
                    ❌ <span>{{ __('ui.dont_know_btn') }}</span>
                </button>
                <button onclick="skipCard()" class="fc-action-btn fc-skip" title="Skip (↑)">
                    ⏭️
                </button>
                <button onclick="markCard('know')" class="fc-action-btn fc-correct" title="Know It (→)">
                    ✅ <span>{{ __('ui.know_it_btn') }}</span>
                </button>
            </div>

            {{-- Card indicator --}}
            <div style="text-align:center;margin-top:1.5rem;color:var(--text-muted);font-size:.8rem">
                <span id="card-indicator">1 / 1</span>
                &nbsp;·&nbsp;
                <span style="opacity:.6">{{ __('ui.arrow_keys_hint') }}</span>
            </div>
        </div>

        {{-- Session Complete Message (hidden) --}}
        <div id="session-complete" style="display:none;max-width:560px;margin:2rem auto;text-align:center" class="glass-card">
            <div style="font-size:3rem;margin-bottom:1rem">🎉</div>
            <h2 style="margin-bottom:.5rem">{{ __('ui.session_complete') }}</h2>
            <p style="color:var(--text-muted);margin-bottom:1.5rem">{{ __('ui.session_complete_desc') }}</p>
            <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
                <button onclick="restartSession()" class="btn btn-primary">{{ __('ui.restart') }}</button>
                <button onclick="reviewDontKnow()" class="btn btn-outline" id="review-missed-btn">{{ __('ui.review_missed') }}</button>
            </div>
        </div>

    </div>

    {{-- ===== NOTEBOOK TAB ===== --}}
    <div id="section-notebook" style="display:none">
        <div style="display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;align-items:center">
            <input type="text" id="notebook-search" class="form-control" placeholder="{{ __('ui.search_words') }}" style="max-width:320px" oninput="filterNotebook(this.value)">
            <span style="color:var(--text-muted);font-size:.875rem" id="notebook-count-label">{{ count($myVocab) }} {{ __('ui.words_saved') }}</span>
        </div>

        <div id="notebook-grid" class="fc-notebook-grid">
            @forelse($myVocab as $vocab)
                <div class="fc-notebook-card" data-word="{{ strtolower($vocab->word) }}">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.75rem">
                        <h3 style="color:var(--primary);font-size:1.1rem">{{ $vocab->word }}</h3>
                        <span class="fc-chip" style="font-size:.65rem;padding:.2rem .6rem">{{ $vocab->skill ?? 'General' }}</span>
                    </div>
                    <p style="font-size:.9rem;margin-bottom:.5rem;line-height:1.5">{{ $vocab->meaning }}</p>
                    @if($vocab->example)
                        <p style="font-size:.8rem;font-style:italic;color:var(--text-muted)">"{{ $vocab->example }}"</p>
                    @endif
                </div>
            @empty
                <div style="grid-column:1/-1;text-align:center;padding:5rem">
                    <div style="font-size:3rem;margin-bottom:1rem">📖</div>
                    <p style="color:var(--text-muted)">{{ __('ui.no_words_saved') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    <script>
    const allFlashcards = [
        { word:'Mitigation', definition:'The action of reducing the severity or painfulness of something.', example:'New policies for the mitigation of climate change were announced.', topic:'Environment' },
        { word:'Sustainable', definition:'Able to be maintained without depleting natural resources.', example:'We need sustainable energy sources to protect our future.', topic:'Environment' },
        { word:'Biodiversity', definition:'The variety of life in the world or a particular habitat.', example:'Deforestation is one of the greatest threats to biodiversity.', topic:'Environment' },
        { word:'Obsolete', definition:'No longer produced or used; out of date.', example:'Handwritten letters are becoming obsolete in the digital age.', topic:'Technology' },
        { word:'Innovation', definition:'A new method, idea, or product.', example:'Technological innovation is driving the global economy forward.', topic:'Technology' },
        { word:'Algorithm', definition:'A process or set of rules followed in calculations or problem-solving.', example:'Social media algorithms influence what content we see.', topic:'Technology' },
        { word:'Pedagogy', definition:'The method and practice of teaching.', example:'Modern pedagogy focuses on student interaction over rote learning.', topic:'Education' },
        { word:'Curriculum', definition:'The subjects comprising a course of study.', example:'The school redesigned its curriculum to include vocational training.', topic:'Education' },
        { word:'Literacy', definition:'The ability to read and write; competence in a subject.', example:'Digital literacy is now as important as reading and writing.', topic:'Education' },
        { word:'Demographic', definition:'Relating to the structure of populations.', example:'The demographic shift toward older populations presents social challenges.', topic:'Society' },
        { word:'Urbanization', definition:'The process by which towns and cities grow.', example:'Rapid urbanization leads to a shortage of affordable housing.', topic:'Society' },
        { word:'Inequality', definition:'Difference in size, degree, circumstances between groups.', example:'Income inequality has widened in many developed nations.', topic:'Society' },
    ];

    let filteredCards = [...allFlashcards];
    let currentIndex = 0;
    let knowSet = [], dontKnowSet = [], skipSet = [];
    let isReviewMode = false;
    let isFlipped = false;
    let isAnimating = false;

    function renderCard() {
        if (currentIndex >= filteredCards.length) {
            showSessionComplete();
            return;
        }
        const card = filteredCards[currentIndex];
        const el = document.getElementById('flashcard');

        // Reset flip
        isFlipped = false;
        el.classList.remove('flipped');

        setTimeout(() => {
            document.getElementById('card-word').textContent = card.word;
            document.getElementById('card-topic-label').textContent = card.topic + ' · Vocabulary';
            document.getElementById('card-definition').textContent = card.definition;
            const ex = card.example.replace(new RegExp(card.word, 'gi'), '<strong style="color:var(--primary)">$&</strong>');
            document.getElementById('card-example').innerHTML = '"' + ex + '"';
            
            // Fix indicator: don't show more than total
            const displayIndex = Math.min(currentIndex + 1, filteredCards.length);
            document.getElementById('card-indicator').textContent = `${displayIndex} / ${filteredCards.length}`;
            updateProgress();
        }, 150);
    }

    function toggleFlip() {
        isFlipped = !isFlipped;
        document.getElementById('flashcard').classList.toggle('flipped', isFlipped);
    }

    function markCard(verdict) {
        if (isAnimating || currentIndex >= filteredCards.length) return;
        isAnimating = true;
        
        const card = filteredCards[currentIndex];
        if (verdict === 'know') {
            knowSet.push(card);
            document.getElementById('count-know').textContent = knowSet.length;
            animateCard('right');
        } else {
            dontKnowSet.push(card);
            document.getElementById('count-dontknow').textContent = dontKnowSet.length;
            animateCard('left');
        }
        
        setTimeout(() => {
            currentIndex++;
            isAnimating = false;
            renderCard();
        }, 300);
    }

    function skipCard() {
        if (isAnimating || currentIndex >= filteredCards.length) return;
        isAnimating = true;

        skipSet.push(filteredCards[currentIndex]);
        document.getElementById('count-skip').textContent = skipSet.length;
        animateCard('up');
        setTimeout(() => { 
            currentIndex++; 
            isAnimating = false;
            renderCard(); 
        }, 300);
    }

    function animateCard(dir) {
        const el = document.getElementById('flashcard');
        const transforms = { right:'translateX(120%) rotate(15deg)', left:'translateX(-120%) rotate(-15deg)', up:'translateY(-80px) scale(.9)' };
        el.style.transition = 'transform .3s ease, opacity .3s ease';
        el.style.transform = transforms[dir];
        el.style.opacity = '0';
        setTimeout(() => {
            el.style.transition = 'none';
            el.style.transform = '';
            el.style.opacity = '1';
            setTimeout(() => {
                el.style.transition = '';
            }, 50);
        }, 320);
    }

    function updateProgress() {
        const done = knowSet.length + dontKnowSet.length + skipSet.length;
        const total = isReviewMode ? filteredCards.length : allFlashcards.filter(c => !filteredCards.length || filteredCards.includes(c)).length;
        const pct = filteredCards.length > 0 ? (currentIndex / filteredCards.length) * 100 : 0;
        document.getElementById('progress-bar').style.width = pct + '%';
        document.getElementById('progress-label').textContent = `${currentIndex} / ${filteredCards.length}`;
    }

    function showSessionComplete() {
        updateProgress();
        // Hide the whole study area (the container of flashcard and actions)
        const container = document.getElementById('flashcard').parentElement;
        if (container) container.style.display = 'none';
        
        document.getElementById('session-complete').style.display = 'block';
        document.getElementById('review-missed-btn').style.display = dontKnowSet.length > 0 ? '' : 'none';
    }

    function restartSession() {
        knowSet = []; dontKnowSet = []; skipSet = [];
        currentIndex = 0; isReviewMode = false;
        document.getElementById('count-know').textContent = 0;
        document.getElementById('count-dontknow').textContent = 0;
        document.getElementById('count-skip').textContent = 0;
        document.getElementById('session-complete').style.display = 'none';
        
        const container = document.getElementById('flashcard').parentElement;
        if (container) container.style.display = 'block';
        
        renderCard();
    }

    function reviewDontKnow() {
        filteredCards = [...dontKnowSet];
        knowSet = []; dontKnowSet = []; skipSet = []; currentIndex = 0;
        isReviewMode = true;
        document.getElementById('count-know').textContent = 0;
        document.getElementById('count-dontknow').textContent = 0;
        document.getElementById('count-skip').textContent = 0;
        document.getElementById('session-complete').style.display = 'none';
        
        const container = document.getElementById('flashcard').parentElement;
        if (container) container.style.display = 'block';
        
        renderCard();
    }

    function filterByTopic(topic) {
        document.querySelectorAll('.fc-chip[data-topic]').forEach(b => b.classList.toggle('active', b.dataset.topic === topic));
        filteredCards = topic === 'all' ? [...allFlashcards] : allFlashcards.filter(c => c.topic === topic);
        knowSet = []; dontKnowSet = []; skipSet = []; currentIndex = 0;
        document.getElementById('count-know').textContent = 0;
        document.getElementById('count-dontknow').textContent = 0;
        document.getElementById('count-skip').textContent = 0;
        document.getElementById('session-complete').style.display = 'none';
        document.getElementById('flashcard').closest('div').style.display = '';
        renderCard();
    }

    async function bookmarkWord() {
        const card = filteredCards[currentIndex];
        if (!card) return;
        const btn = document.getElementById('bookmark-btn');
        btn.textContent = '⏳'; btn.disabled = true;
        try {
            const r = await fetch('{{ route("student.flashcards.save") }}', {
                method: 'POST',
                headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}' },
                body: JSON.stringify({ word:card.word, meaning:card.definition, example:card.example, skill:card.topic })
            });
            const d = await r.json();
            btn.textContent = '✅';
            if (d.was_created) {
                const badge = document.getElementById('notebook-count');
                badge.textContent = parseInt(badge.textContent||0) + 1;
            }
        } catch(e) { btn.textContent = '⚠️'; }
        setTimeout(() => { btn.textContent = '🔖'; btn.disabled = false; }, 1200);
    }

    function switchTab(tab) {
        document.getElementById('section-study').style.display = tab === 'study' ? 'block' : 'none';
        document.getElementById('section-notebook').style.display = tab === 'notebook' ? 'block' : 'none';
        document.getElementById('tab-study').classList.toggle('active', tab === 'study');
        document.getElementById('tab-notebook').classList.toggle('active', tab === 'notebook');
    }

    function filterNotebook(query) {
        const q = query.toLowerCase();
        document.querySelectorAll('.fc-notebook-card').forEach(card => {
            card.style.display = card.dataset.word.includes(q) ? '' : 'none';
        });
    }

    window.addEventListener('keydown', e => {
        if (['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) return;
        if (e.key === 'ArrowRight') markCard('know');
        if (e.key === 'ArrowLeft') markCard('dontknow');
        if (e.key === 'ArrowUp') skipCard();
        if (e.key === ' ') { e.preventDefault(); toggleFlip(); }
    });

    renderCard();
    </script>

    <style>
    /* ===== LAYOUT ===== */
    .fc-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    /* ===== TABS ===== */
    .fc-tabs {
        display: flex;
        gap: .4rem;
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 50px;
        padding: .4rem;
    }
    .fc-tab {
        background: none;
        border: none;
        color: var(--text-muted);
        padding: .5rem 1.25rem;
        border-radius: 50px;
        cursor: pointer;
        font-size: .85rem;
        font-weight: 600;
        transition: all .2s;
        display: flex;
        align-items: center;
        gap: .4rem;
    }
    .fc-tab.active {
        background: var(--primary);
        color: white;
    }
    .fc-badge {
        background: rgba(255,255,255,.2);
        border-radius: 50px;
        padding: 1px 7px;
        font-size: .7rem;
    }

    /* ===== CHIPS / FILTER ===== */
    .fc-filter-row {
        display: flex;
        gap: .75rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    .fc-chip {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        color: var(--text-muted);
        padding: .45rem 1.1rem;
        border-radius: 50px;
        font-size: .8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all .2s;
        white-space: nowrap;
    }
    .fc-chip.active, .fc-chip:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    /* ===== CARD STACK ===== */
    .fc-stack-shadow {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        width: 88%;
        border-radius: 24px;
        background: var(--bg-secondary);
        border: 1px solid var(--glass-border);
    }
    .fc-shadow1 { bottom: -10px; height: calc(100% - 20px); z-index: 0; opacity: .6; pointer-events: none; }
    .fc-shadow2 { bottom: -20px; width: 80%; height: calc(100% - 40px); z-index: -1; opacity: .3; pointer-events: none; }

    /* ===== FLASHCARD ===== */
    .fc-card {
        height: 340px;
        position: relative;
        transition: transform .6s cubic-bezier(.4,0,.2,1);
        transform-style: preserve-3d;
        cursor: pointer;
        z-index: 1;
    }
    .fc-card.flipped { transform: rotateY(180deg); }

    .fc-face {
        position: absolute;
        width: 100%;
        height: 100%;
        backface-visibility: hidden;
        border-radius: 24px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2.5rem;
    }

    .fc-front {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #3730a3 100%);
        color: white;
        text-align: center;
        box-shadow: 0 20px 50px rgba(99,102,241,.35);
    }
    .fc-back {
        background: var(--bg-secondary);
        border: 2px solid var(--primary);
        transform: rotateY(180deg);
        text-align: center;
        box-shadow: 0 20px 50px rgba(0,0,0,.3);
    }

    .fc-topic-pill {
        font-size: .7rem;
        text-transform: uppercase;
        letter-spacing: 2px;
        opacity: .8;
        margin-bottom: 1.5rem;
        background: rgba(255,255,255,.15);
        padding: .35rem 1rem;
        border-radius: 50px;
    }
    .fc-word {
        font-size: 2.75rem;
        font-weight: 800;
        font-family: 'Outfit', sans-serif;
        line-height: 1.1;
    }
    .fc-flip-hint {
        margin-top: 2rem;
        font-size: .8rem;
        opacity: .7;
        background: rgba(255,255,255,.15);
        padding: .4rem 1rem;
        border-radius: 50px;
    }

    .fc-back-label {
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: 1.5px;
        color: var(--primary);
        margin-bottom: .5rem;
    }
    .fc-definition {
        font-size: 1.05rem;
        line-height: 1.6;
        margin-bottom: 1rem;
        color: var(--text-main);
    }
    .fc-divider {
        width: 40px;
        height: 2px;
        background: var(--glass-border);
        margin: .75rem auto;
        border-radius: 10px;
    }
    .fc-example {
        font-size: .875rem;
        font-style: italic;
        color: var(--text-muted);
        line-height: 1.5;
    }

    /* ===== BOOKMARK BUTTON ===== */
    .fc-bookmark-btn {
        position: absolute;
        top: -16px;
        right: 16px;
        z-index: 10;
        background: white;
        border: none;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 8px 20px rgba(0,0,0,.2);
        font-size: 1.1rem;
        transition: transform .2s;
    }
    .fc-bookmark-btn:hover { transform: scale(1.15); }

    /* ===== ACTIONS ===== */
    .fc-actions {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 1.25rem;
        margin-top: 3rem;
        position: relative;
        z-index: 10;
    }
    .fc-action-btn {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .75rem 1.75rem;
        border-radius: 50px;
        font-size: .9rem;
        font-weight: 700;
        border: 2px solid;
        cursor: pointer;
        transition: all .2s;
    }
    .fc-wrong {
        background: rgba(239,68,68,.1);
        border-color: rgba(239,68,68,.4);
        color: #ef4444;
    }
    .fc-wrong:hover {
        background: #ef4444;
        color: white;
        transform: translateX(-4px);
        box-shadow: 0 8px 20px rgba(239,68,68,.3);
    }
    .fc-correct {
        background: rgba(16,185,129,.1);
        border-color: rgba(16,185,129,.4);
        color: #10b981;
    }
    .fc-correct:hover {
        background: #10b981;
        color: white;
        transform: translateX(4px);
        box-shadow: 0 8px 20px rgba(16,185,129,.3);
    }
    .fc-skip {
        background: var(--glass);
        border-color: var(--glass-border);
        color: var(--text-muted);
        padding: .75rem 1rem;
        font-size: 1rem;
    }
    .fc-skip:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        transform: translateY(-2px);
    }

    /* ===== NOTEBOOK ===== */
    .fc-notebook-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.25rem;
    }
    .fc-notebook-card {
        background: var(--bg-secondary);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius);
        padding: 1.25rem;
        transition: border-color .2s, transform .2s;
    }
    .fc-notebook-card:hover {
        border-color: var(--primary);
        transform: translateY(-3px);
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 640px) {
        .fc-header { flex-direction: column; }
        .fc-tabs { width: 100%; justify-content: stretch; }
        .fc-tab { flex: 1; justify-content: center; }
        .fc-card { height: 300px; }
        .fc-word { font-size: 2.1rem; }
        .fc-action-btn span { display: none; }
        .fc-action-btn { padding: .75rem 1.25rem; }
    }
    </style>
</x-app-layout>
