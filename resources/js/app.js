import './echo';

// Global listener for notifications
if (window.Echo) {
    const userId = document.querySelector('meta[name="user-id"]')?.content;
    const classroomId = document.querySelector('meta[name="classroom-id"]')?.content;
    
    // Global User Notifications
    if (userId) {
        window.Echo.private(`App.Models.User.${userId}`)
            .notification((notification) => {
                showNotification(toastFromData(notification));
                fetchUnreadCount();
            });
    }

    // Classroom-specific logic
    if (classroomId) {
        window.Echo.private(`classroom.${classroomId}`)
            .listen('NewPostPublished', (e) => {
                showNotification(e);
            })
            .listen('CommentPublished', (e) => {
                handleNewComment(e);
            });
    }
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
    const navBtn = document.getElementById('notification-btn');
    const dropdown = document.getElementById('notification-dropdown');
    const markReadBtn = document.getElementById('mark-read-btn');

    console.log('Notification UI Init:', { navBtn, dropdown });

    if (navBtn && dropdown) {
        navBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isActive = dropdown.classList.toggle('active');
            console.log('Dropdown toggle:', isActive);
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
});

async function fetchUnreadCount() {
    const res = await fetch('/notifications/unread-count');
    const data = await res.json();
    const badge = document.getElementById('notification-badge');
    if (badge) {
        badge.innerText = data.count;
        badge.style.display = data.count > 0 ? 'flex' : 'none';
    }
}

async function fetchNotifications() {
    const list = document.getElementById('notification-list');
    list.innerHTML = '<div style="padding: 2rem; text-align: center">Loading...</div>';
    
    const res = await fetch('/notifications');
    const data = await res.json();
    
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
}

async function markAllAsRead() {
    await fetch('/notifications/mark-as-read', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    });
    fetchUnreadCount();
    fetchNotifications();
}

function handleNewComment(data) {
    const postCommentsList = document.querySelector(`#comments-list-${data.post_id}`);
    if (postCommentsList) {
        const commentHtml = `
            <div class="comment-item" style="display: flex; gap: 0.75rem; margin-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1rem">
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
        postCommentsList.insertAdjacentHTML('beforeend', commentHtml);
    }
}

function showNotification(data) {
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
    
    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-y-10', 'opacity-0');
    }, 100);

    // Click to reload (simple update)
    // toast.onclick = () => window.location.reload();

    // Remove after 5 seconds
    setTimeout(() => {
        toast.classList.add('translate-y-10', 'opacity-0');
        setTimeout(() => toast.remove(), 500);
    }, 5000);
}
