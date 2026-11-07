@extends('auth._shell', ['title' => 'Choose a new password'])

@section('formpane')
    <a href="{{ url(config('adminlte.login_url', 'login')) }}" class="login-back-link">Back to sign in</a>

    <div class="login-eyebrow">Password reset</div>
    <h2>Choose a new password</h2>
    <p class="login-sub">Pick a strong password — at least 8 characters with a mix of letters, numbers, and symbols.</p>

    @if ($errors->any())
        <div class="login-error">{{ $errors->first() }}</div>
    @endif

    <form class="login-form" action="{{ url(config('adminlte.password_reset_url', 'password/reset')) }}" method="post" autocomplete="off">
        @csrf
        <input type="hidden" name="token" value="{{ $token ?? request('token') }}">

        <div class="login-field">
            <label for="reset-email">Email address</label>
            <input id="reset-email" type="email" name="email"
                   value="{{ old('email', request('email')) }}"
                   placeholder="you@property.com" autocomplete="username" autofocus required>
        </div>

        <div class="login-field">
            <label for="reset-password">New password</label>
            <input id="reset-password" type="password" name="password"
                   placeholder="At least 8 characters" autocomplete="new-password" required>
        </div>

        <div class="login-field">
            <label for="reset-password-confirm">Confirm new password</label>
            <input id="reset-password-confirm" type="password" name="password_confirmation"
                   placeholder="Re-enter your new password" autocomplete="new-password" required>
        </div>

        <button type="submit" class="login-submit" style="margin-top:18px">Set new password</button>
    </form>

    <div class="login-foot">
        <span><strong>Heads up:</strong> The reset link expires after 60 minutes for security. Request a new one if it has expired.</span>
        <span>&copy; {{ date('Y') }} {{ $setting_data['website'] ?? config('app.name', 'Admin') }}</span>
    </div>
@endsection
