<x-app-layout>
    <header style="margin-bottom:1.5rem">
        <h1 style="font-size:1.75rem">⚙️ Cài đặt cá nhân</h1>
        <p style="color:var(--text-muted)">Tuỳ chỉnh thông báo, học tập và quyền riêng tư.</p>
    </header>

    <form method="POST" action="{{ route('settings.preferences.update') }}" style="max-width:680px">
        @csrf
        @method('PUT')

        <fieldset style="background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:14px;padding:1.5rem;margin-bottom:1rem">
            <legend style="font-weight:600;padding:0 0.5rem">🔔 Thông báo</legend>
            @foreach([
                'notify_lesson_reminder' => 'Nhắc nhở bài học hàng ngày',
                'notify_quota_request' => 'Khi có yêu cầu xin thêm bài học (admin)',
                'notify_achievement' => 'Khi đạt thành tích mới',
                'notify_feedback' => 'Khi nhận feedback từ giáo viên',
            ] as $name => $label)
                <label style="display:flex;align-items:center;gap:0.5rem;padding:0.4rem 0;font-size:0.95rem">
                    <input type="checkbox" name="{{ $name }}" value="1" {{ $pref->$name ? 'checked' : '' }}>
                    {{ $label }}
                </label>
            @endforeach
            <label style="display:block;margin-top:0.5rem;font-size:0.95rem">
                Tần suất:
                <select name="notification_digest" style="margin-top:0.25rem;padding:0.4rem;border:1px solid var(--glass-border);border-radius:6px;background:var(--bg);color:var(--text-main)">
                    <option value="realtime" {{ $pref->notification_digest === 'realtime' ? 'selected' : '' }}>Realtime</option>
                    <option value="daily" {{ $pref->notification_digest === 'daily' ? 'selected' : '' }}>Tổng hợp hàng ngày</option>
                    <option value="weekly" {{ $pref->notification_digest === 'weekly' ? 'selected' : '' }}>Tổng hợp hàng tuần</option>
                    <option value="off" {{ $pref->notification_digest === 'off' ? 'selected' : '' }}>Tắt</option>
                </select>
            </label>
        </fieldset>

        <fieldset style="background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:14px;padding:1.5rem;margin-bottom:1rem">
            <legend style="font-weight:600;padding:0 0.5rem">📚 Học tập</legend>
            <label style="display:block;margin-bottom:0.5rem;font-size:0.95rem">
                Mục tiêu ôn tập/ngày:
                <input type="number" name="daily_review_goal" value="{{ $pref->daily_review_goal }}" min="5" max="200"
                       style="margin-top:0.25rem;padding:0.4rem;border:1px solid var(--glass-border);border-radius:6px;background:var(--bg);color:var(--text-main);width:100%">
            </label>
            <label style="display:block;margin-bottom:0.5rem;font-size:0.95rem">
                Thời gian học ưa thích:
                <select name="preferred_study_time" style="margin-top:0.25rem;padding:0.4rem;border:1px solid var(--glass-border);border-radius:6px;background:var(--bg);color:var(--text-main);width:100%">
                    <option value="">-- Chọn --</option>
                    <option value="morning" {{ $pref->preferred_study_time === 'morning' ? 'selected' : '' }}>🌅 Sáng</option>
                    <option value="afternoon" {{ $pref->preferred_study_time === 'afternoon' ? 'selected' : '' }}>☀️ Chiều</option>
                    <option value="evening" {{ $pref->preferred_study_time === 'evening' ? 'selected' : '' }}>🌆 Tối</option>
                    <option value="night" {{ $pref->preferred_study_time === 'night' ? 'selected' : '' }}>🌙 Đêm</option>
                </select>
            </label>
            <label style="display:block;font-size:0.95rem">
                Thời lượng Pomodoro (phút):
                <input type="number" name="session_duration_minutes" value="{{ $pref->session_duration_minutes }}" min="10" max="120"
                       style="margin-top:0.25rem;padding:0.4rem;border:1px solid var(--glass-border);border-radius:6px;background:var(--bg);color:var(--text-main);width:100%">
            </label>
        </fieldset>

        <fieldset style="background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:14px;padding:1.5rem;margin-bottom:1rem">
            <legend style="font-weight:600;padding:0 0.5rem">🌐 Ngôn ngữ & Riêng tư</legend>
            <label style="display:block;margin-bottom:0.5rem;font-size:0.95rem">
                Ngôn ngữ giao diện:
                <select name="locale" style="margin-top:0.25rem;padding:0.4rem;border:1px solid var(--glass-border);border-radius:6px;background:var(--bg);color:var(--text-main);width:100%">
                    <option value="vi" {{ $pref->locale === 'vi' ? 'selected' : '' }}>🇻🇳 Tiếng Việt</option>
                    <option value="en" {{ $pref->locale === 'en' ? 'selected' : '' }}>🇬🇧 English</option>
                </select>
            </label>
            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.95rem;padding:0.3rem 0">
                <input type="checkbox" name="show_in_leaderboard" value="1" {{ $pref->show_in_leaderboard ? 'checked' : '' }}>
                Hiển thị trên bảng xếp hạng
            </label>
            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.95rem;padding:0.3rem 0">
                <input type="checkbox" name="show_study_notes_publicly" value="1" {{ $pref->show_study_notes_publicly ? 'checked' : '' }}>
                Công khai ghi chú mặc định
            </label>
        </fieldset>

        <div style="display:flex;gap:0.5rem">
            <button type="submit" class="btn btn-primary" style="padding:0.6rem 1.2rem">💾 Lưu cài đặt</button>
            <a href="{{ route('settings.preferences.export') }}" class="btn btn-outline" style="padding:0.6rem 1.2rem">📥 Xuất dữ liệu cá nhân</a>
        </div>
    </form>
</x-app-layout>