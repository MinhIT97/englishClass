<x-guest-layout>
    <div class="glass-card">
        <h2 style="margin-bottom: 0.5rem">Welcome Back</h2>
        <p style="color: var(--text-muted); margin-bottom: 2rem; font-size: 0.875rem">Please enter your details to sign in.</p>

        @if(session('success'))
            <div class="glass" style="padding: 1rem; margin-bottom: 1.5rem; border-color: var(--accent); color: var(--accent); font-size: 0.875rem">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="glass" style="padding: 1rem; margin-bottom: 1.5rem; border-color: #ef4444; color: #ef4444; font-size: 0.875rem">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="/login">
            @csrf
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="name@example.com" required value="{{ old('email') }}">
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between">
                    <label class="form-label">Password</label>
                </div>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <div class="form-group" style="display: flex; align-items: center">
                <input type="checkbox" id="remember" name="remember" style="margin-right: 0.5rem">
                <label for="remember" style="font-size: 0.875rem; color: var(--text-muted)">Remember me</label>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%">Sign In</button>
        </form>

        <p style="margin-top: 2rem; text-align: center; font-size: 0.875rem; color: var(--text-muted)">
            Don't have an account? <a href="/register" style="color: var(--primary); text-decoration: none; font-weight: 600">Create Account</a>
        </p>
    </div>
</x-guest-layout>
