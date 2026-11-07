@extends('auth._shell', ['title' => 'Reset password'])

@section('formpane')
    <a href="{{ url(config('adminlte.login_url', 'login')) }}" class="login-back-link">Back to sign in</a>

    <div class="login-eyebrow">Password reset</div>
    <h2>Forgot your password?</h2>
    <p class="login-sub">Enter the email address tied to your account and we'll send you a secure link to choose a new password.</p>

    @if (session('status'))
        <div class="login-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="login-error">{{ $errors->first() }}</div>
    @endif

    <form class="login-form" action="{{ url(config('adminlte.password_email_url', 'password/email')) }}" method="post" autocomplete="off">
        @csrf

        <div class="login-field">
            <label for="reset-email">Email address</label>
            <input id="reset-email" type="email" name="email" value="{{ old('email') }}"
                   placeholder="you@property.com" autocomplete="username" autofocus required>
        </div>

        <button type="submit" class="login-submit" style="margin-top:8px">Email me a reset link</button>
    </form>

    <div class="login-foot">
        <span><strong>Stuck?</strong> Contact your account manager or site administrator so they can verify your identity.</span>
        <span>&copy; {{ date('Y') }} {{ $setting_data['website'] ?? config('app.name', 'Admin') }}</span>
    </div>
@endsection
