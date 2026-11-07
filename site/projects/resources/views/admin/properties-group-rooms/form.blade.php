<div class="row">

    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("room_title") !!}
            {!! Form::text("room_title",null,["class"=>"form-control","required"]) !!}
            <span class="text-danger">{{ $errors->first("room_title")}}</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("room_sub_title") !!}
            {!! Form::text("room_sub_title",null,["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("room_sub_title")}}</span>
        </div>
    </div>
    <input type="hidden" name="property_id" value="{{$property_id }}">
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("room_status") !!}
            {!! Form::select("room_status",Helper::getStatusOptions(),null,["class"=>"form-control"]) !!}
        </div>
    </div>
     
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("room_description") !!}
            {!! Form::textarea("room_description",null,["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("room_description")}}</span>
        </div>
    </div>



</div>
