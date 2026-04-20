<x-app-layout>
    <div style="max-width: 1000px; margin: 0 auto">
        <!-- Class Header -->
        <div class="glass-card" style="padding: 0; overflow: hidden; margin-bottom: 2rem">
            <div style="height: 200px; background: linear-gradient(135deg, var(--primary) 0%, #312e81 100%); display: flex; align-items: flex-end; padding: 2rem">
                <div>
                    <h1 style="color: white; font-size: 2.5rem; margin-bottom: 0.5rem">{{ $classroom->name }}</h1>
                    <div style="display: flex; gap: 1rem; color: rgba(255,255,255,0.8); font-size: 0.875rem">
                        <span>Teacher: {{ $classroom->teacher->name }}</span>
                        <span>•</span>
                        <span>Invite Code: <strong style="color: white">{{ $classroom->invite_code }}</strong></span>
                    </div>
                </div>
            </div>
            <div style="padding: 1rem 2rem; background: var(--bg-secondary); border-top: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center">
                <div style="display: flex; gap: 2rem">
                    <button class="tab-link active" style="color: var(--primary); font-weight: 700; background: none; border: none; font-size: 0.9rem">Wall / Feed</button>
                    <button class="tab-link" style="color: var(--text-muted); background: none; border: none; font-size: 0.9rem">People ({{ $classroom->students->count() }})</button>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem">
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
                                <form action="{{ route('classroom.post.store', $classroom->id) }}" method="POST">
                                    @csrf
                                    <textarea name="content" required placeholder="Share something with the class..." style="width: 100%; height: 100px; background: var(--bg-main); border: 1px solid var(--glass-border); border-radius: 12px; padding: 1rem; resize: none; margin-bottom: 1rem"></textarea>
                                    <div style="display: flex; justify-content: space-between; align-items: center">
                                        <div style="display: flex; gap: 0.5rem">
                                            <select name="type" style="background: var(--bg-main); border: 1px solid var(--glass-border); color: var(--text-muted); padding: 0.5rem; border-radius: 8px; font-size: 0.8rem">
                                                <option value="announcement">Announcement 📢</option>
                                                <option value="schedule">Schedule 📅</option>
                                                <option value="meeting">Meeting 💻</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.5rem; border-radius: 50px">Post</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Posts List -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem">
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
                            <div style="line-height: 1.6; white-space: pre-wrap">{{ $post->content }}</div>
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
