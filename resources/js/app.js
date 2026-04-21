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
                showNotification({
                    classroom_name: 'Classroom',
                    author_name: e.user_name,
                    type: 'posted a new ' + e.type
                });
                handleNewPost(e);
            })
            .listen('.CommentPublished', (e) => {
                log('CommentPublished event received via Echo:', e);
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
                <div style="display: flex; justify-content: space-between">
                    <span style="font-weight: 700; font-size: 0.8rem">${data.user_name}</span>
                    <span style="font-size: 0.7rem; color: var(--text-muted)">${data.created_at}</span>
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
