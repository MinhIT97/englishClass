<x-app-layout>
    @push('head')
        <title>{{ __('ui.reading_review_title') }} - {{ config('app.name') }}</title>
    @endpush

    <div style="max-width: 1100px; margin: 0 auto">
        <header style="margin-bottom: 1.5rem">
            <h1 style="font-size: 1.875rem; margin-bottom: 0.25rem">📚 {{ __('ui.reading_review_title') }}</h1>
            <p style="color: var(--text-muted)">{{ __('ui.reading_review_subtitle') }}</p>
        </header>

        {{-- Stats summary --}}
        <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem;margin-bottom:1.5rem">
            <div class="glass-card" style="padding:1rem;text-align:center">
                <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em">📅 {{ __('ui.reading_review_due_today') }}</div>
                <div style="font-size:1.875rem;font-weight:700;color:var(--primary);margin-top:0.25rem">{{ $stats['due_today'] }}</div>
            </div>
            <div class="glass-card" style="padding:1rem;text-align:center">
                <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em">📚 {{ __('ui.reading_review_total') }}</div>
                <div style="font-size:1.875rem;font-weight:700;margin-top:0.25rem">{{ $stats['total_passages'] }}</div>
            </div>
            <div class="glass-card" style="padding:1rem;text-align:center">
                <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em">🎯 {{ __('ui.reading_review_mature') }}</div>
                <div style="font-size:1.875rem;font-weight:700;color:var(--accent);margin-top:0.25rem">{{ $stats['mature_passages'] }}</div>
            </div>
            <div class="glass-card" style="padding:1rem;text-align:center">
                <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em">🔥 {{ __('ui.streak') }}</div>
                <div style="font-size:1.875rem;font-weight:700;margin-top:0.25rem">{{ $stats['streak'] }}</div>
            </div>
        </div>

        {{-- Action bar --}}
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.5rem">
            <form method="GET" action="{{ route('reading-review.index') }}" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                <select name="difficulty" class="form-control" style="font-size:0.875rem;padding:0.4rem 0.75rem">
                    <option value="">{{ __('ui.reading_review_filter_all_difficulty') }}</option>
                    <option value="easy" @selected($filters['difficulty'] === 'easy')>Easy</option>
                    <option value="medium" @selected($filters['difficulty'] === 'medium')>Medium</option>
                    <option value="hard" @selected($filters['difficulty'] === 'hard')>Hard</option>
                </select>
                <button type="submit" class="btn btn-outline" style="font-size:0.875rem;padding:0.4rem 0.9rem">🔍 {{ __('ui.filter') }}</button>
            </form>
            <a href="{{ route('reading-review.session') }}" class="btn btn-primary" style="font-size:0.9rem">
                ⚡ {{ __('ui.reading_review_start_session') }}
            </a>
        </div>

        @if($passages->isEmpty())
            <x-ui.empty-state
                icon="📭"
                :title="__('ui.reading_review_empty_title')"
                :description="__('ui.reading_review_empty_desc')"
            />
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1rem">
                @foreach($passages as $passage)
                    @php
                        $status = match (true) {
                            $passage->is_mature => 'mature',
                            $passage->is_due => 'due',
                            $passage->user_review => 'scheduled',
                            default => 'new',
                        };
                        $statusLabel = match ($status) {
                            'mature' => '✅ ' . __('ui.reading_review_status_mature'),
                            'due' => '📅 ' . __('ui.reading_review_status_due'),
                            'scheduled' => '⏰ ' . __('ui.reading_review_status_scheduled'),
                            default => '🆕 ' . __('ui.reading_review_status_new'),
                        };
                        $statusColor = match ($status) {
                            'mature' => 'var(--accent)',
                            'due' => 'var(--primary)',
                            'scheduled' => 'var(--text-muted)',
                            default => 'var(--info)',
                        };
                    @endphp
                    <article class="glass-card" style="padding:1.25rem;display:flex;flex-direction:column;gap:0.75rem">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem">
                            <h3 style="font-size:1.1rem;font-weight:600;margin:0;line-height:1.3">{{ $passage->title }}</h3>
                            <span style="font-size:0.7rem;font-weight:600;padding:0.2rem 0.6rem;border-radius:999px;background:{{ $statusColor }}22;color:{{ $statusColor }};white-space:nowrap">
                                {{ $statusLabel }}
                            </span>
                        </div>

                        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;font-size:0.75rem;color:var(--text-muted)">
                            <span style="padding:0.2rem 0.5rem;background:var(--bg-secondary);border-radius:6px">
                                📊 {{ strtoupper($passage->difficulty) }}
                            </span>
                            @if($passage->topic)
                                <span style="padding:0.2rem 0.5rem;background:var(--bg-secondary);border-radius:6px">
                                    📁 {{ $passage->topic->name_en }}
                                </span>
                            @endif
                            <span style="padding:0.2rem 0.5rem;background:var(--bg-secondary);border-radius:6px">
                                📝 {{ $passage->passageQuestions->count() }} {{ __('ui.reading_review_questions') }}
                            </span>
                            @if($passage->word_count)
                                <span style="padding:0.2rem 0.5rem;background:var(--bg-secondary);border-radius:6px">
                                    🔤 {{ $passage->word_count }} {{ __('ui.reading_review_words') }}
                                </span>
                            @endif
                        </div>

                        <p style="font-size:0.875rem;color:var(--text-muted);margin:0;line-height:1.5;max-height:4.5em;overflow:hidden">
                            {{ \Illuminate\Support\Str::limit(strip_tags($passage->body), 180) }}
                        </p>

                        <div style="display:flex;gap:0.5rem;margin-top:auto">
                            <a href="{{ route('reading-review.session', ['passage' => $passage->id]) }}" class="btn btn-primary" style="flex:1;font-size:0.85rem;padding:0.5rem;text-align:center">
                                {{ $passage->is_due || !$passage->user_review ? '▶️ ' . __('ui.reading_review_action_start') : '🔁 ' . __('ui.reading_review_action_review') }}
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
