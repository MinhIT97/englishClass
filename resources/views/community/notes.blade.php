<x-app-layout>
    <header style="margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:flex-end">
        <div>
            <h1 style="font-size:1.75rem">📝 Ghi chú học tập công khai</h1>
            <p style="color:var(--text-muted)">Chia sẻ và học hỏi từ cộng đồng.</p>
        </div>
        <button type="button" onclick="document.getElementById('new-note-form').style.display='block'" class="btn btn-primary" style="padding:0.5rem 1rem">+ Ghi chú mới</button>
    </header>

    <form id="new-note-form" method="POST" action="{{ route('community.notes.store') }}" style="display:none;background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:12px;padding:1rem;margin-bottom:1.5rem">
        @csrf
        <input type="text" name="title" required placeholder="Tiêu đề..." style="width:100%;margin-bottom:0.5rem;padding:0.5rem;border:1px solid var(--glass-border);border-radius:8px;background:var(--bg);color:var(--text-main)">
        <textarea name="content" rows="6" required placeholder="Nội dung ghi chú..." style="width:100%;margin-bottom:0.5rem;padding:0.5rem;border:1px solid var(--glass-border);border-radius:8px;background:var(--bg);color:var(--text-main);font-family:inherit"></textarea>
        <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;font-size:0.875rem">
            <input type="checkbox" name="is_public" value="1"> Công khai
        </label>
        <button type="submit" class="btn btn-primary" style="padding:0.5rem 1rem">Đăng</button>
    </form>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem">
        @forelse($notes as $note)
            <article style="background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:12px;padding:1.25rem">
                <h3 style="margin:0 0 0.5rem;font-size:1.05rem">{{ $note->title }}</h3>
                <p style="color:var(--text-muted);font-size:0.8rem;margin-bottom:0.75rem">
                    ✍️ {{ $note->user->name ?? 'Ẩn danh' }} · {{ $note->created_at->diffForHumans() }}
                </p>
                <p style="line-height:1.6;margin-bottom:1rem">{{ Str::limit($note->content, 240) }}</p>
                <details>
                    <summary style="cursor:pointer;color:var(--primary);font-size:0.875rem">💬 Bình luận ({{ $note->comments->count() }})</summary>
                    <form method="POST" action="{{ route('community.comments.store') }}" style="margin-top:0.5rem">
                        @csrf
                        <input type="hidden" name="commentable_type" value="{{ get_class($note) }}">
                        <input type="hidden" name="commentable_id" value="{{ $note->id }}">
                        <textarea name="body" rows="2" placeholder="Bình luận..." style="width:100%;padding:0.4rem;border:1px solid var(--glass-border);border-radius:6px;background:var(--bg);color:var(--text-main);font-family:inherit;font-size:0.875rem"></textarea>
                        <button type="submit" class="btn btn-outline" style="margin-top:0.25rem;padding:0.3rem 0.6rem;font-size:0.75rem">Gửi</button>
                    </form>
                </details>
            </article>
        @empty
            <x-empty-state icon="📝" title="Chưa có ghi chú công khai" description="Hãy là người đầu tiên chia sẻ!" />
        @endforelse
    </div>

    <div style="margin-top:1.5rem">{{ $notes->links() }}</div>
</x-app-layout>