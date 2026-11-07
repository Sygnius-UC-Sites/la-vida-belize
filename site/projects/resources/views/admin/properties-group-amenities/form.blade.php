<div class="row">

    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("name") !!}
            {!! Form::text("name",null,["class"=>"form-control","required"]) !!}
            <span class="text-danger">{{ $errors->first("name")}}</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("sorting") !!}
            {!! Form::number("sorting",null,["class"=>"form-control","required"]) !!}
            <span class="text-danger">{{ $errors->first("sorting")}}</span>
        </div>
    </div>
    <input type="hidden" name="property_id" value="{{$property_id }}">
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("status") !!}
            {!! Form::select("status",Helper::getStatusOptions(),null,["class"=>"form-control"]) !!}
        </div>
    </div>
     

    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("image") !!}
            {!! Form::file("image",["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("image")}}</span>
             @isset($data)
                @if($data->image!="")
                     <img src="{{ asset(($data->image)) }}" width="200" /> 
                @endif
            @endisset
        </div>
    </div>

</div>
