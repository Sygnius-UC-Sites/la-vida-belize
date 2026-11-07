<div class="row">

    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("sub_room_title") !!}
            {!! Form::text("sub_room_title",null,["class"=>"form-control","required"]) !!}
            <span class="text-danger">{{ $errors->first("sub_room_title")}}</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("sub_room_sub_title") !!}
            {!! Form::text("sub_room_sub_title",null,["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("sub_room_sub_title")}}</span>
        </div>
    </div>
    <input type="hidden" name="property_id" value="{{$property_id }}">
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("sub_room_status") !!}
            {!! Form::select("sub_room_status",Helper::getStatusOptions(),null,["class"=>"form-control"]) !!}
        </div>
    </div>
     
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("sub_room_description") !!}
            {!! Form::textarea("sub_room_description",null,["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("sub_room_description")}}</span>
        </div>
    </div>



</div>
