<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label("name") !!}
            {!! Form::text("name",null,["class"=>"form-control","required"]) !!}
            <span class="text-danger">{{ $errors->first("name")}}</span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label("mobile") !!}
            {!! Form::text("mobile",null,["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("mobile")}}</span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            {!! Form::label("email") !!}
            {!! Form::email("email",null,["class"=>"form-control","required"]) !!}
            <span class="text-danger">{{ $errors->first("email")}}</span>
        </div>
    </div>

    {{-- Trip-context fields submitted by the public form. Editable here so the
         admin can correct typos / add notes before replying. --}}
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label("check_in", "Check-in") !!}
            {!! Form::date("check_in", null, ["class"=>"form-control"]) !!}
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label("check_out", "Check-out") !!}
            {!! Form::date("check_out", null, ["class"=>"form-control"]) !!}
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            {!! Form::label("guests") !!}
            {!! Form::text("guests", null, ["class"=>"form-control"]) !!}
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            {!! Form::label("property", "Property type") !!}
            {!! Form::select("property", ['' => '—', 'condo' => 'Condo', 'house' => 'House', 'either' => 'Either'], null, ["class"=>"form-control"]) !!}
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            {!! Form::label("property_slug", "Property slug") !!}
            {!! Form::text("property_slug", null, ["class"=>"form-control", "placeholder" => "(if linked to a specific property)"]) !!}
        </div>
    </div>

    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("message") !!}
            {!! Form::textarea("message",null,["class"=>"form-control","required","rows"=>"4"]) !!}
            <span class="text-danger">{{ $errors->first("message")}}</span>
        </div>
    </div>

</div>
