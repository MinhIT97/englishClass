<x-app-layout>
    <x-slot name="head">
        <meta name="classroom-id" content="{{ $classroom->id }}">
        @php
            $members = collect([$classroom->teacher])
                ->merge($classroom->students)
                ->unique('id')
                ->map(fn($u) => [
                    'id'      => $u->id,
                    'name'    => $u->name,
                    'role'    => $u->role,
                    'initial' => strtoupper(substr($u->name, 0, 1)),
                ])
                ->values()
                ->toJson();
        @endphp
        <script>
            window.classroomMembers = {!! $members !!};
        </script>
        <style>
            /* Classroom show — responsive */
            .classroom-show-grid {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 2rem;
            }
            .classroom-banner h1 { font-size: 2.5rem; }
            .classroom-tabs {
                padding: 1rem 2rem;
                background: var(--bg-secondary);
                border-top: 1px solid var(--glass-border);
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            @media (max-width: 768px) {
                .classroom-show-grid { grid-template-columns: 1fr; }
                .classroom-banner h1 { font-size: 1.6rem; }
                .classroom-banner { padding: 1.25rem !important; }
                .classroom-tabs { padding: 0.75rem 1rem; gap: 0.25rem; }
                .classroom-tabs button { font-size: 0.8rem; }
            }
            @media (max-width: 480px) {
                .classroom-banner { height: 150px !important; }
            }
        </style>
    </x-slot>
    <div style="max-width: 1000px; margin: 0 auto">
        <!-- Class Header -->
        <div class="glass-card" style="padding: 0; overflow: hidden; margin-bottom: 2rem">
            <div class="classroom-banner" style="height: 200px; background: linear-gradient(135deg, var(--primary) 0%, #312e81 100%); display: flex; align-items: flex-end; padding: 2rem">
                <div>
                    <h1 class="classroom-banner-title" style="color: white; margin-bottom: 0.5rem">{{ $classroom->name }}</h1>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; color: rgba(255,255,255,0.8); font-size: 0.875rem">
                        <span>Teacher: {{ $classroom->teacher->name }}</span>
                        <span>•</span>
                        <span>Invite Code: <strong style="color: white">{{ $classroom->invite_code }}</strong></span>
                    </div>
                </div>
            </div>
            <div class="classroom-tabs">
                <div style="display: flex; gap: 1.5rem">
                    <button class="tab-link active" style="color: var(--primary); font-weight: 700; background: none; border: none; font-size: 0.9rem">Wall / Feed</button>
                    <button class="tab-link" style="color: var(--text-muted); background: none; border: none; font-size: 0.9rem">People ({{ $classroom->students->count() }})</button>
                </div>
            </div>
        </div>

        <div class="classroom-show-grid">
            <!-- Feed Column -->
            <div>
                <!-- Create Post -->
                @if(auth()->user()->role === 'teacher' || auth()->user()->role === 'admin' || $classroom->students->contains(auth()->id()))
                    <div class="glass-card" style="margin-bottom: 2rem; padding: 1.5rem">
                        <div style="display: flex; gap: 1rem">
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center">
                                {{ substr(auth()->user()->name, 0, 1) }}
                            </div>
                            <div style="flex: 1">
                                <form id="create-post-form" action="{{ route('classroom.post.store', $classroom->id) }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <textarea name="content" required placeholder="Share something with the class..." style="width: 100%; height: 100px; background: var(--bg-main); border: 1px solid var(--glass-border); border-radius: 12px; padding: 1rem; resize: none; margin-bottom: 1rem"></textarea>
                                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap">
                                        <div style="display: flex; gap: 0.5rem; align-items: center; flex: 1">
                                            <select name="type" style="background: var(--bg-main); border: 1px solid var(--glass-border); color: var(--text-muted); padding: 0.5rem; border-radius: 8px; font-size: 0.8rem">
                                                <option value="announcement">Announcement 📢</option>
                                                <option value="material">Study Material 📚</option>
                                                <option value="video">Learning Video 🎥</option>
                                                @if(auth()->user()->role === 'student')
                                                <option value="pronunciation">Pronunciation Submission 🗣️</option>
                                                @endif
                                                <option value="schedule">Schedule 📅</option>
                                                <option value="meeting">Meeting 💻</option>
                                            </select>
                                            <input type="file" name="attachment" style="font-size: 0.75rem; color: var(--text-muted)">
                                        </div>
                                        <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.5rem; border-radius: 50px">Post</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Posts List -->
                <div id="posts-list" style="display: flex; flex-direction: column; gap: 1.5rem">
                    @forelse($classroom->posts as $post)
                        <div class="glass-card" style="padding: 1.5rem">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem">
                                <div style="display: flex; gap: 0.75rem; align-items: center">
                                    <div style="width: 36px; height: 36px; border-radius: 50%; background: {{ $post->user->role === 'teacher' ? 'var(--accent)' : 'var(--primary)' }}; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.8rem">
                                        {{ substr($post->user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; font-size: 0.95rem">{{ $post->user->name }}</div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted)">{{ $post->created_at->diffForHumans() }}</div>
                                    </div>
                                </div>
                                <span class="badge" style="background: var(--bg-main); font-size: 0.7rem; text-transform: uppercase">{{ $post->type }}</span>
                            </div>
                            <div style="line-height: 1.6; white-space: pre-wrap; margin-bottom: 1rem">{{ $post->content }}</div>

                            @if($post->attachment_path)
                                <div style="margin-bottom: 1.5rem">
                                    @if($post->type === 'video' || $post->type === 'pronunciation')
                                        <video controls style="width: 100%; border-radius: 12px; background: #000; max-height: 400px">
                                            <source src="{{ asset('storage/' . $post->attachment_path) }}" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    @else
                                        <a href="{{ asset('storage/' . $post->attachment_path) }}" target="_blank" class="glass-card" style="display: flex; align-items: center; gap: 1rem; padding: 0.75rem 1rem; text-decoration: none; border: 1px dashed var(--glass-border)">
                                            <div style="font-size: 1.5rem">📁</div>
                                            <div style="flex: 1">
                                                <div style="font-weight: 600; font-size: 0.85rem">Attachment</div>
                                                <div style="font-size: 0.7rem; color: var(--text-muted)">Click to view or download</div>
                                            </div>
                                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                        </a>
                                    @endif
                                </div>
                            @endif

                            <!-- Feedback Section -->
                            @if($post->feedback_content)
                                <div style="background: rgba(16, 185, 129, 0.1); border-radius: 12px; padding: 1rem; border-left: 4px solid #10b981; margin-top: 1rem">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem">
                                        <div style="font-weight: 700; color: #065f46; font-size: 0.85rem">Teacher Feedback</div>
                                        @if($post->grade)
                                            <div style="background: #10b981; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 800">Grade: {{ $post->grade }}</div>
                                        @endif
                                    </div>
                                    <p style="font-size: 0.9rem; color: #064e3b">{{ $post->feedback_content }}</p>
                                    <div style="font-size: 0.7rem; color: #064e3b; margin-top: 0.5rem; opacity: 0.7; text-align: right">By {{ $post->feedbackBy->name ?? 'System' }}</div>
                                </div>
                            @elseif(auth()->user()->role === 'teacher' || auth()->user()->role === 'admin')
                                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--glass-border)">
                                    <details>
                                        <summary style="cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--primary)">Grade & Feedback</summary>
                                        <form action="{{ route('classroom.post.feedback', $post->id) }}" method="POST" style="margin-top: 1rem">
                                            @csrf
                                            <div style="display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: flex-start">
                                                <textarea name="feedback_content" required placeholder="Write your corrections and feedback..." style="width: 100%; height: 80px; background: var(--bg-main); border: 1px solid var(--glass-border); border-radius: 8px; padding: 0.75rem; font-size: 0.85rem; resize: none"></textarea>
                                                <div style="display: flex; flex-direction: column; gap: 0.5rem">
                                                    <input type="text" name="grade" placeholder="Grade" style="width: 80px; background: var(--bg-main); border: 1px solid var(--glass-border); border-radius: 8px; padding: 0.5rem; font-size: 0.85rem">
                                                    <button type="submit" class="btn btn-primary" style="padding: 0.5rem; font-size: 0.8rem">Submit</button>
                                                </div>
                                            </div>
                                        </form>
                                    </details>
                                </div>
                            @endif

                            <!-- Comments Section -->
                            <div style="margin-top: 1.5rem; border-top: 1px solid var(--glass-border); padding-top: 1rem">
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; color: var(--text-muted); font-size: 0.85rem">
                                    <span>💬</span>
                                    <span>{{ $post->comments->count() }} Comments</span>
                                </div>

                                <div id="comments-list-{{ $post->id }}">
                                    @foreach($post->comments as $comment)
                                        <div class="comment-item" style="display: flex; gap: 0.75rem; margin-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1rem">
                                            <div style="width: 28px; height: 28px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; flex-shrink: 0">
                                                {{ substr($comment->user->name, 0, 1) }}
                                            </div>
                                            <div style="flex: 1; min-width: 0">
                                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.25rem">
                                                    <span style="font-weight: 700; font-size: 0.8rem">{{ $comment->user->name }}</span>
                                                    <div style="display: flex; gap: 0.75rem; align-items: center">
                                                        <span style="font-size: 0.7rem; color: var(--text-muted)">{{ $comment->created_at->diffForHumans() }}</span>
                                                        <button type="button" class="reply-btn" data-username="{{ $comment->user->name }}" data-post-id="{{ $post->id }}" style="background: none; border: none; color: var(--primary); font-size: 0.7rem; font-weight: 700; cursor: pointer; padding: 0">Reply</button>
                                                    </div>
                                                </div>
                                                <div style="font-size: 0.85rem; margin-top: 0.3rem; line-height: 1.5; word-break: break-word">{{ $comment->content }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <!-- Add Comment Form -->
                                <form id="comment-form-{{ $post->id }}" class="comment-form" action="{{ route('classroom.post.comment', $post->id) }}" method="POST" data-post-id="{{ $post->id }}" style="margin-top: 1.5rem; display: flex; gap: 0.5rem">
                                    @csrf
                                    <input type="text" name="content" required placeholder="Write a comment..." style="flex: 1; background: var(--bg-main); border: 1px solid var(--glass-border); border-radius: 20px; padding: 0.5rem 1rem; font-size: 0.85rem">
                                    <button type="submit" style="background: none; border: none; font-size: 1.25rem; cursor: pointer">🕊️</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="glass-card" style="text-align: center; padding: 4rem">
                            <div style="font-size: 2rem; margin-bottom: 1rem">🍃</div>
                            <p style="color: var(--text-muted)">No posts yet. Start the conversation!</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Sidebar Column -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem">
                <!-- Class Info -->
                <div class="glass-card" style="padding: 1.5rem">
                    <h4 style="margin-bottom: 1rem">Class Description</h4>
                    <p style="font-size: 0.875rem; line-height: 1.6; color: var(--text-muted)">
                        {{ $classroom->description ?? 'No extra info provided.' }}
                    </p>
                </div>

                <!-- Upcoming Events (Mock) -->
                <div class="glass-card" style="padding: 1.5rem">
                    <h4 style="margin-bottom: 1rem">Upcoming Events</h4>
                    <div style="display: flex; flex-direction: column; gap: 1rem">
                        <div style="display: flex; gap: 1rem; align-items: center">
                            <div style="width: 45px; height: 45px; background: var(--bg-main); border-radius: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 1px solid var(--glass-border)">
                                <span style="font-size: 0.6rem; color: var(--primary); font-weight: 800">APR</span>
                                <span style="font-size: 1rem; font-weight: 700">25</span>
                            </div>
                            <div>
                                <div style="font-size: 0.875rem; font-weight: 600">Writing Workshop</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted)">Online via Google Meet</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Students -->
                <div class="glass-card" style="padding: 1.5rem">
                    <h4 style="margin-bottom: 1rem">Students ({{ $classroom->students->count() }})</h4>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem">
                        @foreach($classroom->students->take(10) as $student)
                            <div style="display: flex; align-items: center; gap: 0.5rem">
                                <div style="width: 24px; height: 24px; border-radius: 50%; background: var(--bg-main); display: flex; align-items: center; justify-content: center; font-size: 0.7rem">👤</div>
                                <span style="font-size: 0.875rem">{{ $student->name }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
