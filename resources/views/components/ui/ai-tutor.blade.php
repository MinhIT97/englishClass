{{--
    Floating AI Tutor widget — slides in from bottom-right.
    - Sends user questions to /ai/tutor, renders streamed responses.
    - Persists the last conversation in cache (cleared via Clear button).
    - Includes accessibility: aria-label, live region, keyboard shortcut
      (Cmd/Ctrl + J to toggle).
--}}
@auth
<div id="ai-tutor-root" data-open="false" aria-hidden="true"
     style="position:fixed;bottom:1rem;right:1rem;z-index:9000;font-family:inherit">

    <button type="button" id="ai-tutor-toggle" aria-label="Open AI Tutor" aria-expanded="false"
            style="width:56px;height:56px;border-radius:50%;background:var(--primary);color:white;border:none;box-shadow:var(--shadow-hover);cursor:pointer;font-size:1.5rem;display:flex;align-items:center;justify-content:center">
        <span aria-hidden="true">🤖</span>
    </button>

    <div id="ai-tutor-panel" role="dialog" aria-label="AI Tutor"
         style="display:none;position:absolute;bottom:72px;right:0;width:min(380px,calc(100vw - 2rem));height:480px;background:var(--bg-elevated);border:1px solid var(--glass-border);border-radius:14px;box-shadow:var(--shadow-hover);display:none;flex-direction:column;overflow:hidden">

        <header style="padding:0.75rem 1rem;background:var(--primary);color:white;display:flex;justify-content:space-between;align-items:center">
            <strong style="display:flex;gap:0.5rem;align-items:center"><span aria-hidden="true">🤖</span> AI Tutor</strong>
            <div style="display:flex;gap:0.5rem">
                <button type="button" data-ai-tutor-clear aria-label="Clear conversation"
                        style="background:transparent;border:1px solid rgba(255,255,255,0.4);color:white;border-radius:6px;padding:0.2rem 0.5rem;cursor:pointer;font-size:0.75rem">Clear</button>
                <button type="button" data-ai-tutor-close aria-label="Close"
                        style="background:transparent;border:none;color:white;cursor:pointer;font-size:1.25rem;line-height:1">&times;</button>
            </div>
        </header>

        <div id="ai-tutor-log" role="log" aria-live="polite"
             style="flex:1;overflow-y:auto;padding:1rem;display:flex;flex-direction:column;gap:0.5rem;background:var(--bg)">
            <div class="ai-msg ai-msg-bot" style="align-self:flex-start;max-width:85%;padding:0.5rem 0.75rem;background:var(--bg-secondary);border-radius:10px;font-size:0.875rem">
                Xin chào! Mình là AI Tutor. Bạn có thể hỏi về từ vựng, ngữ pháp, hoặc chiến lược làm bài nhé.
            </div>
        </div>

        <form id="ai-tutor-form" style="padding:0.5rem;border-top:1px solid var(--glass-border);display:flex;gap:0.5rem;background:var(--bg-elevated)">
            @csrf
            <input type="text" id="ai-tutor-input" placeholder="Hỏi AI Tutor..." aria-label="Ask AI Tutor"
                   required maxlength="1000"
                   style="flex:1;padding:0.5rem 0.75rem;border:1px solid var(--glass-border);border-radius:8px;background:var(--bg);color:var(--text-main);font-size:0.875rem">
            <button type="submit" style="padding:0.5rem 0.75rem;background:var(--primary);color:white;border:none;border-radius:8px;cursor:pointer">Gửi</button>
        </form>
    </div>
</div>

@once
@push('scripts')
    <script>
        (function () {
            const root = document.getElementById('ai-tutor-root');
            if (!root) return;
            const toggle = document.getElementById('ai-tutor-toggle');
            const panel = document.getElementById('ai-tutor-panel');
            const log = document.getElementById('ai-tutor-log');
            const form = document.getElementById('ai-tutor-form');
            const input = document.getElementById('ai-tutor-input');
            const csrf = form.querySelector('[name="_token"]').value;

            function openPanel() {
                panel.style.display = 'flex';
                root.setAttribute('data-open', 'true');
                root.setAttribute('aria-hidden', 'false');
                toggle.setAttribute('aria-expanded', 'true');
                input.focus();
            }
            function closePanel() {
                panel.style.display = 'none';
                root.setAttribute('data-open', 'false');
                root.setAttribute('aria-hidden', 'true');
                toggle.setAttribute('aria-expanded', 'false');
            }
            toggle.addEventListener('click', () => {
                root.getAttribute('data-open') === 'true' ? closePanel() : openPanel();
            });
            root.querySelector('[data-ai-tutor-close]').addEventListener('click', closePanel);
            root.querySelector('[data-ai-tutor-clear]').addEventListener('click', async () => {
                await fetch('/ai/tutor/clear', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                log.innerHTML = '<div class="ai-msg ai-msg-bot" style="align-self:flex-start;max-width:85%;padding:0.5rem 0.75rem;background:var(--bg-secondary);border-radius:10px;font-size:0.875rem">Đã xoá lịch sử hội thoại.</div>';
            });

            // Cmd/Ctrl+J toggles
            document.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'j') {
                    e.preventDefault();
                    root.getAttribute('data-open') === 'true' ? closePanel() : openPanel();
                }
            });

            function appendMsg(text, who) {
                const el = document.createElement('div');
                el.style.cssText = who === 'user'
                    ? 'align-self:flex-end;max-width:85%;padding:0.5rem 0.75rem;background:var(--primary);color:white;border-radius:10px;font-size:0.875rem;white-space:pre-wrap'
                    : 'align-self:flex-start;max-width:85%;padding:0.5rem 0.75rem;background:var(--bg-secondary);border-radius:10px;font-size:0.875rem;white-space:pre-wrap';
                el.textContent = text;
                log.appendChild(el);
                log.scrollTop = log.scrollHeight;
                return el;
            }

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const q = input.value.trim();
                if (!q) return;
                appendMsg(q, 'user');
                input.value = '';
                const placeholder = appendMsg('Đang suy nghĩ...', 'bot');

                try {
                    const res = await fetch('/ai/tutor', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ question: q }),
                    });
                    const data = await res.json();
                    placeholder.textContent = data.answer || 'Không nhận được phản hồi.';
                } catch (err) {
                    placeholder.textContent = 'Lỗi kết nối. Vui lòng thử lại.';
                }
                log.scrollTop = log.scrollHeight;
            });
        })();
    </script>
@endpush
@endonce
@endauth