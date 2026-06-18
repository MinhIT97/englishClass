{{--
    Embeddable form: lets a non-admin user submit a request to raise
    their daily lesson quota. Drop into any view where the user has
    hit the limit. Pass $lessonType ('course' | 'classroom' |
    'daily_lesson') and optional $remaining (int) for context.
--}}
@props(['lessonType', 'remaining' => null])

@php
    use App\Models\LessonRequest;
@endphp

<div {{ $attributes->merge(['class' => 'glass', 'style' => 'padding: 1.25rem; margin-top: 1rem']) }}>
    <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem">Xin thêm bài học</h3>
    @if($remaining !== null)
        <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1rem">
            Bạn đã dùng hết {{ $remaining }} bài trong hôm nay. Gửi yêu cầu để admin xem xét.
        </p>
    @endif
    <form method="POST" action="{{ route('lesson-requests.store') }}">
        @csrf
        <input type="hidden" name="lesson_type" value="{{ $lessonType }}">
        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem">
            Số bài muốn thêm
            <input type="number" name="requested_extra" min="1" max="50" value="3" required style="width: 100%; margin-top: 0.25rem; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--glass-border); background: transparent; color: inherit">
        </label>
        <label style="display: block; margin-bottom: 0.75rem; font-size: 0.875rem">
            Lý do (tuỳ chọn)
            <textarea name="reason" rows="3" style="width: 100%; margin-top: 0.25rem; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--glass-border); background: transparent; color: inherit"></textarea>
        </label>
        <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem; width: 100%">Gửi yêu cầu</button>
    </form>
</div>