<x-app-layout>
    <header style="margin-bottom:1.5rem">
        <h1 style="font-size:1.75rem">📅 Lịch học của tôi</h1>
        <p style="color:var(--text-muted)">Lên kế hoạch học tập để duy trì streak. Tháng {{ $start->format('m/Y') }}</p>
    </header>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem">
        <section>
            <div class="glass" style="padding:1rem;border-radius:14px">
                <div style="display:flex;justify-content:space-between;margin-bottom:1rem">
                    <a href="?start={{ $start->copy()->subMonth()->toDateString() }}" class="btn btn-outline" style="padding:0.4rem 0.8rem">← Tháng trước</a>
                    <strong>{{ $start->format('F Y') }}</strong>
                    <a href="?start={{ $start->copy()->addMonth()->toDateString() }}" class="btn btn-outline" style="padding:0.4rem 0.8rem">Tháng sau →</a>
                </div>

                <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:0.25rem;text-align:center;font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem">
                    @foreach(['CN','T2','T3','T4','T5','T6','T7'] as $dow)
                        <div>{{ $dow }}</div>
                    @endforeach
                </div>

                @php
                    $cursor = $start->copy()->startOfWeek(Carbon::SUNDAY);
                    $endCal = $end->copy()->endOfWeek(Carbon::SATURDAY);
                    $byDay = $plans->groupBy(fn ($p) => $p->scheduled_at->toDateString());
                @endphp

                <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:0.25rem">
                    @while($cursor->lte($endCal))
                        @php
                            $dateStr = $cursor->toDateString();
                            $dayPlans = $byDay->get($dateStr, collect());
                            $isToday = $dateStr === now()->toDateString();
                            $isCurrentMonth = $cursor->month === $start->month;
                        @endphp
                        <div style="min-height:90px;padding:0.4rem;border:1px solid var(--glass-border);border-radius:8px;background:{{ $isToday ? 'rgba(99,102,241,0.08)' : 'var(--bg-elevated)' }};opacity:{{ $isCurrentMonth ? 1 : 0.5 }}">
                            <div style="font-size:0.75rem;font-weight:{{ $isToday ? 700 : 400 }};color:{{ $isToday ? 'var(--primary)' : 'var(--text-muted)' }}">{{ $cursor->day }}</div>
                            @foreach($dayPlans->take(3) as $p)
                                <div title="{{ $p->title }}" style="font-size:0.7rem;padding:1px 4px;margin-top:2px;background:var(--bg-secondary);border-radius:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    {{ $p->scheduled_at->format('H:i') }} {{ $p->title }}
                                </div>
                            @endforeach
                            @if($dayPlans->count() > 3)
                                <div style="font-size:0.7rem;color:var(--text-muted)">+{{ $dayPlans->count() - 3 }} khác</div>
                            @endif
                        </div>
                        @php $cursor->addDay(); @endphp
                    @endwhile
                </div>
            </div>
        </section>

        <aside>
            <div class="glass" style="padding:1rem;border-radius:14px;margin-bottom:1rem">
                <h2 style="font-size:1.1rem;margin-bottom:0.75rem">➕ Thêm lịch học</h2>
                <form method="POST" action="{{ route('study-plan.store') }}">
                    @csrf
                    <label style="display:block;margin-bottom:0.5rem;font-size:0.875rem">
                        Tiêu đề
                        <input type="text" name="title" required maxlength="200" style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid var(--glass-border);border-radius:8px;background:var(--bg);color:var(--text-main)">
                    </label>
                    <label style="display:block;margin-bottom:0.5rem;font-size:0.875rem">
                        Thời gian
                        <input type="datetime-local" name="scheduled_at" required style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid var(--glass-border);border-radius:8px;background:var(--bg);color:var(--text-main)">
                    </label>
                    <div style="display:flex;gap:0.5rem;margin-bottom:0.5rem">
                        <label style="flex:1;font-size:0.875rem">
                            Thời lượng (phút)
                            <input type="number" name="duration_minutes" value="30" min="5" max="480" style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid var(--glass-border);border-radius:8px;background:var(--bg);color:var(--text-main)">
                        </label>
                        <label style="flex:1;font-size:0.875rem">
                            Loại
                            <select name="type" style="width:100%;margin-top:0.25rem;padding:0.5rem;border:1px solid var(--glass-border);border-radius:8px;background:var(--bg);color:var(--text-main)">
                                <option value="lesson">📚 Bài học</option>
                                <option value="mock_test">📝 Mock test</option>
                                <option value="review">🔄 Ôn tập</option>
                                <option value="practice">💪 Luyện tập</option>
                                <option value="rest">😴 Nghỉ ngơi</option>
                            </select>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;padding:0.6rem">Lưu lịch</button>
                </form>
            </div>

            <div class="glass" style="padding:1rem;border-radius:14px">
                <h2 style="font-size:1.1rem;margin-bottom:0.75rem">🍅 Pomodoro Timer</h2>
                <div x-data="pomodoroTimer()" style="text-align:center">
                    <div x-text="formatted" style="font-size:3rem;font-variant-numeric:tabular-nums;font-weight:700;margin:0.5rem 0"></div>
                    <div style="display:flex;gap:0.5rem;justify-content:center">
                        <button type="button" @click="toggle" class="btn btn-primary" x-text="running ? '⏸ Pause' : '▶ Start'"></button>
                        <button type="button" @click="reset" class="btn btn-outline">↺</button>
                    </div>
                    <p style="font-size:0.75rem;color:var(--text-muted);margin-top:0.5rem">25 phút học · 5 phút nghỉ</p>
                </div>
            </div>
        </aside>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            window.Alpine.data('pomodoroTimer', () => ({
                running: false,
                remaining: 25 * 60,
                isBreak: false,
                _interval: null,
                get formatted() {
                    const m = Math.floor(this.remaining / 60);
                    const s = this.remaining % 60;
                    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
                },
                toggle() {
                    if (this.running) { clearInterval(this._interval); this.running = false; return; }
                    this.running = true;
                    this._interval = setInterval(() => {
                        this.remaining--;
                        if (this.remaining <= 0) {
                            this.isBreak = !this.isBreak;
                            this.remaining = this.isBreak ? 5 * 60 : 25 * 60;
                            window.Toast?.show(this.isBreak ? '⏸ Nghỉ 5 phút nhé!' : '▶ Bắt đầu học tiếp!');
                            window.announce?.(this.isBreak ? 'Nghỉ 5 phút' : 'Bắt đầu học tiếp');
                        }
                    }, 1000);
                },
                reset() {
                    clearInterval(this._interval);
                    this.running = false;
                    this.remaining = this.isBreak ? 5 * 60 : 25 * 60;
                },
            }));
        });
    </script>
</x-app-layout>