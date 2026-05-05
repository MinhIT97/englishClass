<x-app-layout>
    <div style="margin-bottom: 2.5rem">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem">{{ __('ui.ai_writing_grader') }}</h1>
        <p style="color: var(--text-muted)">{{ __('ui.writing_grader_desc') }}</p>
    </div>

    @if(session('error'))
        <div class="glass" style="padding: 1rem; margin-bottom: 1.5rem; border-color: #ef4444; color: #ef4444">
            {{ session('error') }}
        </div>
    @endif

    <div class="writing-grid">
        <!-- Essay Form -->
        <div class="glass-card">
            <form method="POST" action="{{ route('student.writing.submit') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">{{ __('ui.task_type') }}</label>
                    <select name="task_type" class="form-control">
                        <option value="task_2">{{ __('ui.task_2') }}</option>
                        <option value="task_1">{{ __('ui.task_1') }}</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">{{ __('ui.essay_content') }}</label>
                    <textarea name="essay_content" class="form-control" style="min-height: 400px; resize: vertical; line-height: 1.8; font-size: 1rem" placeholder="{{ __('ui.essay_placeholder') }}">{{ old('essay_content') }}</textarea>
                    <small style="color: var(--text-muted); display: block; margin-top: 0.5rem">{{ __('ui.min_words') }}</small>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; border-radius: 50px; padding: 1rem">
                    {{ __('ui.analyze_btn') }}
                </button>
            </form>
        </div>

        <!-- History Sidebar -->
        <div>
            <h3 style="margin-bottom: 1.5rem">{{ __('ui.recent_attempts') }}</h3>
            <div style="display: flex; flex-direction: column; gap: 1rem">
                @forelse($attempts as $attempt)
                    <a href="{{ route('student.writing.show', $attempt->id) }}" class="glass" style="display: block; padding: 1.25rem; text-decoration: none; color: inherit; transition: all 0.2s ease">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem">
                            <span class="badge" style="background: var(--glass); color: var(--text-muted)">{{ strtoupper($attempt->task_type) }}</span>
                            <span style="font-weight: 700; color: var(--accent); font-size: 1.125rem">Band {{ $attempt->band_score }}</span>
                        </div>
                        <p style="font-size: 0.75rem; color: var(--text-muted)">{{ $attempt->created_at->diffForHumans() }}</p>
                    </a>
                @empty
                    <p style="color: var(--text-muted); text-align: center; padding: 2rem">{{ __('ui.no_attempts') }}</p>
                @endforelse
                
                {{ $attempts->links() }}
            </div>
        </div>
        </div>
    </div>

    <style>
        .writing-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        @media (max-width: 768px) {
            .writing-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</x-app-layout>
