<x-app-layout>
    <header style="margin-bottom:1.5rem">
        <h1 style="font-size:1.75rem">👨‍🏫 Bảng điều khiển giáo viên</h1>
        <p style="color:var(--text-muted)">Tổng quan các lớp học bạn phụ trách.</p>
    </header>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:2rem">
        <div class="card" style="padding:1.25rem">
            <p style="color:var(--text-muted);font-size:0.875rem">Lớp học</p>
            <h2 style="font-size:2rem">{{ $classrooms->count() }}</h2>
        </div>
        <div class="card" style="padding:1.25rem">
            <p style="color:var(--text-muted);font-size:0.875rem">Tổng học sinh</p>
            <h2 style="font-size:2rem">{{ $totalStudents }}</h2>
        </div>
        <div class="card" style="padding:1.25rem">
            <p style="color:var(--text-muted);font-size:0.875rem">Hoạt động 7 ngày</p>
            <h2 style="font-size:2rem;color:var(--accent)">{{ $activeLast7Days }}</h2>
        </div>
        <div class="card" style="padding:1.25rem">
            <p style="color:var(--text-muted);font-size:0.875rem">Cần chú ý</p>
            <h2 style="font-size:2rem;color:var(--danger)">{{ $atRisk->count() }}</h2>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem">
        {{-- Classrooms --}}
        <section style="background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:14px;padding:1.5rem">
            <h2 style="margin-bottom:1rem;font-size:1.1rem">🏫 Lớp học của tôi</h2>
            @if($classrooms->isEmpty())
                <x-empty-state icon="🏫" title="Bạn chưa tạo lớp nào" description="Tạo lớp học đầu tiên để bắt đầu." />
            @else
                <div style="display:flex;flex-direction:column;gap:0.75rem">
                    @foreach($classrooms as $classroom)
                        <a href="{{ route('classroom.show', $classroom) }}" style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem 1rem;background:var(--bg-secondary);border-radius:10px;text-decoration:none;color:inherit">
                            <div>
                                <strong>{{ $classroom->name }}</strong>
                                <p style="font-size:0.75rem;color:var(--text-muted);margin:0">📌 {{ $classroom->students->count() }} học sinh · {{ $classroom->posts->count() }} bài viết</p>
                            </div>
                            <span style="color:var(--text-muted)">→</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- At-risk --}}
        <section style="background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:14px;padding:1.5rem">
            <h2 style="margin-bottom:1rem;font-size:1.1rem;color:var(--danger)">⚠️ Cần quan tâm</h2>
            @if($atRisk->isEmpty())
                <x-empty-state icon="✨" title="Mọi học sinh đều hoạt động!" />
            @else
                <ul style="list-style:none;padding:0;margin:0">
                    @foreach($atRisk as $student)
                        <li style="padding:0.5rem 0;border-bottom:1px solid var(--glass-border);display:flex;justify-content:space-between;align-items:center">
                            <span>{{ $student->name }}</span>
                            <small style="color:var(--text-muted)">{{ $student->email }}</small>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- Recent submissions --}}
    @if($recentPosts->count())
        <section style="background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:14px;padding:1.5rem;margin-top:1.5rem">
            <h2 style="margin-bottom:1rem;font-size:1.1rem">📝 Bài chờ chấm</h2>
            <ul style="list-style:none;padding:0;margin:0">
                @foreach($recentPosts as $post)
                    <li style="padding:0.5rem 0">#{{ $post->id }} — {{ \Carbon\Carbon::parse($post->created_at)->diffForHumans() }}</li>
                @endforeach
            </ul>
        </section>
    @endif
</x-app-layout>