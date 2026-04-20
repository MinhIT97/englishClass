<x-app-layout>
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2.5rem">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem">IELTS Flashcards</h1>
            <p style="color: var(--text-muted)">Smart interactive cards for core vocabulary mastery.</p>
        </div>
        <div style="display: flex; gap: 0.5rem; background: var(--glass); padding: 0.5rem; border-radius: 50px;">
            <button onclick="switchTab('cards')" id="tab-cards" class="btn btn-primary" style="padding: 0.5rem 1.5rem; border-radius: 50px; font-size: 0.8rem">Study Cards</button>
            <button onclick="switchTab('notebook')" id="tab-notebook" class="btn btn-outline" style="padding: 0.5rem 1.5rem; border-radius: 50px; font-size: 0.8rem; border: none">My Notebook ({{ count($myVocab) }})</button>
        </div>
    </div>

    <!-- Cards Tab -->
    <div id="section-cards">
        <!-- Topic Selection -->
        <div style="display: flex; gap: 1rem; margin-bottom: 3rem; overflow-x: auto; padding-bottom: 1rem">
            <button onclick="filterByTopic('all')" class="badge topic-btn badge-active" data-topic="all" style="padding: 0.75rem 1.5rem; cursor: pointer">All Topics</button>
            @foreach(['Environment', 'Technology', 'Education', 'Society'] as $topic)
                <button onclick="filterByTopic('{{ $topic }}')" class="badge topic-btn" data-topic="{{ $topic }}" style="background: var(--glass); color: var(--text-muted); padding: 0.75rem 1.5rem; cursor: pointer">{{ $topic }}</button>
            @endforeach
        </div>

        <!-- Flashcard Container -->
        <div style="max-width: 500px; margin: 0 auto; perspective: 1000px; position: relative;">
            <!-- Bookmark Button -->
            <button onclick="bookmarkWord()" id="bookmark-btn" style="position: absolute; top: -20px; right: 0; z-index: 10; background: white; border: none; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 10px 20px rgba(0,0,0,0.1); font-size: 1.25rem; transition: transform 0.2s;">
                🔖
            </button>

            <div id="flashcard" class="flashcard" onclick="toggleFlip()" style="height: 350px; position: relative; transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); transform-style: preserve-3d; cursor: pointer;">
                <!-- Front -->
                <div style="position: absolute; width: 100%; height: 100%; backface-visibility: hidden; background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%); border-radius: 24px; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; color: white; text-align: center; box-shadow: 0 20px 40px rgba(99, 102, 241, 0.3);">
                    <div id="card-category" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 2px; opacity: 0.8; margin-bottom: 2rem">Vocabulary Card</div>
                    <h2 id="card-word" style="font-size: 2.5rem; font-weight: 800; margin: 0">Loading...</h2>
                    <div style="margin-top: 2rem; font-size: 0.875rem; background: rgba(255, 255, 255, 0.2); padding: 0.5rem 1rem; border-radius: 50px;">Click to Flip 🔄</div>
                </div>

                <!-- Back -->
                <div style="position: absolute; width: 100%; height: 100%; backface-visibility: hidden; background: var(--bg-secondary); border: 2px solid var(--primary); border-radius: 24px; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2.5rem; color: var(--text-main); transform: rotateY(180deg); box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
                    <div style="text-align: center">
                        <div style="font-size: 0.75rem; color: var(--primary); font-weight: 700; margin-bottom: 1rem">DEFINITION</div>
                        <p id="card-definition" style="font-size: 1.125rem; line-height: 1.6; margin-bottom: 2rem"></p>
                        
                        <div style="font-size: 0.75rem; color: var(--accent); font-weight: 700; margin-bottom: 0.5rem">EXAMPLE</div>
                        <p id="card-example" style="font-style: italic; color: var(--text-muted); font-size: 0.95rem"></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div style="display: flex; justify-content: center; align-items: center; gap: 2rem; margin-top: 4rem">
            <button onclick="prevCard()" class="btn btn-outline" style="border-radius: 50px; padding: 0.75rem 2rem">Previous</button>
            <span id="card-indicator" style="color: var(--text-muted); font-size: 0.875rem">1 / 1</span>
            <button onclick="nextCard()" class="btn btn-primary" style="border-radius: 50px; padding: 0.75rem 2rem">Next Card ➜</button>
        </div>
    </div>

    <!-- Notebook Tab -->
    <div id="section-notebook" style="display: none">
        <div class="grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem">
            @forelse($myVocab as $vocab)
                <div class="card" style="padding: 1.5rem; position: relative">
                    <span class="badge" style="position: absolute; top: 1rem; right: 1rem; font-size: 0.7rem">{{ $vocab->skill ?? 'General' }}</span>
                    <h3 style="margin-bottom: 0.5rem; color: var(--primary)">{{ $vocab->word }}</h3>
                    <p style="font-size: 0.9rem; margin-bottom: 1rem">{{ $vocab->meaning }}</p>
                    <p style="font-size: 0.8rem; font-style: italic; color: var(--text-muted)">"{{ $vocab->example }}"</p>
                </div>
            @empty
                <div style="grid-column: 1/-1; text-align: center; padding: 5rem">
                    <div style="font-size: 3rem; margin-bottom: 1rem">📖</div>
                    <p style="color: var(--text-muted)">You haven't saved any words yet. Start studying and bookmark words you want to remember!</p>
                </div>
            @endforelse
        </div>
    </div>

    <script>
        const allFlashcards = [
            { word: 'Mitigation', definition: 'The action of reducing the severity, seriousness, or painfulness of something.', example: 'The government is implementing new policies for the mitigation of climate change.', topic: 'Environment' },
            { word: 'Sustainable', definition: 'Able to be maintained at a certain rate or level; conserving an ecological balance.', example: 'We need to find sustainable energy sources to protect our future.', topic: 'Environment' },
            { word: 'Obsolete', definition: 'No longer produced or used; out of date.', example: 'Handwritten letters are becoming obsolete in the digital age.', topic: 'Technology' },
            { word: 'Innovation', definition: 'A new method, idea, product, etc.', example: 'Technological innovation is driving the global economy.', topic: 'Technology' },
            { word: 'Pedagogy', definition: 'The method and practice of teaching, especially as an academic subject.', example: 'Modern pedagogy focuses more on student interaction than traditional lectures.', topic: 'Education' },
            { word: 'Curriculum', definition: 'The subjects comprising a course of study in a school or college.', example: 'The school is redesigning its curriculum to include more vocational training.', topic: 'Education' },
            { word: 'Demographic', definition: 'Relating to the structure of populations.', example: 'The demographic shift towards an older population presents various social challenges.', topic: 'Society' },
            { word: 'Urbanization', definition: 'The process of making an area more urban.', example: 'Rapid urbanization can lead to a shortage of affordable housing in city centers.', topic: 'Society' }
        ];

        let filteredCards = [...allFlashcards];
        let currentIndex = 0;

        function toggleFlip() {
            document.getElementById('flashcard').classList.toggle('flipped');
        }

        function switchTab(tab) {
            document.getElementById('section-cards').style.display = tab === 'cards' ? 'block' : 'none';
            document.getElementById('section-notebook').style.display = tab === 'notebook' ? 'block' : 'none';
            
            const btnCards = document.getElementById('tab-cards');
            const btnNotebook = document.getElementById('tab-notebook');
            
            if(tab === 'cards') {
                btnCards.className = 'btn btn-primary';
                btnNotebook.className = 'btn btn-outline';
            } else {
                btnCards.className = 'btn btn-outline';
                btnNotebook.className = 'btn btn-primary';
            }
        }

        async function bookmarkWord() {
            const card = filteredCards[currentIndex];
            const btn = document.getElementById('bookmark-btn');
            
            btn.style.transform = 'scale(1.2)';
            
            try {
                const response = await fetch('{{ route("student.flashcards.save") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        word: card.word,
                        meaning: card.definition,
                        example: card.example,
                        skill: card.topic
                    })
                });

                const data = await response.json();

                if(response.ok) {
                    btn.textContent = '✅';
                    
                    // Only increment counter if it was a new record
                    if(data.was_created) {
                        const notebookTab = document.getElementById('tab-notebook');
                        if(notebookTab) {
                            const countMatch = notebookTab.textContent.match(/\((\d+)\)/);
                            if(countMatch) {
                                const newCount = parseInt(countMatch[1]) + 1;
                                notebookTab.textContent = `My Notebook (${newCount})`;
                            }
                        }
                    }

                    setTimeout(() => { 
                        btn.textContent = '🔖';
                        btn.style.transform = 'scale(1)';
                    }, 1000);
                }
            } catch (e) {
                console.error(e);
            }
        }

        function filterByTopic(topic) {
            document.querySelectorAll('.topic-btn').forEach(btn => {
                if(btn.dataset.topic === topic) {
                    btn.classList.add('badge-active');
                    btn.style.background = '';
                    btn.style.color = '';
                } else {
                    btn.classList.remove('badge-active');
                    btn.style.background = 'var(--glass)';
                    btn.style.color = 'var(--text-muted)';
                }
            });

            if(topic === 'all') {
                filteredCards = [...allFlashcards];
            } else {
                filteredCards = allFlashcards.filter(c => c.topic === topic);
            }
            
            currentIndex = 0;
            renderCard();
        }

        function nextCard() {
            currentIndex = (currentIndex + 1) % filteredCards.length;
            renderCard();
        }

        function prevCard() {
            currentIndex = (currentIndex - 1 + filteredCards.length) % filteredCards.length;
            renderCard();
        }

        function renderCard() {
            const card = filteredCards[currentIndex];
            const flashcardEl = document.getElementById('flashcard');
            
            flashcardEl.classList.remove('flipped');

            setTimeout(() => {
                document.getElementById('card-word').textContent = card.word;
                document.getElementById('card-category').textContent = card.topic + ' Card';
                document.getElementById('card-definition').textContent = card.definition;
                document.getElementById('card-example').innerHTML = `"${card.example.replace(new RegExp(card.word, 'gi'), '<strong>$&</strong>')}"`;
                document.getElementById('card-indicator').textContent = `${currentIndex + 1} / ${filteredCards.length}`;
            }, 100);
        }

        window.addEventListener('keydown', (e) => {
            if(e.key === 'ArrowRight') nextCard();
            if(e.key === 'ArrowLeft') prevCard();
            if(e.key === ' ') toggleFlip();
        });

        renderCard();
    </script>

    <style>
        #bookmark-btn:hover {
            transform: scale(1.1) !important;
        }
        .flashcard.flipped {
            transform: rotateY(180deg);
        }
        .badge-active {
            background-color: var(--primary) !important;
            color: white !important;
            border: none;
        }
        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
        }
    </style>
</x-app-layout>
