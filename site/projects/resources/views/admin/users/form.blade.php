@php
    $editing = isset($data) && $data;
    $canManageDevelopers = $canManageDevelopers ?? false;
    $isDeveloperChecked = old('is_developer', $editing ? (bool) $data->is_developer : false);
@endphp
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label("name", "Name") !!}
            {!! Form::text("name",null,["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("name")}}</span>
        </div>
    </div>
    

    <div class="col-md-4">
        <div class="form-group">
            
            {!! Form::label("email", "Email") !!}
            {!! Form::email("email",null,["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("email")}}</span>
            
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            
            {!! Form::label("password", $editing ? "New password" : "Password") !!}
            {!! Form::password("password",["class"=>"form-control","autocomplete"=>"new-password"]) !!}
            @if($editing)
                <small class="form-text text-muted">Leave blank to keep the current password. Enter a new password here to reset it.</small>
            @else
                <small class="form-text text-muted">Minimum 10 characters.</small>
            @endif
            <span class="text-danger">{{ $errors->first("password")}}</span>
            
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label("password_confirmation", $editing ? "Confirm new password" : "Confirm password") !!}
            {!! Form::password("password_confirmation",["class"=>"form-control","autocomplete"=>"new-password"]) !!}
        </div>
    </div>
    <div class="col-md-8">
        <div class="form-group">
            <label>Access level</label>
            @if($canManageDevelopers)
                <div class="custom-control custom-switch mt-2">
                    <input type="checkbox" class="custom-control-input" id="is_developer" name="is_developer" value="1" {{ $isDeveloperChecked ? 'checked' : '' }}>
                    <label class="custom-control-label" for="is_developer">Developer account</label>
                </div>
                <small class="form-text text-muted">Developer accounts see the developer bar, can open feature flags, and can create or manage other developer accounts.</small>
            @else
                <div class="alert alert-light border mb-0">
                    Admin user
                    <small class="d-block text-muted">Only developer accounts can create or edit developer users.</small>
                </div>
            @endif
            <span class="text-danger">{{ $errors->first("is_developer")}}</span>
        </div>
    </div>
   

</div>

