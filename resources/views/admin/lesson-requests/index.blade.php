<x-app-layout>
    <div style="margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: flex-end">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem">Yêu cầu xin thêm bài học</h1>
            <p style="color: var(--text-muted)">Duyệt các yêu cầu tăng giới hạn tạo bài học từ user.</p>
        </div>
        <div class="glass" style="display: flex; padding: 4px; border-radius: 10px">
            @foreach(['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', 'all' => 'Tất cả'] as $key => $label)
                <a href="?status={{ $key }}" class="btn {{ $status === $key ? 'btn-primary' : 'btn-outline' }}" style="padding: 0.5rem 1rem; font-size: 0.875rem">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    @if(session('success'))
        <div class="glass" style="padding: 1rem; margin-bottom: 1.5rem; border-color: var(--accent); color: var(--accent)">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="glass" style="padding: 1rem; margin-bottom: 1.5rem; border-color: #ef4444; color: #ef4444">
            {{ session('error') }}
        </div>
    @endif

    <div class="glass" style="overflow: hidden">
        <table style="width: 100%; border-collapse: collapse; text-align: left">
            <thead>
                <tr style="border-bottom: 1px solid var(--glass-border); color: var(--text-muted); font-size: 0.875rem">
                    <th style="padding: 1.25rem">User</th>
                    <th style="padding: 1.25rem">Loại bài</th>
                    <th style="padding: 1.25rem">Số lượng xin</th>
                    <th style="padding: 1.25rem">Lý do</th>
                    <th style="padding: 1.25rem">Trạng thái</th>
                    <th style="padding: 1.25rem; text-align: right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $req)
                    <tr style="border-bottom: 1px solid var(--glass-border)">
                        <td style="padding: 1.25rem; font-weight: 500">
                            {{ $req->user->name ?? 'N/A' }}
                            <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 400">{{ $req->user->email ?? '' }}</div>
                        </td>
                        <td style="padding: 1.25rem">
                            <span class="badge" style="background: rgba(99, 102, 241, 0.1); color: var(--primary)">
                                {{ match($req->lesson_type) {
                                    'course' => 'Course',
                                    'classroom' => 'Classroom',
                                    'daily_lesson' => 'Daily Lesson',
                                    default => $req->lesson_type,
                                } }}
                            </span>
                        </td>
                        <td style="padding: 1.25rem">+{{ $req->requested_extra }}</td>
                        <td style="padding: 1.25rem; color: var(--text-muted); max-width: 240px">{{ $req->reason ?: '—' }}</td>
                        <td style="padding: 1.25rem">
                            @if($req->status === 'pending')
                                <span class="badge badge-pending">Chờ duyệt</span>
                            @elseif($req->status === 'approved')
                                <span class="badge badge-active">Đã duyệt</span>
                            @else
                                <span class="badge" style="background: rgba(239,68,68,0.1); color: #ef4444">Từ chối</span>
                            @endif
                        </td>
                        <td style="padding: 1.25rem; text-align: right">
                            @if($req->isPending())
                                <details style="display: inline-block">
                                    <summary class="btn btn-primary" style="padding: 0.4rem 1rem; font-size: 0.75rem; cursor: pointer; list-style: none; display: inline-block">Duyệt</summary>
                                    <form method="POST" action="{{ route('admin.lesson-requests.review', $req) }}" style="margin-top: 0.5rem; min-width: 280px; padding: 1rem; background: var(--bg-secondary, rgba(0,0,0,0.2)); border-radius: 8px; text-align: left">
                                        @csrf
                                        <input type="hidden" name="decision" value="approve">
                                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem">
                                            <input type="checkbox" name="grant_unlimited" value="1" style="margin-right: 0.4rem">
                                            Cấp quyền tạo không giới hạn
                                        </label>
                                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem">
                                            Số bài cộng thêm (nếu không unlimited):
                                            <input type="number" name="approved_extra" min="1" max="50" value="{{ $req->requested_extra }}" style="width: 100%; margin-top: 0.25rem; padding: 0.4rem; border-radius: 6px; border: 1px solid var(--glass-border); background: transparent; color: inherit">
                                        </label>
                                        <label style="display: block; margin-bottom: 0.75rem; font-size: 0.875rem">
                                            Ghi chú:
                                            <textarea name="admin_note" rows="2" style="width: 100%; margin-top: 0.25rem; padding: 0.4rem; border-radius: 6px; border: 1px solid var(--glass-border); background: transparent; color: inherit"></textarea>
                                        </label>
                                        <button type="submit" class="btn btn-primary" style="padding: 0.4rem 1rem; font-size: 0.75rem; width: 100%">Xác nhận duyệt</button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('admin.lesson-requests.review', $req) }}" style="display: inline-block; margin-left: 0.4rem">
                                    @csrf
                                    <input type="hidden" name="decision" value="reject">
                                    <button type="submit" class="btn btn-outline" style="padding: 0.4rem 1rem; font-size: 0.75rem; border-color: #ef4444; color: #ef4444">Từ chối</button>
                                </form>
                            @else
                                <div style="color: var(--text-muted); font-size: 0.75rem">
                                    {{ $req->reviewed_at?->diffForHumans() ?? '' }}
                                    @if($req->admin_note)
                                        <div title="{{ $req->admin_note }}">📝 có ghi chú</div>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 2.5rem; text-align: center; color: var(--text-muted)">
                            Không có yêu cầu nào.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.5rem">
        {{ $requests->links() }}
    </div>
</x-app-layout>