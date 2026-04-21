<x-app-layout>
    <div style="max-width: 1000px; margin: 0 auto">
        <!-- Breadcrumbs -->
        <nav style="margin-bottom: 1.5rem; font-size: 0.875rem">
            <a href="{{ route('course.index') }}" style="color: var(--text-muted); text-decoration: none">📚 Courses</a>
            <span style="margin: 0 0.5rem; color: var(--text-muted)">/</span>
            <span style="color: white">{{ $course->title }}</span>
        </nav>

        <!-- Flash Messages -->
        @if(session('success'))
            <div style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #10b981; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem">
                ✓ {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem">
                {{ session('error') }}
            </div>
        @endif

        <!-- Responsive 2-column → 1-column on mobile -->
        <div class="course-show-grid">
            <!-- Left: Content -->
            <div>
                <div class="glass-card" style="padding: 0; overflow: hidden; margin-bottom: 1.5rem">
                    <div style="height: 220px; background: linear-gradient(135deg, var(--primary) 0%, #312e81 100%); display: flex; align-items: center; justify-content: center">
                        <span style="font-size: 5rem">
                            @if(Str::contains($course->title, 'Writing')) 📝
                            @elseif(Str::contains($course->title, 'Speaking')) 🗣️
                            @elseif(Str::contains($course->title, 'Grammar')) 📚
                            @else 🎓 @endif
                        </span>
                    </div>
                </div>

                <h1 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.3">{{ $course->title }}</h1>

                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.5rem">
                    <span class="badge" style="background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.2)">Status: {{ ucfirst($course->status) }}</span>
                    <span style="color: var(--text-muted); font-size: 0.85rem; display:flex; align-items:center">⏰ 12 Hours Content</span>
                    <span style="color: var(--text-muted); font-size: 0.85rem; display:flex; align-items:center">👤 1,240 Students</span>
                </div>

                <div class="glass-card">
                    <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem">About this course</h2>
                    <p style="color: var(--text-muted); line-height: 1.8; margin-bottom: 1.5rem">{{ $course->description }}</p>

                    <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem">What you'll learn</h3>
                    <ul class="learn-grid">
                        <li><span style="color:#10b981">✓</span> Advanced Exam Strategies</li>
                        <li><span style="color:#10b981">✓</span> Band 8.0+ Vocabulary</li>
                        <li><span style="color:#10b981">✓</span> Native-level Structures</li>
                        <li><span style="color:#10b981">✓</span> Time Management Hacks</li>
                    </ul>
                </div>
            </div>

            <!-- Right: Sticky Sidebar -->
            <div class="course-sidebar">
                <div class="glass-card" style="position: sticky; top: 90px">
                    <div style="font-size: 2rem; font-weight: 800; margin-bottom: 0.4rem">${{ number_format($course->price, 2) }}</div>
                    <p style="color: var(--text-muted); font-size: 0.82rem; margin-bottom: 1.5rem">Full lifetime access. No subscription.</p>

                    @if($isEnrolled)
                        <button class="btn btn-primary" style="width: 100%; padding: 0.9rem; border-radius: 12px; font-weight: 700; background: #10b981">
                            🚀 Start Learning
                        </button>
                    @else
                        <form action="{{ route('course.enroll', $course->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.9rem; border-radius: 12px; font-weight: 700">
                                Enroll Now ➜
                            </button>
                        </form>
                    @endif

                    <div style="margin-top: 1.5rem; border-top: 1px solid var(--glass-border); padding-top: 1.25rem">
                        <h4 style="font-size: 0.85rem; font-weight: 700; margin-bottom: 0.75rem">Course features:</h4>
                        <ul style="list-style: none; padding: 0; font-size: 0.82rem; color: var(--text-muted)">
                            <li style="margin-bottom: 0.6rem">📱 Access on mobile</li>
                            <li style="margin-bottom: 0.6rem">📜 Certificate of completion</li>
                            <li style="margin-bottom: 0.6rem">♾️ Lifetime access</li>
                            <li>💬 Teacher Q&A Support</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .course-show-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            align-items: start;
        }
        .learn-grid {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .learn-grid li { display: flex; gap: 0.5rem }
        @media (max-width: 768px) {
            .course-show-grid {
                grid-template-columns: 1fr;
            }
            /* Move sidebar above content on mobile */
            .course-sidebar { order: -1; }
            .learn-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .course-show-grid { gap: 1rem; }
        }
    </style>
</x-app-layout>
