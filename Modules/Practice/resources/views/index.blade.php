<x-app-layout>
    <div style="margin-bottom: 2.5rem">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem">Smart Practice Mode</h1>
        <p style="color: var(--text-muted)">Master each skill with tailored drills and instant AI feedback.</p>
    </div>

    @if(session('error'))
        <div class="glass" style="padding: 1rem; margin-bottom: 1.5rem; border-color: #ef4444; color: #ef4444">
            {{ session('error') }}
        </div>
    @endif

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem">
        <!-- Reading -->
        <div class="glass-card" style="text-align: center; border-bottom: 4px solid #3b82f6">
            <div style="font-size: 3rem; margin-bottom: 1rem">📖</div>
            <h3>Reading Drills</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem; min-height: 3rem">Focus on Gap-Fill, MCQ, and Matching questions from real IELTS passages.</p>
            <a href="{{ route('student.practice.drill', 'reading') }}" class="btn btn-primary" style="width: 100%">Start Training</a>
        </div>

        <!-- Listening -->
        <div class="glass-card" style="text-align: center; border-bottom: 4px solid #8b5cf6">
            <div style="font-size: 3rem; margin-bottom: 1rem">🎧</div>
            <h3>Listening Drills</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem; min-height: 3rem">Practice catching details and understanding accents.</p>
            <a href="{{ route('student.practice.drill', 'listening') }}" class="btn btn-primary" style="width: 100%; background-color: #8b5cf6">Start Training</a>
        </div>

        <!-- Writing Task 1 Support -->
        <div class="glass-card" style="text-align: center; border-bottom: 4px solid #10b981">
            <div style="font-size: 3rem; margin-bottom: 1rem">📊</div>
            <h3>Data Description</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem; min-height: 3rem">Level up your Writing Task 1 by practicing with diverse charts and maps.</p>
            <a href="{{ route('student.practice.drill', 'writing') }}" class="btn btn-primary" style="width: 100%; background-color: #10b981">Start Training</a>
        </div>

        <!-- Flashcards -->
        <div class="glass-card" style="text-align: center; border-bottom: 4px solid #f59e0b">
            <div style="font-size: 3rem; margin-bottom: 1rem">🗂️</div>
            <h3>AI Flashcards</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem; min-height: 3rem">Review topic-based vocabulary generated just for your level.</p>
            <a href="/student/flashcards" class="btn btn-primary" style="width: 100%; background-color: #f59e0b">Review Cards</a>
        </div>
    </div>
</x-app-layout>
