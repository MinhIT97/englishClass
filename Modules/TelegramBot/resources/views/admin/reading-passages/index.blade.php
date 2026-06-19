<x-app-layout>
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2.5rem; flex-wrap: wrap; gap: 1rem">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem">📖 Reading Passages</h1>
            <p style="color: var(--text-muted)">Manage passages for the reading-comprehension review feature.</p>
        </div>
        <a href="{{ route('admin.reading-passages.create') }}" class="btn btn-primary" style="padding: 0.75rem 1.5rem; border-radius: 50px">
            + Create New Passage
        </a>
    </div>

    @if(session('success'))
        <div class="glass-card" style="padding: 1rem 1.25rem; margin-bottom: 1.5rem; border-left: 4px solid var(--accent)">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="glass-card" style="padding: 1rem 1.25rem; margin-bottom: 1.5rem; border-left: 4px solid var(--danger); color: var(--danger)">
            ❌ {{ session('error') }}
        </div>
    @endif

    {{-- Filters --}}
    <div class="glass-card" style="padding: 1.25rem; margin-bottom: 1.5rem">
        <form action="{{ route('admin.reading-passages.index') }}" method="GET" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 0.75rem; align-items: end">
            <div>
                <label style="display: block; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Title or body…" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem">
            </div>
            <div>
                <label style="display: block; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem">Topic</label>
                <select name="topic_id" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem">
                    <option value="">All topics</option>
                    @foreach($topics as $topic)
                        <option value="{{ $topic->id }}" @selected(($filters['topic_id'] ?? null) == $topic->id)>
                            {{ $topic->name_en }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem">Difficulty</label>
                <select name="difficulty" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem">
                    <option value="">All</option>
                    <option value="easy" @selected(($filters['difficulty'] ?? null) === 'easy')>Easy</option>
                    <option value="medium" @selected(($filters['difficulty'] ?? null) === 'medium')>Medium</option>
                    <option value="hard" @selected(($filters['difficulty'] ?? null) === 'hard')>Hard</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem">Status</label>
                <select name="is_active" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem">
                    <option value="">All</option>
                    <option value="1" @selected(($filters['is_active'] ?? null) === '1' || ($filters['is_active'] ?? null) === 1)>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? null) === '0' || ($filters['is_active'] ?? null) === 0)>Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn btn-outline" style="padding: 0.5rem 1rem">🔍 Filter</button>
        </form>
    </div>

    {{-- List --}}
    <div class="glass-card" style="padding: 0; overflow: hidden">
        <table style="width: 100%; border-collapse: collapse">
            <thead style="background: var(--bg-secondary); text-align: left">
                <tr>
                    <th style="padding: 0.75rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted)">ID</th>
                    <th style="padding: 0.75rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted)">Title</th>
                    <th style="padding: 0.75rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted)">Topic</th>
                    <th style="padding: 0.75rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted)">Difficulty</th>
                    <th style="padding: 0.75rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted)">Words</th>
                    <th style="padding: 0.75rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted)">Status</th>
                    <th style="padding: 0.75rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); text-align: right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($passages as $passage)
                    <tr style="border-top: 1px solid var(--glass-border)">
                        <td style="padding: 0.75rem 1rem; font-family: monospace; color: var(--text-muted)">#{{ $passage->id }}</td>
                        <td style="padding: 0.75rem 1rem">
                            <div style="font-weight: 500">{{ $passage->title }}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted)">{{ $passage->slug }}</div>
                        </td>
                        <td style="padding: 0.75rem 1rem; font-size: 0.875rem">{{ $passage->topic?->name_en ?? '—' }}</td>
                        <td style="padding: 0.75rem 1rem">
                            <span style="font-size: 0.75rem; padding: 0.2rem 0.5rem; background: var(--bg-secondary); border-radius: 6px">
                                {{ strtoupper($passage->difficulty) }}
                            </span>
                        </td>
                        <td style="padding: 0.75rem 1rem; color: var(--text-muted); font-size: 0.875rem">{{ $passage->word_count ?? '—' }}</td>
                        <td style="padding: 0.75rem 1rem">
                            @if($passage->is_active)
                                <span style="font-size: 0.75rem; color: var(--accent); font-weight: 600">● Active</span>
                            @else
                                <span style="font-size: 0.75rem; color: var(--text-muted)">○ Inactive</span>
                            @endif
                        </td>
                        <td style="padding: 0.75rem 1rem; text-align: right">
                            <div style="display: inline-flex; gap: 0.4rem">
                                <a href="{{ route('admin.reading-passages.edit', $passage) }}" class="btn btn-outline" style="padding: 0.3rem 0.7rem; font-size: 0.8rem">Edit</a>
                                <form action="{{ route('admin.reading-passages.toggle', $passage) }}" method="POST" style="display: inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline" style="padding: 0.3rem 0.7rem; font-size: 0.8rem">
                                        {{ $passage->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                                <form action="{{ route('admin.reading-passages.destroy', $passage) }}" method="POST" style="display: inline" onsubmit="return confirm('Delete passage #{{ $passage->id }}? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn" style="padding: 0.3rem 0.7rem; font-size: 0.8rem; background: var(--danger); color: white">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding: 2rem; text-align: center; color: var(--text-muted)">
                            No passages found. <a href="{{ route('admin.reading-passages.create') }}" style="color: var(--primary)">Create the first one →</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.5rem">
        {{ $passages->links() }}
    </div>
</x-app-layout>
