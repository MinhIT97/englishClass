<x-app-layout>
<style>
    /* ===== Facebook-style Skeleton Loader ===== */
    @keyframes shimmer {
        0% { background-position: -1000px 0; }
        100% { background-position: 1000px 0; }
    }
    .skeleton {
        animation: shimmer 1.8s infinite linear;
        background: linear-gradient(to right, rgba(255,255,255,0.04) 4%, rgba(255,255,255,0.1) 25%, rgba(255,255,255,0.04) 36%);
        background-size: 1000px 100%;
        border-radius: 8px;
    }
    .skeleton-card {
        background: var(--bg-secondary);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        overflow: hidden;
    }
    .classroom-card {
        background: var(--bg-secondary);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        overflow: hidden;
        display: block;
        text-decoration: none;
        color: inherit;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        position: relative;
    }
    .classroom-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 60px rgba(99, 102, 241, 0.25);
        border-color: var(--primary);
    }
    .classroom-card:hover .card-enter-btn {
        opacity: 1;
        transform: translateX(0);
    }
    .card-enter-btn {
        opacity: 0;
        transform: translateX(-8px);
        transition: opacity 0.2s, transform 0.2s;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--glass-border);
        flex-wrap: wrap;
        gap: 1rem;
    }
    .stats-row {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    /* Responsive overrides for classroom */
    @media (max-width: 640px) {
        .page-header { flex-direction: column; align-items: flex-start; }
        .page-header button { width: 100%; }
        .modal-box { margin: 1rem; padding: 1.5rem; border-radius: 16px; }
    }
    .stat-pill {
        background: var(--bg-secondary);
        border: 1px solid var(--glass-border);
        border-radius: 50px;
        padding: 0.5rem 1.25rem;
        font-size: 0.8rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .stat-pill strong {
        color: var(--text-primary, white);
        font-weight: 700;
    }
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.6);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        animation: fadeIn 0.2s ease;
    }
    .modal-overlay.active { display: flex; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .modal-box {
        background: var(--bg-secondary);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        padding: 2.5rem;
        position: relative;
        width: 100%;
        max-width: 480px;
        animation: slideUp 0.25s ease;
        box-shadow: 0 40px 80px rgba(0,0,0,0.5);
    }
    .modal-close {
        position: absolute;
        top: 1.25rem;
        right: 1.25rem;
        background: rgba(255,255,255,0.07);
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--text-muted);
        font-size: 1rem;
        transition: background 0.2s;
    }
    .modal-close:hover { background: rgba(255,255,255,0.14); color: white; }
    .form-group { margin-bottom: 1.25rem; }
    .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }
    .form-input {
        width: 100%;
        background: var(--bg-main);
        border: 1px solid var(--glass-border);
        padding: 0.8rem 1rem;
        border-radius: 12px;
        color: inherit;
        font-size: 0.95rem;
        transition: border-color 0.2s;
        outline: none;
        box-sizing: border-box;
    }
    .form-input:focus { border-color: var(--primary); }
    .empty-state { grid-column: 1/-1; text-align: center; padding: 6rem 2rem; }
    .empty-state-icon {
        width: 80px; height: 80px;
        background: var(--bg-secondary);
        border: 1px solid var(--glass-border);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 2rem; margin: 0 auto 1.5rem;
    }
    /* gradient variants for cards */
    .grad-0 { background: linear-gradient(135deg, #6366f1 0%, #312e81 100%); }
    .grad-1 { background: linear-gradient(135deg, #8b5cf6 0%, #4c1d95 100%); }
    .grad-2 { background: linear-gradient(135deg, #06b6d4 0%, #1e3a5f 100%); }
    .grad-3 { background: linear-gradient(135deg, #10b981 0%, #065f46 100%); }
    .grad-4 { background: linear-gradient(135deg, #f59e0b 0%, #78350f 100%); }
    .grad-5 { background: linear-gradient(135deg, #ef4444 0%, #7f1d1d 100%); }
</style>

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 style="font-size: 1.6rem; font-weight: 800; margin: 0 0 0.3rem">My Classrooms</h1>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin: 0">Connect with your teachers and fellow students.</p>
        </div>
        @if(auth()->user()->role === 'student')
            <button id="open-join-btn" class="btn btn-primary" style="padding: 0.75rem 1.5rem; border-radius: 50px; display: flex; align-items: center; gap: 0.5rem">
                <span>🔑</span> Join Class
            </button>
        @else
            <button id="open-create-btn" class="btn btn-primary" style="padding: 0.75rem 1.5rem; border-radius: 50px; display: flex; align-items: center; gap: 0.5rem">
                <span style="font-size: 1.1rem">+</span> Create New Class
            </button>
        @endif
    </div>

    <!-- Stats Row -->
    <div class="stats-row">
        <div class="stat-pill">
            🏫 <strong>{{ $classrooms->count() }}</strong> Classroom{{ $classrooms->count() !== 1 ? 's' : '' }}
        </div>
        @php
            $totalStudents = $classrooms->sum(fn($c) => $c->students->count());
        @endphp
        <div class="stat-pill">
            👥 <strong>{{ $totalStudents }}</strong> Student{{ $totalStudents !== 1 ? 's' : '' }}
        </div>
    </div>

    <!-- Skeleton Loaders (shown briefly via JS) -->
    <div id="skeleton-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem">
        @for ($i = 0; $i < 3; $i++)
        <div class="skeleton-card">
            <div class="skeleton" style="height: 130px; width: 100%"></div>
            <div style="padding: 1.25rem 1.5rem">
                <div class="skeleton" style="height: 18px; width: 55%; margin-bottom: 0.75rem"></div>
                <div class="skeleton" style="height: 13px; width: 80%; margin-bottom: 0.4rem"></div>
                <div class="skeleton" style="height: 13px; width: 60%; margin-bottom: 1.25rem"></div>
                <div style="display: flex; justify-content: space-between">
                    <div class="skeleton" style="height: 28px; width: 28px; border-radius: 50%"></div>
                    <div class="skeleton" style="height: 13px; width: 25%"></div>
                </div>
            </div>
        </div>
        @endfor
    </div>

    <!-- Actual Classroom Grid (hidden initially) -->
    <div id="classroom-grid" style="display: none; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem">
        @forelse($classrooms as $index => $classroom)
            @php
                $totalPosts = $classroom->posts->count();
                $gradClass = 'grad-' . ($index % 6);
            @endphp
            <a href="{{ route('classroom.show', $classroom->id) }}" class="classroom-card">
                <!-- Card Banner -->
                <div class="{{ $gradClass }}" style="height: 130px; position: relative; display: flex; align-items: flex-end; padding: 1rem 1.25rem">
                    <!-- Invite Code Badge -->
                    <span style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.18); color: white; padding: 0.25rem 0.7rem; border-radius: 50px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.05em; backdrop-filter: blur(10px)">
                        {{ $classroom->invite_code }}
                    </span>
                    <!-- Classroom Initial Icon -->
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800; color: white; backdrop-filter: blur(10px); border: 2px solid rgba(255,255,255,0.3)">
                        {{ strtoupper(substr($classroom->name, 0, 1)) }}
                    </div>
                </div>
                <!-- Card Body -->
                <div style="padding: 1.25rem 1.5rem">
                    <h3 style="margin: 0 0 0.4rem; font-size: 1rem; font-weight: 700">{{ $classroom->name }}</h3>
                    <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; margin: 0 0 1.25rem; min-height: 2.4em; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical">
                        {{ $classroom->description && $classroom->description !== $classroom->name ? Str::limit($classroom->description, 100) : 'No description yet.' }}
                    </p>
                    <div style="display: flex; justify-content: space-between; align-items: center">
                        <!-- Teacher Info -->
                        <div style="display: flex; align-items: center; gap: 0.5rem">
                            <div style="width: 26px; height: 26px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700">
                                {{ strtoupper(substr($classroom->teacher->name, 0, 1)) }}
                            </div>
                            <span style="font-size: 0.75rem; color: var(--text-muted)">{{ $classroom->teacher->name }}</span>
                        </div>
                        <!-- Meta + Enter -->
                        <div style="display: flex; align-items: center; gap: 1rem">
                            <span style="font-size: 0.72rem; color: var(--text-muted)">
                                👥 {{ $classroom->students->count() }}
                                &nbsp;·&nbsp;
                                📝 {{ $totalPosts }}
                            </span>
                            <span class="card-enter-btn" style="font-size: 0.75rem; color: var(--primary); font-weight: 700">Enter ➜</span>
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <div class="empty-state">
                <div class="empty-state-icon">🏫</div>
                <h3 style="margin: 0 0 0.75rem">No classes yet</h3>
                <p style="color: var(--text-muted); margin: 0 0 2rem; font-size: 0.875rem">
                    @if(auth()->user()->role === 'student')
                        Ask your teacher for an invite code to get started.
                    @else
                        Create your first classroom and invite students.
                    @endif
                </p>
                @if(auth()->user()->role === 'student')
                    <button id="open-join-btn-empty" class="btn btn-primary" style="padding: 0.75rem 2rem; border-radius: 50px">🔑 Join a Class</button>
                @else
                    <button id="open-create-btn-empty" class="btn btn-primary" style="padding: 0.75rem 2rem; border-radius: 50px">+ Create Classroom</button>
                @endif
            </div>
        @endforelse
    </div>

    <!-- ===== CREATE MODAL ===== -->
    <div id="create-modal" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close close-modal-btn">✕</button>
            <h2 style="margin: 0 0 0.5rem; font-size: 1.3rem">Create Classroom</h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0 0 1.75rem">Set up a new learning space for your students.</p>
            <form action="{{ route('classroom.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label">Class Name *</label>
                    <input type="text" name="name" required placeholder="e.g. IELTS Writing Intensive" class="form-input">
                </div>
                <div class="form-group" style="margin-bottom: 2rem">
                    <label class="form-label">Description (Optional)</label>
                    <textarea name="description" rows="3" placeholder="Describe what students will learn..." class="form-input" style="resize: none"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.9rem; border-radius: 12px; font-size: 0.95rem">Create Classroom ✨</button>
            </form>
        </div>
    </div>

    <!-- ===== JOIN MODAL ===== -->
    <div id="join-modal" class="modal-overlay">
        <div class="modal-box" style="max-width: 400px; text-align: center">
            <button class="modal-close close-modal-btn">✕</button>
            <div style="font-size: 2.5rem; margin-bottom: 1rem">🔑</div>
            <h2 style="margin: 0 0 0.5rem; font-size: 1.3rem">Join a Classroom</h2>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin: 0 0 2rem">Enter the 6-character invite code provided by your teacher.</p>
            <form action="{{ route('classroom.join') }}" method="POST">
                @csrf
                <input type="text" name="invite_code" required maxlength="6"
                    placeholder="XXXXXX"
                    style="width: 100%; background: var(--bg-main); border: 2px solid var(--glass-border); padding: 1rem; border-radius: 16px; font-size: 2rem; text-align: center; letter-spacing: 8px; text-transform: uppercase; margin-bottom: 1.5rem; box-sizing: border-box; transition: border-color 0.2s; outline: none; color: inherit"
                    onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='var(--glass-border)'">
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.9rem; border-radius: 12px; font-size: 0.95rem">Join Now →</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // ===== Facebook-style Skeleton to Real Content =====
        const skeleton = document.getElementById('skeleton-grid');
        const grid = document.getElementById('classroom-grid');

        // Simulate a brief load then reveal content (mimics API fetch delay)
        setTimeout(() => {
            skeleton.style.opacity = '1';
            skeleton.style.transition = 'opacity 0.3s';
            skeleton.style.opacity = '0';
            setTimeout(() => {
                skeleton.style.display = 'none';
                grid.style.display = 'grid';
                grid.style.opacity = '0';
                grid.style.transition = 'opacity 0.35s';
                requestAnimationFrame(() => { grid.style.opacity = '1'; });
            }, 300);
        }, 600);

        // ===== Modal Logic =====
        const openModal = (id) => document.getElementById(id)?.classList.add('active');
        const closeModal = (id) => document.getElementById(id)?.classList.remove('active');

        // Open buttons
        document.getElementById('open-create-btn')?.addEventListener('click', () => openModal('create-modal'));
        document.getElementById('open-create-btn-empty')?.addEventListener('click', () => openModal('create-modal'));
        document.getElementById('open-join-btn')?.addEventListener('click', () => openModal('join-modal'));
        document.getElementById('open-join-btn-empty')?.addEventListener('click', () => openModal('join-modal'));

        // Close buttons inside modals
        document.querySelectorAll('.close-modal-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.closest('.modal-overlay')?.classList.remove('active');
            });
        });

        // Close when clicking backdrop
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) overlay.classList.remove('active');
            });
        });

        // Close on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
            }
        });

        // Auto-uppercase invite code input
        const inviteInput = document.querySelector('input[name="invite_code"]');
        if (inviteInput) {
            inviteInput.addEventListener('input', () => {
                inviteInput.value = inviteInput.value.toUpperCase();
            });
        }
    });
    </script>
</x-app-layout>
