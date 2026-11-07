<div class="row" >
	<div class="col-md-12">
		<a href="javascript:;" class="add-space-data btn btn-info"><i class="fa fa-plus"></i> Add Property Space</a>
		 	 <hr>
	</div>




</div>
<div class="row mb-2">
    <div class="col-md-4">
        <strong>Space Name</strong>
    </div>
    <div class="col-md-4">
        <strong>Image</strong>
    </div>

    <div class="col-md-2">
        <strong>Status</strong>
    </div>
    <div class="col-md-2">
        <strong>Action</strong>
    </div>
    
    <div class="col-md-12">
        <br>
    </div>
</div>
<div id="space-area-section">
    @isset($data)
@foreach(App\Models\PropertySpace::where("property_id",$data->id)->get() as $c)
    <div class="row gaurav-delete-property-space">
        <input type="hidden" name="space_id[]" value="{{ $c->id }}" >
                <div class="col-md-4">
                    {!! Form::text("space_name[]",$c->space_name,["class"=>"form-control","placeholder"=>"Space Name"]) !!}
                </div>
            
                <div class="col-md-4">
                    {!! Form::file("space_image[]",["class"=>"form-control"]) !!}
                    @if($c->space_image)
                    <img src="{{ asset($c->space_image) }}" style="height:100px;" >
                    @endif
                </div>
                <div class="col-md-2">
                    {!! Form::select("space_status[]",["active"=>"active","deactive"=>"deactive"],$c->space_status,["class"=>"form-control"]) !!}
                </div>
                <div class="col-md-2">
                    <a href="javascript:;" class="delete-space-data-from-db btn btn-danger " data-id="{{ $c->id }}" ><i class="fa fa-trash"></i> </a>
                </div>
                
                <div class="col-md-12">
                    <br>
                </div>
            </div>
@endforeach
@endisset
</div>




