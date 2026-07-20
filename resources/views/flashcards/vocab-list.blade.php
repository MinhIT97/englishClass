<x-app-layout>
    <div style="margin-bottom: 2rem">
        <h1 style="font-size: 1.75rem; margin-bottom: 0.5rem">📚 Từ vựng của bạn</h1>
        <p style="color: var(--text-muted)">{{ $words->total() }} từ đã học</p>
    </div>

    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap">
        <form method="GET" style="display: flex; gap: 0.5rem; flex: 1; min-width: 250px">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Tìm từ vựng..." style="flex: 1; padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-main)">
            <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; border-radius: 8px">Tìm</button>
            @if(request('search') || request('topic'))
                <a href="{{ url('/student/vocabulary') }}" style="padding: 0.5rem 1rem; color: var(--text-muted); text-decoration: none">Xóa lọc</a>
            @endif
        </form>

        <select onchange="window.location=this.value" style="padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-main); min-width: 180px">
            <option value="{{ url('/student/vocabulary') }}">Tất cả chủ đề</option>
            @foreach($topics as $topic)
                <option value="{{ url('/student/vocabulary?topic=' . $topic->id) }}" {{ request('topic') == $topic->id ? 'selected' : '' }}>
                    {{ $topic->name_vi }}
                </option>
            @endforeach
        </select>
    </div>

    @if($words->isEmpty())
        <x-ui.empty-state icon="📭" title="Chưa có từ vựng" description="Hãy kết nối Telegram bot để bắt đầu học từ mới mỗi ngày.">
            <a href="{{ url('/student/settings/telegram') }}" class="btn btn-primary">🤖 Kết nối Telegram</a>
        </x-ui.empty-state>
    @else
        <div style="display: grid; gap: 0.5rem">
            @foreach($words as $w)
                <div class="glass-card" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem">
                    <div>
                        <strong style="font-size: 1.1rem">{{ $w->word }}</strong>
                        @if($w->ipa)<code style="margin-left: 0.5rem; font-size: 0.85rem">{{ $w->ipa }}</code>@endif
                        @if($w->pos)<span style="margin-left: 0.5rem; color: var(--text-muted); font-size: 0.85rem"><i>{{ $w->pos }}</i></span>@endif
                        <div style="color: var(--text-muted); margin-top: 0.25rem">{{ $w->meaning_vi }}</div>
                        @if($w->example_en)<div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.15rem"><i>"{{ $w->example_en }}"</i></div>@endif
                    </div>
                    @if($w->topic)
                        <span style="font-size: 0.75rem; background: var(--bg-main); padding: 0.25rem 0.75rem; border-radius: 99px; white-space: nowrap">{{ $w->topic->name_vi }}</span>
                    @endif
                </div>
            @endforeach
        </div>

        <div style="margin-top: 2rem">{{ $words->links() }}</div>
    @endif
</x-app-layout>
