{{--
    Daily Quests widget — fetch from /quests on load, render progress
    bars, fire confetti on completion.
--}}
<div x-data="dailyQuests()" x-init="load()" class="daily-quests" style="background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:14px;padding:1.25rem">
    <h2 style="font-size:1.1rem;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.5rem">
        <span aria-hidden="true">🎯</span> Quest hôm nay
    </h2>

    <div x-show="loading" x-cloak>
        <x-ui.skeleton variant="list-item" :count="3" />
    </div>

    <div x-show="!loading && quests.length === 0" x-cloak>
        <x-empty-state icon="🌙" title="Hôm nay không có quest nào" description="Hãy quay lại vào ngày mai nhé!" />
    </div>

    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:0.75rem">
        <template x-for="q in quests" :key="q.id">
            <li :style="`opacity:${q.completed ? 0.6 : 1};display:flex;gap:0.75rem;padding:0.75rem;border-radius:10px;background:var(--bg-secondary);align-items:center`">
                <div style="font-size:1.75rem" x-text="q.icon" aria-hidden="true"></div>
                <div style="flex:1;min-width:0">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.25rem">
                        <strong x-text="q.title" style="font-size:0.95rem"></strong>
                        <span style="font-size:0.75rem;color:var(--accent)">+<span x-text="q.xp_reward"></span> XP</span>
                    </div>
                    <p x-text="q.description" style="color:var(--text-muted);font-size:0.8rem;margin:0 0 0.4rem"></p>
                    <div style="height:6px;background:var(--bg);border-radius:999px;overflow:hidden">
                        <div :style="`width:${Math.min(100,(q.progress / q.target) * 100)}%;height:100%;background:${q.completed ? 'var(--accent)' : 'var(--primary)'};transition:width 0.5s`"></div>
                    </div>
                    <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.2rem">
                        <span x-text="q.progress"></span> / <span x-text="q.target"></span>
                        <span x-show="q.completed" x-cloak style="color:var(--accent);margin-left:0.5rem">✓ Hoàn thành</span>
                    </div>
                </div>
            </li>
        </template>
    </ul>
</div>

@once
@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            window.Alpine.data('dailyQuests', () => ({
                loading: true,
                quests: [],
                async load() {
                    try {
                        const res = await fetch('/quests');
                        const data = await res.json();
                        this.quests = data.quests || [];
                    } catch (e) { /* offline */ }
                    this.loading = false;
                },
            }));
        });
    </script>
@endpush
@endonce