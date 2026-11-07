<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("email_type") !!}
            {!! Form::text("email_type",null,["class"=>"form-control","id"=>"email_type"]) !!}
            <span class="text-danger">{{ $errors->first("email_type")}}</span>
        </div>
    </div>
   
    <div class="col-md-6">
        <div class="form-group">
            
            {!! Form::label("email_subject") !!}
            {!! Form::text("email_subject",null,["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("email_subject")}}</span>
            
        </div>
    </div>
   
</div>


<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("email_body") !!}
            {!! Form::textarea("email_body",null,["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("email_body")}}</span>
        </div>
    </div>


</div>