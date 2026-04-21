import './echo';

// Debug Logging Helper
const log = (msg, data = null) => {
    const timestamp = new Date().toLocaleTimeString();
    if (data) {
        console.log(`[Reverb Debug ${timestamp}] ${msg}`, data);
    } else {
        console.log(`[Reverb Debug ${timestamp}] ${msg}`);
    }
};

// ===== @Mention Autocomplete System =====
const mentionStyles = `
.mention-dropdown {
    position: absolute;
    background: #1e1e2e;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
    z-index: 9999;
    min-width: 220px;
    max-height: 220px;
    overflow-y: auto;
    padding: 0.4rem;
    backdrop-filter: blur(20px);
    animation: mentionFadeIn 0.15s ease;
}
@keyframes mentionFadeIn {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.mention-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.12s;
    font-size: 0.85rem;
}
.mention-item:hover, .mention-item.active {
    background: rgba(99,102,241,0.2);
}
.mention-avatar {
    width: 28px; height: 28px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; font-weight: 700; color: white;
    flex-shrink: 0;
}
.mention-name { font-weight: 600; color: white; }
.mention-role { font-size: 0.7rem; color: rgba(255,255,255,0.4); margin-left: auto; }
`;

class MentionAutocomplete {
    constructor() {
        this.dropdown = null;
        this.members = window.classroomMembers || [];
        this.query = '';
        this.activeInput = null;
        this.activeIndex = -1;
        this.mentionStart = -1;

        // Inject CSS
        const style = document.createElement('style');
        style.textContent = mentionStyles;
        document.head.appendChild(style);

        this.bind();
    }

    bind() {
        document.addEventListener('input', (e) => this.onInput(e));
        document.addEventListener('keydown', (e) => this.onKeyDown(e));
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.mention-dropdown')) this.hide();
        });
    }

    onInput(e) {
        const input = e.target;
        if (input.tagName !== 'INPUT' && input.tagName !== 'TEXTAREA') return;

        const val = input.value;
        const caret = input.selectionStart;

        // Find the '@' before the caret
        let atIdx = -1;
        for (let i = caret - 1; i >= 0; i--) {
            if (val[i] === '@') { atIdx = i; break; }
            if (val[i] === ' ' || val[i] === '\n') break;
        }

        if (atIdx === -1) { this.hide(); return; }

        this.query = val.slice(atIdx + 1, caret);
        this.mentionStart = atIdx;
        this.activeInput = input;

        const filtered = this.members.filter(m =>
            m.name.toLowerCase().includes(this.query.toLowerCase())
        );

        if (filtered.length === 0) { this.hide(); return; }
        this.show(filtered, input);
    }

    onKeyDown(e) {
        if (!this.dropdown) return;
        const items = this.dropdown.querySelectorAll('.mention-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.activeIndex = Math.min(this.activeIndex + 1, items.length - 1);
            this.updateActive(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.activeIndex = Math.max(this.activeIndex - 1, 0);
            this.updateActive(items);
        } else if (e.key === 'Enter' || e.key === 'Tab') {
            if (this.activeIndex >= 0 && items[this.activeIndex]) {
                e.preventDefault();
                this.select(items[this.activeIndex].dataset.name);
            }
        } else if (e.key === 'Escape') {
            this.hide();
        }
    }

    updateActive(items) {
        items.forEach((el, i) => el.classList.toggle('active', i === this.activeIndex));
        items[this.activeIndex]?.scrollIntoView({ block: 'nearest' });
    }

    show(members, input) {
        this.hide();
        this.activeIndex = 0;

        this.dropdown = document.createElement('div');
        this.dropdown.className = 'mention-dropdown';

        members.forEach((m, i) => {
            const item = document.createElement('div');
            item.className = 'mention-item' + (i === 0 ? ' active' : '');
            item.dataset.name = m.name;
            const bgColor = m.role === 'teacher' ? '#7c3aed' : '#6366f1';
            item.innerHTML = `
                <div class="mention-avatar" style="background: ${bgColor}">${m.initial}</div>
                <span class="mention-name">${m.name}</span>
                <span class="mention-role">${m.role}</span>
            `;
            item.addEventListener('mousedown', (e) => {
                e.preventDefault();
                this.select(m.name);
            });
            this.dropdown.appendChild(item);
        });

        // Position dropdown above the input
        const rect = input.getBoundingClientRect();
        this.dropdown.style.position = 'fixed';
        this.dropdown.style.left = rect.left + 'px';
        this.dropdown.style.top = (rect.top - 10) + 'px';
        this.dropdown.style.transform = 'translateY(-100%)';
        this.dropdown.style.width = Math.max(rect.width, 240) + 'px';

        document.body.appendChild(this.dropdown);
    }

    select(name) {
        if (!this.activeInput) return;
        const val = this.activeInput.value;
        const before = val.slice(0, this.mentionStart);
        const after = val.slice(this.activeInput.selectionStart);
        this.activeInput.value = before + '@' + name + ' ' + after;
        const newCaret = before.length + name.length + 2;
        this.activeInput.setSelectionRange(newCaret, newCaret);
        this.activeInput.focus();
        this.hide();
    }

    hide() {
        this.dropdown?.remove();
        this.dropdown = null;
        this.activeIndex = -1;
    }
}


// Global listener for notifications
if (window.Echo) {
    log('Echo initialized.');
    
    const userId = document.querySelector('meta[name="user-id"]')?.content;
    const classroomId = document.querySelector('meta[name="classroom-id"]')?.content;
    
    log('Context detected:', { userId, classroomId });

    // Global User Notifications
    if (userId) {
        log(`Subscribing to private user channel: App.Models.User.${userId}`);
        window.Echo.private(`App.Models.User.${userId}`)
            .notification((notification) => {
                log('Private notification received:', notification);
                showNotification(toastFromData(notification));
                incrementBadgeCount();
                fetchUnreadCount();
            });
    }

    // Classroom-specific logic
    if (classroomId) {
        log(`Subscribing to private classroom channel: classroom.${classroomId}`);
        window.Echo.private(`classroom.${classroomId}`)
            .subscribed(() => {
                log(`Successfully subscribed to classroom.${classroomId}`);
            })
            .listen('.NewPostPublished', (e) => {
                log('NewPostPublished event received via Echo:', e);
                if (e.user_id && String(e.user_id) !== String(userId)) {
                    showNotification({
                        classroom_name: 'Classroom',
                        author_name: e.user_name,
                        type: 'posted a new ' + e.type
                    });
                    incrementBadgeCount();
                }
                handleNewPost(e);
            })
            .listen('.CommentPublished', (e) => {
                log('CommentPublished event received via Echo:', e);
                if (e.user_id && String(e.user_id) !== String(userId)) {
                    incrementBadgeCount();
                }
                handleNewComment(e);
            })
            .error((err) => {
                log('Echo subscription error (Check console Network for 403/Forbidden):', err);
                if (err.status === 403) {
                    console.error('[Reverb Debug] ACCESS DENIED: User is not authorized to listen to this classroom channel.');
                } else {
                    console.error('[Reverb Debug] Subscription Failed. Network or CSRF issue.', err);
                }
            });
    }
} else {
    console.error('[Reverb Debug] Echo not found on window object.');
}

function toastFromData(n) {
    return {
        classroom_name: n.title,
        author_name: '',
        type: n.message,
    };
}

// Notification UI Logic
document.addEventListener('DOMContentLoaded', () => {
    log('DOM Content Loaded. Initializing UI components.');
    
    // Initialize @mention autocomplete (works on any page with classroomMembers)
    if (window.classroomMembers) {
        new MentionAutocomplete();
        log('MentionAutocomplete initialized with', window.classroomMembers.length + ' members.');
    }
    
    const navBtn = document.getElementById('notification-btn');
    const dropdown = document.getElementById('notification-dropdown');
    const markReadBtn = document.getElementById('mark-read-btn');

    if (navBtn && dropdown) {
        navBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isActive = dropdown.classList.toggle('active');
            log('Notification dropdown toggled:', isActive);
            if (isActive) {
                fetchNotifications();
            }
        });

        // Close on click outside
        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && !navBtn.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
    }

    if (markReadBtn) {
        markReadBtn.addEventListener('click', markAllAsRead);
    }

    fetchUnreadCount();

    // --- AJAX Newsfeed Logic ---
    const postForm = document.getElementById('create-post-form');
    if (postForm) {
        log('Post form detected. Attaching AJAX listener.');
        postForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            log('Post form submitted via AJAX.');
            
            const formData = new FormData(postForm);
            const submitBtn = postForm.querySelector('button[type="submit"]');
            
            try {
                submitBtn.disabled = true;
                log('Sending POST request to:', postForm.action);
                
                const response = await fetch(postForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const data = await response.json();
                log('Post submission response:', data);
                
                if (data.success) {
                    log('Post submission success. Resetting form and calling handleNewPost.');
                    postForm.reset();
                    handleNewPost(data.post);
                } else {
                    log('Post submission failed server-side:', data);
                }
            } catch (error) {
                console.error('[Reverb Debug] Post submission network error:', error);
            } finally {
                submitBtn.disabled = false;
            }
        });
    }

    // Delegated listener for Reply buttons
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('reply-btn')) {
            const username = e.target.dataset.username;
            const postId = e.target.dataset.postId;
            log(`Reply button clicked. Tagging user: @${username} for post: ${postId}`);
            
            const form = document.querySelector(`#comment-form-${postId}`);
            if (form) {
                const input = form.querySelector('input[name="content"]');
                if (input) {
                    input.value = `@${username} `;
                    input.focus();
                    
                    // Scroll into view if needed
                    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        }
    });

    // Delegated listener for comment forms
    document.addEventListener('submit', async (e) => {
        if (e.target.classList.contains('comment-form')) {
            e.preventDefault();
            const form = e.target;
            log(`Comment form submitted for post ID: ${form.dataset.postId}`);
            
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button');
            
            try {
                submitBtn.disabled = true;
                log('Sending COMMENT request to:', form.action);
                
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const data = await response.json();
                log('Comment submission response:', data);
                
                if (data.success) {
                    log('Comment submission success. Resetting form and calling handleNewComment.');
                    form.reset();
                    handleNewComment({
                        post_id: form.dataset.postId,
                        ...data.comment
                    });
                }
            } catch (error) {
                console.error('[Reverb Debug] Comment submission network error:', error);
            } finally {
                submitBtn.disabled = false;
            }
        }
    });
});

async function fetchUnreadCount() {
    try {
        const res = await fetch('/notifications/unread-count');
        const data = await res.json();
        const badge = document.getElementById('notification-badge');
        if (badge) {
            badge.innerText = data.count;
            badge.style.display = data.count > 0 ? 'flex' : 'none';
        }
    } catch (e) {}
}

function incrementBadgeCount() {
    const badge = document.getElementById('notification-badge');
    if (badge) {
        let count = parseInt(badge.innerText) || 0;
        count++;
        badge.innerText = count;
        badge.style.display = 'flex';
        log('Optimistically incremented badge count to:', count);
    }
}

async function fetchNotifications() {
    const list = document.getElementById('notification-list');
    list.innerHTML = '<div style="padding: 2rem; text-align: center">Loading...</div>';
    
    try {
        const res = await fetch('/notifications');
        const data = await res.json();
        log('Fetched notifications:', data);
        
        if (data.length === 0) {
            list.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--text-muted)">No notifications</div>';
            return;
        }

        list.innerHTML = data.map(n => `
            <a href="${n.data.url}" class="notification-item ${n.read_at ? '' : 'unread'}">
                <div style="font-size: 1.5rem">📢</div>
                <div>
                    <div style="font-weight: 700; font-size: 0.85rem">${n.data.title}</div>
                    <div style="font-size: 0.8rem; opacity: 0.8">${n.data.message}</div>
                </div>
            </a>
        `).join('');
    } catch (e) {
        log('Failed to fetch notifications:', e);
    }
}

async function markAllAsRead() {
    log('Marking all notifications as read.');
    await fetch('/notifications/mark-as-read', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    });
    fetchUnreadCount();
    fetchNotifications();
}

function handleNewPost(data) {
    log('handleNewPost called with data:', data);
    const list = document.getElementById('posts-list');
    if (!list) {
        log('Error: #posts-list container not found.');
        return;
    }

    if (document.getElementById(`post-${data.id}`)) {
        log(`Post ID ${data.id} already exists in DOM. Skipping injection.`);
        return;
    }

    log(`Injecting new post HTML into DOM for post ID: ${data.id}`);

    const attachmentHtml = data.attachment_url ? `
        <div style="margin-bottom: 1.5rem">
            ${(data.type === 'video' || data.type === 'pronunciation') ? `
                <video controls style="width: 100%; border-radius: 12px; background: #000; max-height: 400px">
                    <source src="${data.attachment_url}" type="video/mp4">
                </video>
            ` : `
                <a href="${data.attachment_url}" target="_blank" class="glass-card" style="display: flex; align-items: center; gap: 1rem; padding: 0.75rem 1rem; text-decoration: none; border: 1px dashed var(--glass-border)">
                    <div style="font-size: 1.5rem">📁</div>
                    <div style="flex: 1">
                        <div style="font-weight: 600; font-size: 0.85rem">Attachment</div>
                        <div style="font-size: 0.7rem; color: var(--text-muted)">Click to view or download</div>
                    </div>
                </a>
            `}
        </div>
    ` : '';

    const postHtml = `
        <div id="post-${data.id}" class="glass-card animate-fade-in" style="padding: 1.5rem">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem">
                <div style="display: flex; gap: 0.75rem; align-items: center">
                    <div style="width: 36px; height: 36px; border-radius: 50%; background: ${data.user_role === 'teacher' ? 'var(--accent)' : 'var(--primary)'}; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.8rem">
                        ${data.user_initial}
                    </div>
                    <div>
                        <div style="font-weight: 700; font-size: 0.95rem">${data.user_name}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted)">${data.created_at}</div>
                    </div>
                </div>
                <span class="badge" style="background: var(--bg-main); font-size: 0.7rem; text-transform: uppercase">${data.type}</span>
            </div>
            <div style="line-height: 1.6; white-space: pre-wrap; margin-bottom: 1rem">${data.content}</div>
            ${attachmentHtml}

            <!-- Comments Section -->
            <div style="margin-top: 1.5rem; border-top: 1px solid var(--glass-border); padding-top: 1rem">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; color: var(--text-muted); font-size: 0.85rem">
                    <span>💬</span>
                    <span id="comment-count-${data.id}">0 Comments</span>
                </div>
                <div id="comments-list-${data.id}"></div>
                <form id="comment-form-${data.id}" class="comment-form" action="/classroom/post/${data.id}/comment" method="POST" data-post-id="${data.id}" style="margin-top: 1.5rem; display: flex; gap: 0.5rem">
                    <input type="text" name="content" required placeholder="Write a comment..." style="flex: 1; background: var(--bg-main); border: 1px solid var(--glass-border); border-radius: 20px; padding: 0.5rem 1rem; font-size: 0.85rem">
                    <button type="submit" style="background: none; border: none; font-size: 1.25rem; cursor: pointer">🕊️</button>
                </form>
            </div>
        </div>
    `;

    const emptyState = list.querySelector('.glass-card p')?.innerText.includes('No posts yet');
    if (emptyState) list.innerHTML = '';

    list.insertAdjacentHTML('afterbegin', postHtml);
}

function handleNewComment(data) {
    log('handleNewComment called with data:', data);
    const list = document.querySelector(`#comments-list-${data.post_id}`);
    if (!list) {
        log(`Error: #comments-list-${data.post_id} not found.`);
        return;
    }

    if (document.getElementById(`comment-${data.id}`)) {
        log(`Comment ID ${data.id} already exists in DOM. Skipping injection.`);
        return;
    }

    const commentHtml = `
        <div id="comment-${data.id}" class="comment-item animate-fade-in" style="display: flex; gap: 0.75rem; margin-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1rem">
            <div style="width: 28px; height: 28px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.7rem">
                ${data.user_name.substring(0, 1)}
            </div>
            <div style="flex: 1">
                <div style="display: flex; justify-content: space-between; align-items: center">
                    <span style="font-weight: 700; font-size: 0.8rem">${data.user_name}</span>
                    <div style="display: flex; gap: 0.75rem; align-items: center">
                        <span style="font-size: 0.7rem; color: var(--text-muted)">${data.created_at}</span>
                        <button type="button" class="reply-btn" data-username="${data.user_name}" data-post-id="${data.post_id}" style="background: none; border: none; color: var(--primary); font-size: 0.7rem; font-weight: 700; cursor: pointer; padding: 0">Reply</button>
                    </div>
                </div>
                <div style="font-size: 0.85rem; margin-top: 0.25rem">${data.content}</div>
            </div>
        </div>
    `;
    list.insertAdjacentHTML('beforeend', commentHtml);

    const countEl = document.getElementById(`comment-count-${data.post_id}`);
    if (countEl) {
        const count = list.querySelectorAll('.comment-item').length;
        countEl.innerText = `${count} Comments`;
    }
}

function showNotification(data) {
    log('showNotification called:', data);
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-5 right-5 bg-white border-l-4 border-blue-500 shadow-xl p-4 rounded-lg z-[1000] transform transition-all duration-500 translate-y-10 opacity-0 flex flex-col gap-1 min-w-[300px] hover:scale-105 cursor-pointer';
    toast.innerHTML = `
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
            </div>
            <div>
                <p class="font-bold text-gray-800">${data.classroom_name}</p>
                <p class="text-sm text-gray-600">${data.author_name} ${data.type}.</p>
            </div>
        </div>
    `;

    document.body.appendChild(toast);
    setTimeout(() => toast.classList.remove('translate-y-10', 'opacity-0'), 100);
    setTimeout(() => {
        toast.classList.add('translate-y-10', 'opacity-0');
        setTimeout(() => toast.remove(), 500);
    }, 5000);
}
