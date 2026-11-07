@extends('admin.layouts')

@section('title', 'Change password')

@section('content_header')
    <h1>Change password</h1>
    <p class="text-muted">Update the password used to log in to this admin panel. Both fields are required and the new one must be different from the current.</p>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
    @endif

    <div class="card" style="max-width:560px;">
        <div class="card-header"><h5 class="m-0">Change password</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route('change-password-admin') }}" autocomplete="off">
                @csrf

                <div class="form-group">
                    <label for="old_password">Current password</label>
                    <div class="input-group">
                        <input type="password" id="old_password" name="old_password" class="form-control"
                               required autocomplete="current-password"
                               aria-describedby="old_password_error">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary toggle-pwd"
                                    data-target="old_password" tabindex="-1" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    @if($errors->first('old_password'))
                        <small id="old_password_error" class="text-danger">{{ $errors->first('old_password') }}</small>
                    @endif
                </div>

                <div class="form-group">
                    <label for="new_password">New password</label>
                    <div class="input-group">
                        <input type="password" id="new_password" name="new_password" class="form-control"
                               required autocomplete="new-password" minlength="8"
                               aria-describedby="new_password_help new_password_error">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary toggle-pwd"
                                    data-target="new_password" tabindex="-1" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <small id="new_password_help" class="form-text text-muted">Use at least 8 characters. Mix of letters, numbers, and symbols recommended.</small>
                    @if($errors->first('new_password'))
                        <small id="new_password_error" class="text-danger">{{ $errors->first('new_password') }}</small>
                    @endif
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <a href="{{ url('admin/setting') }}" class="text-muted small">← Back to settings</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key mr-1"></i> Update password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.toggle-pwd').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.dataset.target);
                if (!input) return;
                var isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                var icon = btn.querySelector('i');
                if (icon) icon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
                btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            });
        });
    </script>
@stop
