<x-app-layout>
    <div class="container-fluid" style="padding: 2rem">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h3 font-heading mb-0">{{ __('ui.manage_feedback') }}</h2>
            <div class="text-muted small">{{ __('ui.all_feedback_desc') }}</div>
        </div>

        <div class="glass-card" style="padding: 0; overflow: hidden; border: 1px solid var(--glass-border);">
            <div class="table-responsive" style="width: 100%;">
                <table class="table" style="width: 100%; margin-bottom: 0; color: var(--text-main); border-collapse: collapse;">
                    <thead>
                        <tr style="background: rgba(255, 255, 255, 0.05); border-bottom: 1px solid var(--glass-border);">
                            <th style="padding: 1.25rem; border: none; text-align: left; width: 20%;">{{ __('ui.fb_user') }}</th>
                            <th style="padding: 1.25rem; border: none; text-align: left; width: 10%;">{{ __('ui.fb_type') }}</th>
                            <th style="padding: 1.25rem; border: none; text-align: left; width: 10%;">{{ __('ui.fb_rating') }}</th>
                            <th style="padding: 1.25rem; border: none; text-align: left; width: 20%;">{{ __('ui.fb_message') }}</th>
                            <th style="padding: 1.25rem; border: none; text-align: left; width: 15%;">Người xử lý</th>
                            <th style="padding: 1.25rem; border: none; text-align: left; width: 15%;">{{ __('ui.fb_status') }}</th>
                            <th style="padding: 1.25rem; border: none; text-align: right; width: 10%;">{{ __('ui.fb_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($feedbacks as $feedback)
                            <tr style="border-bottom: 1px solid var(--glass-border); transition: background 0.2s ease;">
                                <td style="padding: 1.25rem; vertical-align: middle;">
                                    <div class="d-flex align-items-center">
                                        <div style="width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--accent)); display: flex; align-items: center; justify-content: center; margin-right: 1rem; font-size: 0.9rem; font-weight: bold; color: white; flex-shrink: 0;">
                                            {{ strtoupper(substr($feedback->user->name, 0, 1)) }}
                                        </div>
                                        <div style="min-width: 0;">
                                            <div style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $feedback->user->name }}</div>
                                            <div class="text-muted small" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $feedback->email ?? $feedback->user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 1.25rem; vertical-align: middle;">
                                    <span class="badge" style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--glass-border); color: var(--text-muted); font-weight: 500; text-transform: capitalize; padding: 0.4rem 0.8rem; border-radius: 8px;">
                                        {{ __('ui.cat_' . $feedback->type) }}
                                    </span>
                                </td>
                                <td style="padding: 1.25rem; vertical-align: middle;">
                                    <div style="color: #f59e0b; font-size: 1rem;">
                                        @for($i = 1; $i <= 5; $i++)
                                            @if($i <= $feedback->rating)
                                                ★
                                            @else
                                                <span style="opacity: 0.3">☆</span>
                                            @endif
                                        @endfor
                                    </div>
                                </td>
                                <td style="padding: 1.25rem; vertical-align: middle;">
                                    <div style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;" title="{{ $feedback->message }}">
                                        {{ $feedback->message }}
                                    </div>
                                    <div class="text-muted small mt-1" style="font-size: 0.75rem;">{{ $feedback->created_at->diffForHumans() }}</div>
                                </td>
                                <td style="padding: 1.25rem; vertical-align: middle;">
                                    <form action="{{ route('admin.feedback.assign', $feedback) }}" method="POST">
                                        @csrf
                                        <select name="user_id" onchange="this.form.submit()" style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--glass-border); border-radius: 8px; color: white; font-size: 0.8rem; padding: 6px 12px; width: 100%;">
                                            <option value="">Chưa gán</option>
                                            @foreach($admins as $admin)
                                                <option value="{{ $admin->id }}" {{ $feedback->assigned_to == $admin->id ? 'selected' : '' }}>
                                                    {{ $admin->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td style="padding: 1.25rem; vertical-align: middle; position: relative;">
                                    <div class="status-container" style="position: relative; display: inline-block;">
                                        <select 
                                            class="status-select" 
                                            data-id="{{ $feedback->id }}" 
                                            data-url="{{ route('admin.feedback.updateStatus', $feedback) }}"
                                            style="background: rgba(0, 0, 0, 0.3); border: 1px solid var(--glass-border); border-radius: 8px; color: white; font-size: 0.8rem; padding: 6px 12px; cursor: pointer; outline: none; transition: all 0.3s ease;"
                                        >
                                            <option value="pending" {{ $feedback->status == 'pending' ? 'selected' : '' }}>{{ __('ui.fb_pending') }}</option>
                                            <option value="reviewed" {{ $feedback->status == 'reviewed' ? 'selected' : '' }}>{{ __('ui.fb_reviewed') }}</option>
                                            <option value="resolved" {{ $feedback->status == 'resolved' ? 'selected' : '' }}>{{ __('ui.fb_resolved') }}</option>
                                        </select>
                                        <div class="spinner-overlay" style="display: none; position: absolute; inset: 0; background: rgba(0,0,0,0.5); border-radius: 8px; align-items: center; justify-content: center;">
                                            <div class="mini-spinner"></div>
                                        </div>
                                    </div>
                                </td>


                                <td style="padding: 1.25rem; vertical-align: middle; text-align: right;">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button onclick="openHistoryModal({{ $feedback->id }})" style="background: rgba(255, 255, 255, 0.1); border: 1px solid var(--glass-border); color: white; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center;" title="Lịch sử xử lý">
                                            📜
                                        </button>
                                        <form action="{{ route('admin.feedback.destroy', $feedback) }}" method="POST" onsubmit="return confirm('{{ __('ui.fb_delete_confirm') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center;">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <!-- History Modal (Hidden by default) -->
                            <div id="history-modal-{{ $feedback->id }}" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); z-index: 10000; align-items: center; justify-content: center; padding: 2rem;">
                                <div class="glass-card" style="width: 100%; max-width: 600px; max-height: 80vh; overflow-y: auto; position: relative;">
                                    <button onclick="closeHistoryModal({{ $feedback->id }})" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
                                    
                                    <h3 class="mb-4">Lịch sử xử lý & Ghi chú</h3>
                                    
                                    <!-- Add Note Form -->
                                    <form action="{{ route('admin.feedback.addNote', $feedback) }}" method="POST" class="mb-4">
                                        @csrf
                                        <label class="small text-muted mb-1 d-block">Thêm ghi chú mới</label>
                                        <textarea name="note" rows="3" class="form-control" style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white; border-radius: 12px; padding: 0.75rem; width: 100%;" placeholder="Nhập ghi chú xử lý..."></textarea>
                                        <button type="submit" class="btn btn-primary mt-2" style="background: var(--primary); border: none; padding: 0.5rem 1rem; border-radius: 8px; color: white; cursor: pointer;">Lưu ghi chú</button>
                                    </form>

                                    <!-- History Timeline -->
                                    <div class="timeline">
                                        <h4 class="small font-weight-bold mb-3">Dòng thời gian</h4>
                                        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                            @forelse($feedback->logs as $log)
                                                <div style="display: flex; gap: 1rem;">
                                                    <div style="flex-shrink: 0; width: 2px; background: var(--glass-border); position: relative; margin-left: 0.5rem;">
                                                        <div style="position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                                                    </div>
                                                    <div>
                                                        <div class="small" style="font-weight: 600; color: var(--primary);">
                                                            @if($log->action == 'status_changed') Trạng thái thay đổi @elseif($log->action == 'assigned') Đã gán người xử lý @else Ghi chú mới @endif
                                                        </div>
                                                        <div style="font-size: 0.9rem; margin-top: 0.25rem;">{{ $log->content }}</div>
                                                        <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                                                            Bởi {{ $log->user->name }} • {{ $log->created_at->diffForHumans() }}
                                                        </div>
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="text-muted small">Chưa có lịch sử xử lý.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="7" style="padding: 5rem; text-align: center; color: var(--text-muted);">
                                    <div style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.5;">📭</div>
                                    <div style="font-size: 1.1rem;">{{ __('ui.fb_no_data') }}</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>
        </div>
        
        <div class="mt-4 d-flex justify-content-center">
            {{ $feedbacks->links() }}
        </div>
    </div>

    <div id="toast-container" style="position: fixed; top: 2rem; right: 2rem; z-index: 9999; display: flex; flex-direction: column; gap: 1rem;"></div>

    <script>
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            
            const bgColor = type === 'success' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)';
            const borderColor = type === 'success' ? '#10b981' : '#ef4444';
            const icon = type === 'success' ? '✅' : '❌';

            toast.style.cssText = `
                background: ${bgColor};
                backdrop-filter: blur(10px);
                border: 1px solid ${borderColor};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 12px;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
                animation: slideIn 0.3s ease-out forwards;
                min-width: 300px;
            `;

            toast.innerHTML = `<span>${icon}</span> <span style="font-weight: 500;">${message}</span>`;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-in forwards';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            .mini-spinner {
                width: 16px;
                height: 16px;
                border: 2px solid rgba(255, 255, 255, 0.3);
                border-top-color: white;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);


        function openHistoryModal(id) {
            const modal = document.getElementById(`history-modal-${id}`);
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeHistoryModal(id) {
            const modal = document.getElementById(`history-modal-${id}`);
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close on overlay click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        };

        document.addEventListener('DOMContentLoaded', function() {

            const selects = document.querySelectorAll('.status-select');
            
            selects.forEach(select => {
                select.addEventListener('change', async function() {
                    const id = this.dataset.id;
                    const url = this.dataset.url;
                    const status = this.value;
                    const container = this.closest('.status-container');
                    const loader = container.querySelector('.spinner-overlay');
                    
                    // Visual feedback
                    loader.style.display = 'flex';
                    this.disabled = true;

                    try {

                        const response = await fetch(url, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ status: status })
                        });

                        const result = await response.json();

                        if (result.success) {
                            showToast(result.message, 'success');
                            this.style.borderColor = '#10b981';
                            setTimeout(() => {
                                this.style.borderColor = 'var(--glass-border)';
                            }, 1000);
                        } else {
                            showToast('Failed to update status', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showToast('An error occurred', 'error');
                    } finally {
                        loader.style.display = 'none';
                        this.disabled = false;
                    }
                });
            });
        });

    </script>
</x-app-layout>



