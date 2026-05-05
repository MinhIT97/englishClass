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
        <div class="glass-card text-center group animate-card-float">
            <div class="text-5xl mb-4 group-hover:scale-110 transition-transform duration-300">📖</div>
            <h3 class="text-xl mb-2">{{ __('ui.reading_drills') }}</h3>
            <p class="text-muted text-sm mb-6 min-height-[3rem]">{{ __('ui.reading_desc') }}</p>
            <a href="{{ route('student.practice.drill', 'reading') }}" class="btn btn-primary w-full">{{ __('ui.start_training') }}</a>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-blue-500 opacity-50"></div>
        </div>

        <div class="glass-card text-center group animate-card-float" style="animation-delay: 0.5s">
            <div class="text-5xl mb-4 group-hover:scale-110 transition-transform duration-300">🎧</div>
            <h3 class="text-xl mb-2">{{ __('ui.listening_drills') }}</h3>
            <p class="text-muted text-sm mb-6 min-height-[3rem]">{{ __('ui.listening_desc') }}</p>
            <a href="{{ route('student.practice.drill', 'listening') }}" class="btn btn-primary w-full" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4)">{{ __('ui.start_training') }}</a>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-purple-500 opacity-50"></div>
        </div>

        <div class="glass-card text-center group animate-card-float" style="animation-delay: 1s">
            <div class="text-5xl mb-4 group-hover:scale-110 transition-transform duration-300">✍️</div>
            <h3 class="text-xl mb-2">{{ __('ui.writing') }}</h3>
            <p class="text-muted text-sm mb-6 min-height-[3rem]">{{ __('ui.submit_essays') }}</p>
            <a href="{{ route('student.writing.index') }}" class="btn btn-primary w-full" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4)">{{ __('ui.analyze_btn') }}</a>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-accent opacity-50"></div>
        </div>

        <div class="glass-card text-center group animate-card-float" style="animation-delay: 1.5s">
            <div class="text-5xl mb-4 group-hover:scale-110 transition-transform duration-300">🗣️</div>
            <h3 class="text-xl mb-2">{{ __('ui.speaking') }}</h3>
            <p class="text-muted text-sm mb-6 min-height-[3rem]">{{ __('ui.talk_to_ai') }}</p>
            <a href="{{ route('student.speaking.index') }}" class="btn btn-primary w-full" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4)">{{ __('ui.start_interview') }}</a>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-warning opacity-50"></div>
        </div>
    </div>
</x-app-layout>
