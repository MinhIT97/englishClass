<x-app-layout>
    <div style="display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap;">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.35rem;">IELTS Sets Admin</h1>
            <p style="color: var(--text-muted); max-width: 760px;">
                Manage full IELTS mock sets, publishing status, section structure, and question assignment from the question bank.
            </p>
        </div>
        <a href="{{ route('admin.sets.create') }}" class="btn btn-primary" style="padding: 0.9rem 1.5rem;">
            Create New Set
        </a>
    </div>

    <div class="glass-card" style="padding: 0; overflow: hidden;">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: rgba(255, 255, 255, 0.03);">
                        <th style="padding: 1rem; text-align: left;">Set</th>
                        <th style="padding: 1rem; text-align: left;">Type</th>
                        <th style="padding: 1rem; text-align: left;">Band</th>
                        <th style="padding: 1rem; text-align: left;">Sections</th>
                        <th style="padding: 1rem; text-align: left;">Attempts</th>
                        <th style="padding: 1rem; text-align: left;">Status</th>
                        <th style="padding: 1rem; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sets as $set)
                        <tr style="border-top: 1px solid var(--glass-border);">
                            <td style="padding: 1rem; vertical-align: top;">
                                <div style="font-weight: 700;">{{ $set->title }}</div>
                                <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem;">{{ $set->topic }}</div>
                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.35rem;">/{{ $set->slug }}</div>
                            </td>
                            <td style="padding: 1rem;">{{ ucfirst($set->set_type) }}</td>
                            <td style="padding: 1rem;">{{ $set->target_band }}</td>
                            <td style="padding: 1rem;">
                                <div>{{ $set->sections_count }} sections</div>
                                <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem;">{{ $set->total_questions }} prompts</div>
                            </td>
                            <td style="padding: 1rem;">{{ $set->attempts_count }}</td>
                            <td style="padding: 1rem;">
                                <span class="badge" style="background: {{ $set->is_published ? 'rgba(16, 185, 129, 0.12)' : 'rgba(148, 163, 184, 0.12)' }}; color: {{ $set->is_published ? '#10b981' : '#cbd5e1' }}; border: 1px solid {{ $set->is_published ? 'rgba(16, 185, 129, 0.18)' : 'rgba(148, 163, 184, 0.18)' }};">
                                    {{ $set->is_published ? 'Published' : 'Draft' }}
                                </span>
                            </td>
                            <td style="padding: 1rem;">
                                <div style="display: flex; gap: 0.5rem; justify-content: flex-end; flex-wrap: wrap;">
                                    <a href="{{ route('admin.sets.edit', $set) }}" class="btn btn-outline" style="padding: 0.7rem 1rem;">Edit</a>
                                    @if($set->is_published)
                                        <a href="{{ route('student.sets.show', $set) }}" class="btn btn-outline" style="padding: 0.7rem 1rem;">Preview</a>
                                    @endif
                                    <form method="POST" action="{{ route('admin.sets.destroy', $set) }}" onsubmit="return confirm('Delete this IELTS set and all related attempts?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline" style="padding: 0.7rem 1rem; border-color: rgba(239, 68, 68, 0.3); color: #fca5a5;">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                                No IELTS sets found yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $sets->links() }}
    </div>
</x-app-layout>
