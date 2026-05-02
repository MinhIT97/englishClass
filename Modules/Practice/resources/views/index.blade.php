<x-app-layout>
    <div class="mb-10 animate-fade-in">
        <h1 class="text-4xl font-bold mb-2">{{ __('ui.smart_practice') }}</h1>
        <p class="text-muted text-lg">{{ __('ui.practice_desc') }}</p>
    </div>

    @if(session('error'))
        <div class="glass p-4 mb-6 border-danger text-danger animate-fade-in">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 animate-fade-in" style="animation-delay: 0.1s">
        <!-- Reading -->
        <div class="glass-card text-center group animate-card-float">
            <div class="text-5xl mb-4 group-hover:scale-110 transition-transform duration-300">📖</div>
            <h3 class="text-xl mb-2">{{ __('ui.reading_drills') }}</h3>
            <p class="text-muted text-sm mb-6 min-height-[3rem]">{{ __('ui.reading_desc') }}</p>
            <a href="{{ route('student.practice.drill', 'reading') }}" class="btn btn-primary w-full">{{ __('ui.start_training') }}</a>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-blue-500 opacity-50"></div>
        </div>

        <!-- Listening -->
        <div class="glass-card text-center group animate-card-float" style="animation-delay: 0.5s">
            <div class="text-5xl mb-4 group-hover:scale-110 transition-transform duration-300">🎧</div>
            <h3 class="text-xl mb-2">{{ __('ui.listening_drills') }}</h3>
            <p class="text-muted text-sm mb-6 min-height-[3rem]">{{ __('ui.listening_desc') }}</p>
            <a href="{{ route('student.practice.drill', 'listening') }}" class="btn btn-primary w-full" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4)">{{ __('ui.start_training') }}</a>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-purple-500 opacity-50"></div>
        </div>

        <!-- Writing Task 1 Support -->
        <div class="glass-card text-center group animate-card-float" style="animation-delay: 1s">
            <div class="text-5xl mb-4 group-hover:scale-110 transition-transform duration-300">📊</div>
            <h3 class="text-xl mb-2">{{ __('ui.data_desc') }}</h3>
            <p class="text-muted text-sm mb-6 min-height-[3rem]">{{ __('ui.writing_task1_desc') }}</p>
            <a href="{{ route('student.practice.drill', 'writing') }}" class="btn btn-primary w-full" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4)">{{ __('ui.start_training') }}</a>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-accent opacity-50"></div>
        </div>

        <!-- Flashcards -->
        <div class="glass-card text-center group animate-card-float" style="animation-delay: 1.5s">
            <div class="text-5xl mb-4 group-hover:scale-110 transition-transform duration-300">🗂️</div>
            <h3 class="text-xl mb-2">{{ __('ui.ai_flashcards') }}</h3>
            <p class="text-muted text-sm mb-6 min-height-[3rem]">{{ __('ui.flashcards_desc') }}</p>
            <a href="/student/flashcards" class="btn btn-primary w-full" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4)">{{ __('ui.review_cards') }}</a>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-warning opacity-50"></div>
        </div>
    </div>
</x-app-layout>
