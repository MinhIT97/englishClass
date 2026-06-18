{{--
    Global search bar (Cmd/Ctrl + K). Renders an input that pops a
    dropdown with grouped results (courses, classrooms, notes, users).
--}}
<div x-data="globalSearch()" x-init="init()" class="global-search" style="position:relative;width:100%;max-width:480px">
    <div style="position:relative;display:flex;align-items:center">
        <input
            type="search"
            data-global-search
            x-model="query"
            @input.debounce.250ms="search"
            @focus="open = true"
            @keydown.escape="open = false"
            @keydown.down.prevent="move(1)"
            @keydown.up.prevent="move(-1)"
            @keydown.enter.prevent="go()"
            placeholder="Tìm kiếm... (Ctrl+K)"
            aria-label="Global search"
            style="width:100%;padding:0.5rem 2.25rem 0.5rem 0.75rem;border:1px solid var(--glass-border);border-radius:8px;background:var(--bg);color:var(--text-main);font-size:0.875rem">
        <kbd style="position:absolute;right:0.5rem;color:var(--text-muted);font-size:0.7rem">Ctrl+K</kbd>
    </div>

    <div x-show="open && (query.length >= 2 || loading)" x-cloak
         @click.outside="open = false"
         style="position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:10px;box-shadow:var(--shadow-hover);max-height:60vh;overflow-y:auto;z-index:100">

        <div x-show="loading" x-cloak style="padding:1rem;text-align:center;color:var(--text-muted)">Đang tìm...</div>

        <div x-show="!loading && hasResults" x-cloak>
            <template x-for="(items, group) in groups" :key="group">
                <div x-show="items.length > 0">
                    <div style="padding:0.4rem 0.75rem;font-size:0.7rem;text-transform:uppercase;color:var(--text-muted);background:var(--bg-secondary)">
                        <span x-text="groupLabel(group)"></span>
                    </div>
                    <template x-for="(item, i) in items" :key="item.id || i">
                        <a :href="itemUrl(group, item)"
                           @mouseenter="selected = `${group}-${i}`"
                           :style="`display:flex;justify-content:space-between;padding:0.5rem 0.75rem;text-decoration:none;color:inherit;${selected === `${group}-${i}` ? 'background:var(--bg-secondary)' : ''}`">
                            <span x-text="itemLabel(group, item)"></span>
                            <small style="color:var(--text-muted)" x-text="itemSubtitle(group, item)"></small>
                        </a>
                    </template>
                </div>
            </template>
        </div>

        <div x-show="!loading && !hasResults && query.length >= 2" x-cloak style="padding:1.5rem;text-align:center;color:var(--text-muted)">
            Không tìm thấy kết quả.
        </div>
    </div>
</div>

@once
@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            window.Alpine.data('globalSearch', () => ({
                query: '',
                open: false,
                loading: false,
                groups: { courses: [], classrooms: [], notes: [], users: [] },
                selected: null,

                init() {
                    document.addEventListener('keydown', (e) => {
                        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                            e.preventDefault();
                            this.$root.querySelector('input')?.focus();
                        }
                    });
                },

                get hasResults() {
                    return Object.values(this.groups).some((arr) => arr.length > 0);
                },

                groupLabel(g) {
                    return { courses: '📚 Khóa học', classrooms: '🏫 Lớp học', notes: '📝 Ghi chú', users: '👤 Người dùng' }[g] || g;
                },
                itemLabel(g, item) {
                    return item.title || item.name || item.email || '?';
                },
                itemSubtitle(g, item) {
                    if (g === 'users') return item.role;
                    return item.slug || item.email || '';
                },
                itemUrl(g, item) {
                    if (g === 'courses') return `/courses/${item.slug}`;
                    if (g === 'classrooms') return `/classroom/${item.id}`;
                    if (g === 'notes') return `/community/notes#note-${item.id}`;
                    if (g === 'users') return `/admin/users#user-${item.id}`;
                    return '#';
                },

                async search() {
                    if (this.query.length < 2) {
                        this.groups = { courses: [], classrooms: [], notes: [], users: [] };
                        return;
                    }
                    this.loading = true;
                    this.open = true;
                    try {
                        const res = await fetch(`/search?q=${encodeURIComponent(this.query)}`, { headers: { 'Accept': 'application/json' } });
                        const data = await res.json();
                        this.groups = data.groups || this.groups;
                    } catch (e) { /* offline */ }
                    this.loading = false;
                },

                move(delta) {
                    const flat = [];
                    Object.entries(this.groups).forEach(([g, items]) => items.forEach((it, i) => flat.push(`${g}-${i}`)));
                    if (!flat.length) return;
                    const idx = flat.indexOf(this.selected);
                    const next = Math.max(0, Math.min(flat.length - 1, (idx < 0 ? 0 : idx + delta)));
                    this.selected = flat[next];
                },

                go() {
                    if (!this.selected) return;
                    const [g, idx] = this.selected.split('-');
                    const item = this.groups[g]?.[parseInt(idx, 10)];
                    if (!item) return;
                    window.location.href = this.itemUrl(g, item);
                },
            }));
        });
    </script>
@endpush
@endonce