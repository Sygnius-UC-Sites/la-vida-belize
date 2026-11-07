<div class="row">
    
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("PMS NAME") !!}
            {!! Form::text("api_pms",null,["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("api_pms")}}</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("API KEY") !!}
            {!! Form::text("api_id",null,["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("api_id")}}</span>
        </div>
    </div>
   

   
</div>