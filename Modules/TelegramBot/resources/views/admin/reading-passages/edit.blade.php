<x-app-layout>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem">
        <div>
            <h1 style="font-size: 1.75rem; margin-bottom: 0.25rem">✏️ Edit Passage #{{ $passage->id }}</h1>
            <p style="color: var(--text-muted)">Update passage content and questions. Question edits replace the entire list.</p>
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
        'passage' => $passage,
        'action' => route('admin.reading-passages.update', $passage),
        'method' => 'PUT',
    ])
</x-app-layout>
