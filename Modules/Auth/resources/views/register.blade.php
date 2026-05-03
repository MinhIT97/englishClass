<x-guest-layout>
    <div class="glass-card">
        <h2 style="margin-bottom: 0.5rem">{{ __('ui.start_journey') }}</h2>
        <p style="color: var(--text-muted); margin-bottom: 2rem; font-size: 0.875rem">{{ __('ui.join_desc') }}</p>


        @if($errors->any())
            <div class="glass" style="padding: 1rem; margin-bottom: 1.5rem; border-color: #ef4444; color: #ef4444; font-size: 0.875rem">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="/register">
            @csrf
            <div class="form-group">
                <label class="form-label">{{ __('ui.full_name') }}</label>
                <input type="text" name="name" class="form-control" placeholder="John Doe" required value="{{ old('name') }}">
            </div>


            <div class="form-group">
                <label class="form-label">{{ __('ui.email') }}</label>
                <input type="email" name="email" class="form-control" placeholder="name@example.com" required value="{{ old('email') }}">
            </div>


            <div class="form-group">
                <label class="form-label">{{ __('ui.target_band') }}</label>
                <select name="target_band" class="form-control">
                    <option value="">{{ __('ui.target_band_select') }}</option>

                    <option value="6.0" {{ old('target_band') == '6.0' ? 'selected' : '' }}>6.0</option>
                    <option value="6.5" {{ old('target_band') == '6.5' ? 'selected' : '' }}>6.5</option>
                    <option value="7.0" {{ old('target_band') == '7.0' ? 'selected' : '' }}>7.0</option>
                    <option value="7.5" {{ old('target_band') == '7.5' ? 'selected' : '' }}>7.5</option>
                    <option value="8.0" {{ old('target_band') == '8.0' ? 'selected' : '' }}>8.0</option>
                    <option value="8.5" {{ old('target_band') == '8.5' ? 'selected' : '' }}>8.5</option>
                    <option value="9.0" {{ old('target_band') == '9.0' ? 'selected' : '' }}>9.0</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('ui.password') }}</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('ui.confirm_password') }}</label>
                <input type="password" name="password_confirmation" class="form-control" placeholder="••••••••" required>
            </div>


            <button type="submit" class="btn btn-primary" style="width: 100%">{{ __('ui.create_account') }}</button>

        </form>

        <p style="margin-top: 2rem; text-align: center; font-size: 0.875rem; color: var(--text-muted)">
            {{ __('ui.already_have_account') }} <a href="/login" style="color: var(--primary); text-decoration: none; font-weight: 600">{{ __('ui.sign_in') }}</a>
        </p>

    </div>
</x-guest-layout>
