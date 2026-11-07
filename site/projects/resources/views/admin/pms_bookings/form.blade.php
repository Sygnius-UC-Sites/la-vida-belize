<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("Booking Type") !!}
            {!! Form::select("booking_type_admin",["invoice"=>"invoice","manual"=>"manual"],null,["class"=>"form-control","required","placeholder"=>"Choose Booking Type","id"=>"booking-selector"]) !!}
            <span class="text-danger">{{ $errors->first("booking_type_admin")}}</span>
        </div>
    </div>
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("property") !!}
            {!! Form::select("property_id",ModelHelper::getProperptySelectList(),null,["class"=>"form-control","required","placeholder"=>"Choose Property","id"=>"property-selector"]) !!}
            <span class="text-danger">{{ $errors->first("property_id")}}</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("checkin") !!}
            {!! Form::text("checkin",null,["required","autocomplete"=>"off","inputmode"=>"none","id"=>"txtFrom","placeholder"=>"Check in","class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("checkin")}}</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("checkout") !!}
            {!! Form::text("checkout",null,["required","autocomplete"=>"off","inputmode"=>"none","id"=>"txtTo","placeholder"=>"Check Out","class"=>"form-control lst" ]) !!}
            <span class="text-danger">{{ $errors->first("checkout")}}</span>
        </div>
    </div>
</div>
<div class="row" id="gaurav-data-new-logic">
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label("adults") !!}
            {!! Form::selectRange("adults",0,100,null,["class"=>"form-control","required","id"=>"adult_data"]) !!}
            <span class="text-danger">{{ $errors->first("adults")}}</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label("child") !!}
            {!! Form::selectRange("child",0,100,null,["class"=>"form-control","id"=>"child_data"]) !!}
            <span class="text-danger">{{ $errors->first("child")}}</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label("pets") !!}
            {!! Form::selectRange("pets",0,100,null,["class"=>"form-control","id"=>"pet_data"]) !!}
            <span class="text-danger">{{ $errors->first("pets")}}</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label("extra_discount") !!}
            {!! Form::number("extra_discount",null,["class"=>"form-control","id"=>"extra-discount"]) !!}
            <span class="text-danger">{{ $errors->first("extra_discount")}}</span>
        </div>
    </div>
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("Heating Pool") !!}
            {!! Form::select("heating_pool_fee_data_guarav",["Yes"=>"Yes","No"=>"No"],null,["class"=>"form-control","id"=>"heating_pool_fee_data_guarav"]) !!}
            <span class="text-danger">{{ $errors->first("extra_discount")}}</span>
        </div>
    </div>
</div>
<div class="row" id="pricedata-gaurav">
</div>
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
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("message") !!}
            {!! Form::textarea("message",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("message")}}</span>
        </div>
    </div>
</div>