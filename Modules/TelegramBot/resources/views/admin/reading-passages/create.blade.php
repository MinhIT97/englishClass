<x-app-layout>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem">
        <div>
            <h1 style="font-size: 1.75rem; margin-bottom: 0.25rem">📖 Create Reading Passage</h1>
            <p style="color: var(--text-muted)">Add a new passage to the reading-comprehension library.</p>
        </div>
        <a href="{{ route('admin.reading-passages.index') }}" class="btn btn-outline" style="border-radius: 50px; padding: 0.6rem 1.25rem">← Back to list</a>
    </div>

    @if($errors->any())
        <div class="glass-card" style="padding: 1rem 1.25rem; margin-bottom: 1.5rem; border-left: 4px solid var(--danger); color: var(--danger)">
            <strong>❌ Please fix the following:</strong>
            <ul style="margin-top: 0.5rem; padding-left: 1.5rem; font-size: 0.875rem">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('telegrambot::admin.reading-passages._form', [
        'passage' => null,
        'action' => route('admin.reading-passages.store'),
        'method' => 'POST',
    ])
</x-app-layout>
