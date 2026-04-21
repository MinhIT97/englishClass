<x-app-layout>
    <div style="margin-bottom: 2.5rem">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem">Account Settings</h1>
        <p style="color: var(--text-muted)">Manage your profile information and account security.</p>
    </div>

    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 1rem; border-radius: var(--radius); margin-bottom: 2rem; border: 1px solid rgba(16, 185, 129, 0.2);">
            {{ session('success') }}
        </div>
    @endif

    <div class="settings-grid">
        <!-- General Info -->
        <div class="glass-card">
            <h3 style="margin-bottom: 1.5rem">General Information</h3>
            <form action="{{ route('settings.update') }}" method="POST">
                @csrf
                <div class="form-group" style="margin-bottom: 1.5rem">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-muted);">Full Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control" style="width: 100%; padding: 0.75rem; background: var(--bg-secondary); border: 1px solid var(--glass-border); border-radius: var(--radius); color: var(--text-main);" required>
                    @error('name') <span style="color: #ef4444; font-size: 0.75rem;">{{ $message }}</span> @enderror
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-muted);">Email Address (Read Only)</label>
                    <input type="email" value="{{ $user->email }}" class="form-control" style="width: 100%; padding: 0.75rem; background: rgba(0,0,0,0.1); border: 1px solid var(--glass-border); border-radius: var(--radius); color: var(--text-muted);" readonly>
                </div>

                <div class="form-group" style="margin-bottom: 2rem">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-muted);">IELTS Target Band</label>
                    <select name="target_band" class="form-control" style="width: 100%; padding: 0.75rem; background: var(--bg-secondary); border: 1px solid var(--glass-border); border-radius: var(--radius); color: var(--text-main);">
                        @for($i = 4.0; $i <= 9.0; $i += 0.5)
                            <option value="{{ $i }}" {{ old('target_band', $user->target_band) == $i ? 'selected' : '' }}>Band {{ number_format($i, 1) }}</option>
                        @endfor
                    </select>
                    @error('target_band') <span style="color: #ef4444; font-size: 0.75rem;">{{ $message }}</span> @enderror
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%">Save Basic Info</button>
            </form>
        </div>

        <!-- Security -->
        <div class="glass-card">
            <h3 style="margin-bottom: 1.5rem">Security & Password</h3>
            <form action="{{ route('settings.update') }}" method="POST">
                @csrf
                <input type="hidden" name="name" value="{{ $user->name }}"> <!-- Keep name -->
                
                <div class="form-group" style="margin-bottom: 1.5rem">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-muted);">New Password</label>
                    <input type="password" name="password" class="form-control" style="width: 100%; padding: 0.75rem; background: var(--bg-secondary); border: 1px solid var(--glass-border); border-radius: var(--radius); color: var(--text-main);">
                    @error('password') <span style="color: #ef4444; font-size: 0.75rem;">{{ $message }}</span> @enderror
                </div>

                <div class="form-group" style="margin-bottom: 2rem">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-muted);">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control" style="width: 100%; padding: 0.75rem; background: var(--bg-secondary); border: 1px solid var(--glass-border); border-radius: var(--radius); color: var(--text-main);">
                </div>

                <button type="submit" class="btn btn-outline" style="width: 100%">Update Password</button>
            </form>
            
            <div style="margin-top: 2rem; padding: 1rem; background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.1); border-radius: var(--radius);">
                <p style="font-size: 0.75rem; color: #ef4444; margin: 0">Note: Changing your password will not log you out of your current session.</p>
            </div>
        </div>
    </div>

    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: start;
        }
        @media (max-width: 900px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</x-app-layout>
