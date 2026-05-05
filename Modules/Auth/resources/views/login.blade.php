<x-guest-layout>
    <div class="glass-card animate-fade-in">
        <h2 class="text-3xl mb-2">{{ __('ui.sign_in') }}</h2>
        <p class="text-muted mb-8">{{ __('ui.welcome_back') }}.</p>

        @if(session('success'))
            <div class="glass p-4 mb-6 border-accent text-accent text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="glass p-4 mb-6 border-danger text-danger text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="/login">
            @csrf
            <div class="form-group">
                <label class="form-label">{{ __('ui.email') }}</label>
                <input type="email" name="email" class="form-control" placeholder="name@example.com" required value="{{ old('email') }}">
            </div>

            <div class="form-group">
                <div class="flex justify-between items-center mb-1">
                    <label class="form-label">{{ __('ui.password') }}</label>
                    <a href="/forgot-password" class="text-xs text-primary hover:underline">{{ __('ui.forgot_password') }}</a>
                </div>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <div class="form-group flex items-center">
                <input type="checkbox" id="remember" name="remember" class="w-4 h-4 rounded border-glass-border bg-bg-secondary text-primary focus:ring-primary/20">
                <label for="remember" class="ml-2 text-sm text-muted cursor-pointer">{{ __('ui.remember_me') }}</label>
            </div>

            <button type="submit" class="btn btn-primary w-full">{{ __('ui.sign_in') }}</button>
        </form>

        <p class="mt-8 text-center text-sm text-muted">
            {{ __('ui.no_account') }} <a href="/register" class="text-primary font-bold hover:underline">{{ __('ui.create_account') }}</a>
        </p>
    </div>
</x-guest-layout>
