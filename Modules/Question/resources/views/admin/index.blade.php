<x-app-layout>
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2.5rem">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem">Question Bank</h1>
            <p style="color: var(--text-muted)">Manage and organize all IELTS practice questions.</p>
        </div>
        <div style="display: flex; gap: 1rem">
            <a href="{{ route('question.create') }}" class="btn btn-outline" style="padding: 0.75rem 1.5rem; border-radius: 50px">
                + Create Manually
            </a>
            <a href="{{ route('admin.questions.ai') }}" class="btn btn-primary" style="padding: 0.75rem 1.5rem; border-radius: 50px">
                ✨ AI Generator
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom: 2rem; padding: 1.5rem">
        <form action="{{ route('question.index') }}" method="GET" style="display: flex; gap: 1rem; align-items: flex-end">
            <div style="flex: 1">
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Skill</label>
                <select name="skill" class="form-control" style="width: 100%; padding: 0.6rem; background: var(--bg-secondary); border: 1px solid var(--glass-border); border-radius: var(--radius); color: var(--text-main);">
                    <option value="">All Skills</option>
                    <option value="reading" {{ request('skill') == 'reading' ? 'selected' : '' }}>Reading</option>
                    <option value="listening" {{ request('skill') == 'listening' ? 'selected' : '' }}>Listening</option>
                    <option value="writing" {{ request('skill') == 'writing' ? 'selected' : '' }}>Writing</option>
                    <option value="speaking" {{ request('skill') == 'speaking' ? 'selected' : '' }}>Speaking</option>
                </select>
            </div>
            <div style="flex: 1">
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Type</label>
                <select name="type" class="form-control" style="width: 100%; padding: 0.6rem; background: var(--bg-secondary); border: 1px solid var(--glass-border); border-radius: var(--radius); color: var(--text-main);">
                    <option value="">All Types</option>
                    <option value="multiple_choice" {{ request('type') == 'multiple_choice' ? 'selected' : '' }}>MCQ</option>
                    <option value="gap_fill" {{ request('type') == 'gap_fill' ? 'selected' : '' }}>Gap Fill</option>
                </select>
            </div>
            <button type="submit" class="btn btn-outline" style="padding: 0.6rem 1.5rem">Filter</button>
            @if(request()->anyFilled(['skill', 'type']))
                <a href="{{ route('question.index') }}" class="btn btn-outline" style="padding: 0.6rem 1.5rem; color: #ef4444; border-color: rgba(239, 68, 68, 0.2)">Reset</a>
            @endif
        </form>
    </div>

    <!-- Questions Table -->
    <div class="card" style="padding: 0; overflow: hidden">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--glass-border);">
                    <th style="padding: 1rem 1.5rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Question</th>
                    <th style="padding: 1rem 1.5rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Skill</th>
                    <th style="padding: 1rem 1.5rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Type</th>
                    <th style="padding: 1rem 1.5rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Difficulty</th>
                    <th style="padding: 1rem 1.5rem; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Actions</th>
                </tr>
            </thead>
            <tbody style="color: var(--text-main);">
                @forelse($questions as $question)
                    <tr style="border-bottom: 1px solid var(--glass-border); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 1.25rem 1.5rem;">
                            <div style="font-weight: 600; margin-bottom: 0.25rem">{{ $question->topic }}</div>
                            <div style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 300px;">
                                {{ Str::limit(strip_tags($question->content['question'] ?? $question->content['text'] ?? ''), 100) }}
                            </div>
                        </td>
                        <td style="padding: 1.25rem 1.5rem;">
                            <span class="badge" style="background: rgba(99, 102, 241, 0.1); color: #818cf8; text-transform: capitalize;">{{ $question->skill }}</span>
                        </td>
                        <td style="padding: 1.25rem 1.5rem;">
                            <span style="font-size: 0.875rem">{{ Str::upper(str_replace('_', ' ', $question->type)) }}</span>
                        </td>
                        <td style="padding: 1.25rem 1.5rem;">
                            @php
                                $color = [
                                    'easy' => '#10b981',
                                    'medium' => '#f59e0b',
                                    'hard' => '#ef4444'
                                ][$question->difficulty] ?? '#71717a';
                            @endphp
                            <span style="color: {{ $color }}; font-weight: 600; font-size: 0.75rem; text-transform: uppercase;">● {{ $question->difficulty }}</span>
                        </td>
                        <td style="padding: 1.25rem 1.5rem;">
                            <div style="display: flex; gap: 0.5rem">
                                <form action="{{ route('question.delete', $question->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; color: #ef4444; border-color: rgba(239, 68, 68, 0.1)" onclick="return confirm('Delete this question?')">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding: 4rem; text-align: center; color: var(--text-muted);">
                            <div style="font-size: 2rem; margin-bottom: 1rem">🔍</div>
                            No questions found or manual entry is empty.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($questions->hasPages())
            <div style="padding: 1.5rem; border-top: 1px solid var(--glass-border);">
                {{ $questions->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
