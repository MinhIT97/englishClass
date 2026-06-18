<x-app-layout>
    <header style="margin-bottom:1.5rem">
        <h1 style="font-size:1.75rem">📊 Tiến bộ của tôi</h1>
        <p style="color:var(--text-muted)">Phân tích chi tiết theo kỹ năng và hoạt động 30 ngày qua.</p>
    </header>

    {{-- Top stats --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:2rem">
        <div class="card" style="padding:1.25rem">
            <p style="font-size:0.875rem;color:var(--text-muted)">Band ước tính</p>
            <h2 style="font-size:2rem;color:var(--primary)">
                {{ $estimated_band ?? '—' }}
                @if(isset($stats['xp']))
                    <small style="font-size:0.875rem;color:var(--text-muted);font-weight:400">/ {{ auth()->user()->target_band ?? '?' }}</small>
                @endif
            </h2>
        </div>
        <div class="card" style="padding:1.25rem">
            <p style="font-size:0.875rem;color:var(--text-muted)">XP</p>
            <h2 style="font-size:2rem">{{ number_format($stats['xp']) }}</h2>
        </div>
        <div class="card" style="padding:1.25rem">
            <p style="font-size:0.875rem;color:var(--text-muted)">Streak</p>
            <h2 style="font-size:2rem">🔥 {{ $stats['streak'] }} ngày</h2>
        </div>
        <div class="card" style="padding:1.25rem">
            <p style="font-size:0.875rem;color:var(--text-muted)">Câu đã làm</p>
            <h2 style="font-size:2rem">{{ number_format($stats['lessons_completed']) }}</h2>
        </div>
    </div>

    {{-- Skills radar (SVG) --}}
    <section style="background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem">
        <h2 style="margin-bottom:1rem;font-size:1.1rem">🎯 Điểm chính xác theo kỹ năng</h2>
        <div style="display:flex;align-items:center;gap:2rem;flex-wrap:wrap">
            <svg viewBox="0 0 200 200" width="220" height="220" role="img" aria-label="Skill radar chart">
                @php
                    $cx = 100; $cy = 100; $r = 80;
                    $n = count($skills);
                    $points = [];
                    foreach ($skills as $i => $s) {
                        $angle = (-90 + 360 * $i / $n) * M_PI / 180;
                        $val = $s['accuracy'] ?? 0;
                        $x = $cx + $r * $val * cos($angle);
                        $y = $cy + $r * $val * sin($angle);
                        $points[] = "$x,$y";
                        // axis labels
                        $lx = $cx + ($r + 18) * cos($angle);
                        $ly = $cy + ($r + 18) * sin($angle);
                        echo '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . ($cx + $r * cos($angle)) . '" y2="' . ($cy + $r * sin($angle)) . '" stroke="var(--glass-border)" stroke-width="1"/>';
                        echo '<text x="' . $lx . '" y="' . $ly . '" font-size="11" fill="var(--text-muted)" text-anchor="middle" dominant-baseline="middle">' . ucfirst($s['skill']) . '</text>';
                    }
                @endphp
                <polygon points="{{ implode(' ', $points) }}" fill="rgba(99,102,241,0.25)" stroke="var(--primary)" stroke-width="2"/>
            </svg>
            <div style="flex:1;min-width:200px">
                @foreach($skills as $s)
                    <div style="margin-bottom:0.5rem">
                        <div style="display:flex;justify-content:space-between;font-size:0.875rem;margin-bottom:0.2rem">
                            <strong>{{ ucfirst($s['skill']) }}</strong>
                            <span style="color:var(--text-muted)">{{ $s['correct'] }}/{{ $s['total'] }}</span>
                        </div>
                        <div style="height:6px;background:var(--bg-secondary);border-radius:999px;overflow:hidden">
                            <div style="width:{{ ($s['accuracy'] ?? 0) * 100 }}%;height:100%;background:var(--primary)"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Activity heatmap --}}
    <section style="background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem">
        <h2 style="margin-bottom:1rem;font-size:1.1rem">📅 Hoạt động 30 ngày qua</h2>
        <div style="display:grid;grid-template-columns:repeat(15,1fr);gap:4px">
            @foreach($activity as $day)
                @php
                    $intensity = $day['count'] > 0 ? min(1, $day['count'] / 10) : 0;
                    $color = $intensity == 0 ? 'var(--bg-secondary)'
                        : 'rgba(99,102,241,' . (0.3 + 0.7 * $intensity) . ')';
                @endphp
                <div title="{{ $day['date'] }}: {{ $day['count'] }} câu"
                     style="aspect-ratio:1;border-radius:4px;background:{{ $color }};cursor:default"
                     aria-label="{{ $day['date'] }}: {{ $day['count'] }} câu trả lời"></div>
            @endforeach
        </div>
    </section>

    {{-- Weaknesses --}}
    <section style="background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:14px;padding:1.5rem">
        <h2 style="margin-bottom:1rem;font-size:1.1rem">⚠️ Chủ đề cần cải thiện</h2>
        @if(empty($weaknesses))
            <x-empty-state icon="🌟" title="Chưa có dữ liệu" description="Làm thêm bài tập để có phân tích chi tiết." />
        @else
            <ul style="list-style:none;padding:0;margin:0">
                @foreach($weaknesses as $w)
                    <li style="padding:0.5rem 0.75rem;background:var(--bg-secondary);border-radius:8px;margin-bottom:0.4rem;display:flex;justify-content:space-between">
                        <span>Topic #{{ $w['topic_id'] }}</span>
                        <span style="color:var(--danger);font-weight:600">{{ round(($w['accuracy'] ?? 0) * 100) }}%</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-app-layout>