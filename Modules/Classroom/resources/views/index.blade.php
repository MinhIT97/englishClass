<x-app-layout>
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2.5rem">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem">Classrooms & Learning Groups</h1>
            <p style="color: var(--text-muted)">Connect with your teachers and fellow students.</p>
        </div>
        
        @if(auth()->user()->role === 'student')
            <button onclick="document.getElementById('join-modal').style.display='flex'" class="btn btn-primary" style="padding: 0.75rem 1.5rem; border-radius: 50px">Join Class 🔑</button>
        @else
            <button onclick="document.getElementById('create-modal').style.display='flex'" class="btn btn-primary" style="padding: 0.75rem 1.5rem; border-radius: 50px">+ Create New Class</button>
        @endif
    </div>

    <!-- Classroom Grid -->
    <div class="grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem">
        @forelse($classrooms as $classroom)
            <a href="{{ route('classroom.show', $classroom->id) }}" class="glass-card" style="padding: 0; overflow: hidden; display: block; text-decoration: none; color: inherit; transition: transform 0.3s">
                <div style="height: 120px; background: linear-gradient(135deg, var(--primary) 0%, #4338ca 100%); position: relative">
                    <span style="position: absolute; bottom: 1rem; right: 1rem; background: rgba(255,255,255,0.2); color: white; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.75rem; backdrop-filter: blur(10px)">
                        Code: {{ $classroom->invite_code }}
                    </span>
                </div>
                <div style="padding: 1.5rem">
                    <h3 style="margin-bottom: 0.5rem">{{ $classroom->name }}</h3>
                    <p style="font-size: 0.875rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 1.5rem">
                        {{ Str::limit($classroom->description, 100) ?? 'No description provided.' }}
                    </p>
                    <div style="display: flex; justify-content: space-between; align-items: center">
                        <div style="display: flex; align-items: center; gap: 0.5rem">
                            <div style="width: 24px; height: 24px; border-radius: 50%; background: var(--bg-main); display: flex; align-items: center; justify-content: center; font-size: 0.7rem">👤</div>
                            <span style="font-size: 0.75rem; color: var(--text-muted)">{{ $classroom->teacher->name }}</span>
                        </div>
                        <span style="font-size: 0.75rem; color: var(--primary); font-weight: 600">Enter Class ➜</span>
                    </div>
                </div>
            </a>
        @empty
            <div style="grid-column: 1/-1; text-align: center; padding: 5rem">
                <div style="font-size: 3rem; margin-bottom: 1.5rem">🏫</div>
                <h3>No classes found</h3>
                <p style="color: var(--text-muted); margin-bottom: 2rem">You haven't joined or created any classrooms yet.</p>
            </div>
        @endforelse
    </div>

    <!-- Modals -->
    <div id="create-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; backdrop-filter: blur(5px)">
        <div class="glass-card" style="width: 100%; max-width: 500px; padding: 2.5rem; position: relative">
            <button onclick="this.closest('.modal').style.display='none'" style="position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted)">&times;</button>
            <h2 style="margin-bottom: 1.5rem">Create Classroom</h2>
            <form action="{{ route('classroom.store') }}" method="POST">
                @csrf
                <div style="margin-bottom: 1.5rem">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem">Class Name</label>
                    <input type="text" name="name" required placeholder="e.g. IELTS Writing Intensive" style="width: 100%; background: var(--bg-main); border: 1px solid var(--glass-border); padding: 0.75rem 1rem; border-radius: 12px">
                </div>
                <div style="margin-bottom: 2rem">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem">Description (Optional)</label>
                    <textarea name="description" rows="3" style="width: 100%; background: var(--bg-main); border: 1px solid var(--glass-border); padding: 0.75rem 1rem; border-radius: 12px"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem">Create Class</button>
            </form>
        </div>
    </div>

    <div id="join-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; backdrop-filter: blur(5px)">
        <div class="glass-card" style="width: 100%; max-width: 400px; padding: 2.5rem; position: relative">
            <button onclick="this.closest('.modal').style.display='none'" style="position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted)">&times;</button>
            <h2 style="margin-bottom: 1rem; text-align: center">Join Class</h2>
            <p style="text-align: center; color: var(--text-muted); margin-bottom: 2rem; font-size: 0.875rem">Enter the 6-character invite code provided by your teacher.</p>
            <form action="{{ route('classroom.join') }}" method="POST">
                @csrf
                <input type="text" name="invite_code" required maxlength="6" placeholder="XYZ123" style="width: 100%; background: var(--bg-main); border: 1px solid var(--glass-border); padding: 1rem; border-radius: 12px; font-size: 1.5rem; text-align: center; letter-spacing: 5px; text-transform: uppercase; margin-bottom: 1.5rem">
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem">Join Now</button>
            </form>
        </div>
    </div>

    <style>
        .glass-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }
    </style>
</x-app-layout>
