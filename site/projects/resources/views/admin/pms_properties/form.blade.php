 <div class="card card-primary card-outline card-outline-tabs">
              <div class="card-header p-0 border-bottom-0">
                <ul class="nav nav-tabs" id="custom-tabs-three-tab" role="tablist">
                  <li class="nav-item">
                    <a class="nav-link active" id="custom-tabs-three-home-tab" data-toggle="pill" href="#custom-tabs-three-home" role="tab" aria-controls="custom-tabs-three-home" aria-selected="true">Main</a>
                  </li>
                  
                  @isset($data)
                  <li class="nav-item  ">
                    <a class="nav-link" id="custom-tabs-three-amenities-tab" data-toggle="pill" href="#custom-tabs-three-amenities" role="tab" aria-controls="custom-tabs-three-amenities" aria-selected="false">Amenities </a>
                  </li>
                  @endisset
                  
                </ul>
              </div>
              
              <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                  <div class="tab-pane fade active show" id="custom-tabs-three-home" role="tabpanel" aria-labelledby="custom-tabs-three-home-tab">
                    
<h4 class="text-info mt-3"><i class="fas fa-link"></i> Synced from Lodgify (Read-Only)</h4>
<div class="row bg-light p-3 border rounded mb-4">
    @isset($data)
    <div class="col-md-3 text-center">
        @if(!empty($data->image_url))
            <img src="{{ Str::startsWith($data->image_url, '//') ? 'https:'.$data->image_url : $data->image_url }}" alt="Property Image" class="img-fluid rounded" style="max-height: 120px;">
        @else
            <div class="text-muted p-4 border rounded"><i class="fas fa-image fa-3x"></i><br><small>No Image</small></div>
        @endif
    </div>
    <div class="col-md-4">
        <p><strong>Property Name:</strong> {{ $data->name ?? 'N/A' }}</p>
        <p><strong>Lodgify ID:</strong> {{ $data->pms_property_id ?? 'N/A' }}</p>
        <p><strong>Pricing:</strong> {{ $data->currency ?? 'USD' }} {{ $data->min_price ?? '0' }} / {{ $data->price_unit_in_days ?? 'night' }}</p>
    </div>
    <div class="col-md-5">
        <p><strong>Address:</strong> {{ $data->address ?? 'N/A' }}</p>
        <p><strong>City/Zip:</strong> {{ $data->city ?? 'N/A' }} {{ $data->zip ?? '' }}</p>
        <p><strong>Country:</strong> {{ $data->country ?? 'N/A' }}</p>
    </div>
    @else
        <div class="col-12"><p class="text-muted">Data will be pulled from Lodgify after creation.</p></div>
    @endisset
</div>

<h4 class="text-warning">SEO & Website Section</h4>

<div class="row">
    <div class="col-md-12">
		<div class="form-group">
			{!! Form::label("location") !!}
			{!! Form::select("location_id",ModelHelper::getLocationSelectList(),null,["class"=>"form-control","placeholder"=>"Choose Location"]) !!}
			<span class="text-danger">{{ $errors->first("heading") }}</span>
		</div>
	</div>
    <div class="col-md-6">
        <div class="form-group">
             <label>SEO URL ( Only A-z,0-9,_,- are allowed)*</label>
                {!! Form::text("seo_url",null,["class"=>"form-control", "pattern"=>"[a-zA-Z0-9-_]+", "title"=>"Enter Valid SEO URL", "oninvalid"=>"this.setCustomValidity('SEO URL is not Valid Please enter first letter must be a-z and only accept chars a-z 0-9,-,_')" ,"onchange"=>"try{setCustomValidity('')}catch(e){}", "oninput"=>"setCustomValidity(' ')","required"=>"required"]) !!}
          <span class="text-danger">{{ $errors->first("seo_url") }}</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
          {!! Form::label("status") !!}
          {!! Form::select("status",Helper::getBooleanDataActual(),null,["class"=>"form-control"]) !!}
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
          {!! Form::label("Home page show") !!}
          {!! Form::select("is_home",Helper::getBooleanDataActual(),null,["class"=>"form-control"]) !!}
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("meta_title") !!}
            {!! Form::textarea("meta_title",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("meta_title")}}</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("meta_keywords") !!}
            {!! Form::textarea("meta_keywords",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("meta_keywords")}}</span>
        </div>
    </div>
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("banner_image") !!}
            {!! Form::file("banner_image",["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("banner_image")}}</span>
             @isset($data)
                @if($data->banner_image!="")
                     <a href="{{ asset(($data->banner_image)) }}" target="_BLANK" >Attachment</a>  <br>
                     
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input custom-control-input-danger" name="remove_banner_image" value="yes" type="checkbox" id="remove_banner_image" >
                        <label for="remove_banner_image" class="custom-control-label">Remove Banner Image</label>
                    </div>
                @endif
            @endisset
        </div>
    </div>

    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("review_widget") !!}
            {!! Form::textarea("review_widget",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("review_widget")}}</span>
        </div>
    </div>
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("meta_description") !!}
            {!! Form::textarea("meta_description",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("meta_description")}}</span>
        </div>
    </div>
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("map") !!}
            {!! Form::textarea("map",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("map")}}</span>
        </div>
    </div>
</div>

                  
                  		
                  
                  
                  </div>
                 
                  @isset($data)
                  <div class="tab-pane fade  " id="custom-tabs-three-amenities" role="tabpanel" aria-labelledby="custom-tabs-three-amenities-tab">
                     {{-- PMS amenities are synced from the provider — the core
                          amenity editor posted to core-property routes with a PMS
                          id (404). Show the synced list read-only instead. --}}
                     @php
                        $syncedAmenities = [];
                        $pmsRoom = \App\Models\Pms\PmsRoom::where('pms_source', $data->pms_source)
                            ->where('pms_property_id', $data->pms_property_id)->first();
                        if ($pmsRoom && $pmsRoom->amenities_json) {
                            $decoded = json_decode($pmsRoom->amenities_json, true);
                            if (is_array($decoded)) {
                                array_walk_recursive($decoded, function ($v, $k) use (&$syncedAmenities) {
                                    if (in_array($k, ['text', 'name'], true) && is_string($v) && $v !== '') $syncedAmenities[] = $v;
                                });
                            }
                        }
                        $syncedAmenities = array_values(array_unique($syncedAmenities));
                     @endphp
                     <div class="alert alert-info">
                        <i class="fa fa-sync-alt mr-1"></i>
                        Amenities for PMS listings are managed in your
                        {{ ucfirst($data->pms_source ?: 'PMS') }} account and updated on every sync.
                     </div>
                     @if($syncedAmenities)
                        <div class="row">
                           @foreach($syncedAmenities as $amen)
                              <div class="col-md-4 mb-1"><i class="fa fa-check text-success mr-1"></i>{{ $amen }}</div>
                           @endforeach
                        </div>
                     @else
                        <p class="text-muted mb-0">No amenities synced yet — run a sync from PMS Settings &amp; Sync.</p>
                     @endif
                  </div>
                  @endisset
               
                 
                </div>
                <div class="alert"></div>
               
              </div>
              
              <!-- /.card -->
            </div>




