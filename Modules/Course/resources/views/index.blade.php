<x-app-layout>
    <!-- Page Header -->
    <div class="course-header-wrap">
        <div>
            <h1 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem">{{ __('ui.master_courses') }}</h1>
            <p style="color: var(--text-muted)">{{ __('ui.course_desc') }}</p>
        </div>
        <form action="{{ route('course.index') }}" method="GET" class="course-search-form">
            <input type="text" name="title" value="{{ request('title') }}" placeholder="{{ __('ui.search_courses') }}"
                   style="flex: 1; background: var(--bg-secondary); border: 1px solid var(--glass-border); border-radius: 50px; padding: 0.6rem 1.25rem; color: white; outline: none; font-size: 0.875rem; min-width: 0">
            <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.1rem; border-radius: 50px; flex-shrink: 0">🔍</button>
        </form>
    </div>

    <!-- Course Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem">
        @forelse($courses as $index => $course)
            @php
                $gradients = [
                    'linear-gradient(135deg, #6366f1 0%, #312e81 100%)',
                    'linear-gradient(135deg, #8b5cf6 0%, #4c1d95 100%)',
                    'linear-gradient(135deg, #ec4899 0%, #831843 100%)',
                    'linear-gradient(135deg, #06b6d4 0%, #164e63 100%)'
                ];
                $grad = $gradients[$index % count($gradients)];
            @endphp
            <div class="glass-card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column">
                <div style="height: 130px; background: {{ $grad }}; display: flex; align-items: center; justify-content: center; position: relative">
                    <span style="font-size: 3rem">
                        @if(Str::contains($course->title, 'Writing')) 📝
                        @elseif(Str::contains($course->title, 'Speaking')) 🗣️
                        @elseif(Str::contains($course->title, 'Grammar')) 📚
                        @else 🎓 @endif
                    </span>
                    <span style="position: absolute; top: 0.75rem; right: 0.75rem; background: rgba(0,0,0,0.3); color: white; padding: 0.2rem 0.65rem; border-radius: 50px; font-size: 0.72rem; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.1)">
                        ${{ number_format($course->price, 2) }}
                    </span>
                </div>
                <div style="padding: 1.25rem; flex: 1; display: flex; flex-direction: column">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.5rem">
                        <h3 style="margin: 0; font-size: 1rem; font-weight: 700; line-height: 1.4">{{ $course->title }}</h3>
                        <span class="badge" style="background: rgba(16,185,129,0.1); color: #10b981; font-size: 0.65rem; border: 1px solid rgba(16,185,129,0.2); white-space: nowrap">{{ $course->status }}</span>
                    </div>
                    <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 1.25rem; flex: 1">
                        {{ Str::limit($course->description, 110) }}
                    </p>
                    <div style="display: flex; gap: 0.6rem">
                        <a href="{{ route('course.show', $course->id) }}" class="btn btn-outline" style="flex: 1; text-align: center; font-size: 0.82rem; padding: 0.55rem">{{ __('ui.learn_more') }}</a>
                        @php $isEnrolled = auth()->user()->enrolledCourses()->where('course_id', $course->id)->exists(); @endphp
                        @if($isEnrolled)
                            <a href="{{ route('course.show', $course->id) }}" class="btn btn-primary" style="flex: 1; text-align: center; font-size: 0.82rem; padding: 0.55rem; background: #10b981">{{ __('ui.enrolled') }}</a>
                        @else
                            <form action="{{ route('course.enroll', $course->id) }}" method="POST" style="flex: 1">
                                @csrf
                                <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 0.82rem; padding: 0.55rem">{{ __('ui.enroll_now') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div style="grid-column: 1/-1; text-align: center; padding: 4rem 1rem">
                <div style="font-size: 3rem; margin-bottom: 1rem">🍃</div>
                <h3>{{ __('ui.no_courses') }}</h3>
                <p style="color: var(--text-muted)">{{ __('ui.search_try_different') }}</p>
                <a href="{{ route('course.index') }}" style="color: var(--primary); text-decoration: underline; margin-top: 1rem; display: inline-block">{{ __('ui.view_all_courses') }}</a>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div style="margin-top: 2.5rem">{{ $courses->appends(request()->query())->links() }}</div>

    <style>
        .course-header-wrap {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .course-search-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        @media (max-width: 640px) {
            .course-header-wrap { flex-direction: column; }
            .course-search-form { width: 100%; }
        }
    </style>
</x-app-layout>
