<x-app-layout>
    <div style="margin-bottom: 2rem">
        <h1 style="font-size: 1.75rem; margin-bottom: 0.5rem">🧠 Cấu trúc câu đã học</h1>
        <p style="color: var(--text-muted)">{{ $entries->total() }} cấu trúc</p>
    </div>

    @if($entries->isEmpty())
        <x-ui.empty-state icon="📭" title="Chưa có cấu trúc câu" description="Hãy kết nối Telegram bot để học cấu trúc mới mỗi ngày.">
            <a href="{{ url('/student/settings/telegram') }}" class="btn btn-primary">🤖 Kết nối Telegram</a>
        </x-ui.empty-state>
    @else
        <div style="display: grid; gap: 1rem">
            @foreach($entries as $g)
                <div class="glass-card" style="padding: 1.25rem">
                    <code style="font-size: 1.1rem; display: block; margin-bottom: 0.75rem; background: var(--bg-main); padding: 0.5rem 1rem; border-radius: 8px">{{ $g->structure }}</code>
                    @if($g->explanation_vi)
                        <p style="margin-bottom: 0.5rem">💡 {{ $g->explanation_vi }}</p>
                    @endif
                    @if($g->example_en)
                        <p style="margin-bottom: 0.25rem; color: var(--text-muted)">✏️ <i>"{{ $g->example_en }}"</i></p>
                    @endif
                    @if($g->example_vi)
                        <p style="color: var(--text-muted); font-size: 0.9rem">🇻🇳 {{ $g->example_vi }}</p>
                    @endif
                    @if($g->topic)
                        <div style="margin-top: 0.75rem">
                            <span style="font-size: 0.75rem; background: var(--bg-main); padding: 0.2rem 0.6rem; border-radius: 99px">{{ $g->topic->name_vi }}</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div style="margin-top: 2rem">{{ $entries->links() }}</div>
    @endif
</x-app-layout>
