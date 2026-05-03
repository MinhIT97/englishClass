<x-app-layout>
    <div style="margin-bottom: 2.5rem">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem">IELTS Sets</h1>
        <p style="color: var(--text-muted)">
            Work through structured sets instead of isolated drills. Each set is organized to save time and keep revision focused.
        </p>
    </div>

    <div class="sets-grid">
        @forelse($sets as $set)
            <a href="{{ route('student.sets.show', $set) }}" class="glass-card set-card" style="text-decoration: none; color: inherit;">
                <div class="set-card-header">
                    <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: var(--primary); border: 1px solid rgba(99, 102, 241, 0.18)">
                        {{ strtoupper($set->set_type) }}
                    </span>
                    <span style="font-size: 0.75rem; color: var(--text-muted)">Band {{ $set->target_band }}</span>
                </div>

                <h3 style="margin-bottom: 0.65rem">{{ $set->title }}</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem">{{ $set->description }}</p>

                <div class="set-meta-grid">
                    <div class="set-meta-item">
                        <strong>{{ $set->topic }}</strong>
                        <span>Topic</span>
                    </div>
                    <div class="set-meta-item">
                        <strong>{{ $set->duration_minutes }} min</strong>
                        <span>Duration</span>
                    </div>
                    <div class="set-meta-item">
                        <strong>{{ $set->total_questions }}</strong>
                        <span>Questions</span>
                    </div>
                    <div class="set-meta-item">
                        <strong>{{ $set->sections_count }}</strong>
                        <span>Sections</span>
                    </div>
                </div>

                <div class="set-skills-row">
                    @foreach($set->sections as $section)
                        <span class="set-skill-pill">{{ ucfirst($section->skill) }}</span>
                    @endforeach
                </div>

                @if($set->latest_attempt)
                    <div class="set-attempt-banner">
                        Latest status: <strong>{{ str_replace('_', ' ', $set->latest_attempt->status) }}</strong>
                    </div>
                @endif
            </a>
        @empty
            <div class="glass-card" style="padding: 2rem; text-align: center; color: var(--text-muted)">
                No published sets yet.
            </div>
        @endforelse
    </div>

    <style>
        .sets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .set-card {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            min-height: 280px;
        }

        .set-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .set-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .set-meta-item {
            padding: 0.85rem 1rem;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
        }

        .set-meta-item strong {
            display: block;
            font-size: 1rem;
        }

        .set-meta-item span {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .set-skills-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .set-skill-pill {
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
            background: rgba(34, 211, 238, 0.08);
            border: 1px solid rgba(34, 211, 238, 0.16);
            color: var(--accent);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .set-attempt-banner {
            margin-top: auto;
            padding: 0.8rem 1rem;
            border-radius: 14px;
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.16);
            color: #10b981;
            font-size: 0.85rem;
        }
    </style>
</x-app-layout>
