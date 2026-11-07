@extends('auth._shell', ['title' => 'Sign in'])

@section('formpane')
    <div class="login-eyebrow">Sign in</div>
    <h2>Welcome back</h2>
    <p class="login-sub">Enter your credentials to access your property dashboard.</p>

    @if ($errors->any())
        <div class="login-error">{{ $errors->first() }}</div>
    @endif

    <form class="login-form" action="{{ url(config('adminlte.login_url', 'login')) }}" method="post" autocomplete="off">
        @csrf

        <div class="login-field">
            <label for="login-email">Email address</label>
            <input id="login-email" type="email" name="email" value="{{ old('email') }}"
                   placeholder="you@property.com" autocomplete="username" autofocus required>
        </div>

        <div class="login-field">
            <label for="login-password">Password</label>
            <input id="login-password" type="password" name="password"
                   placeholder="••••••••" autocomplete="current-password" required>
        </div>

        <div class="login-row">
            <label class="login-remember">
                <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                Keep me signed in
            </label>
            <a href="{{ url(config('adminlte.password_reset_url', 'password/reset')) }}">Forgot password?</a>
        </div>

        <button type="submit" class="login-submit">Sign in to {{ $setting_data['website'] ?? 'your dashboard' }}</button>
    </form>

    <div class="login-foot">
        <span><strong>Need help?</strong> Contact your account manager or site administrator.</span>
        <span>&copy; {{ date('Y') }} {{ $setting_data['website'] ?? config('app.name', 'Admin') }} · All rights reserved.</span>
    </div>
@endsection
