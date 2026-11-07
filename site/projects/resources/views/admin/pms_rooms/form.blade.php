<h4 class="text-info mt-3"><i class="fas fa-link"></i> Synced from Lodgify (Read-Only)</h4>
<div class="row bg-light p-3 border rounded mb-4">
    @isset($data)
    <div class="col-md-3 text-center">
        @if(!empty($data->image_url))
            <img src="{{ Str::startsWith($data->image_url, '//') ? 'https:'.$data->image_url : $data->image_url }}" alt="Room Image" class="img-fluid rounded" style="max-height: 120px;">
        @else
            <div class="text-muted p-4 border rounded"><i class="fas fa-image fa-3x"></i><br><small>No Image</small></div>
        @endif
    </div>
    <div class="col-md-4">
        <p><strong>Room Name:</strong> {{ $data->name ?? 'N/A' }}</p>
        <p><strong>Room ID:</strong> {{ $data->pms_room_id ?? 'N/A' }}</p>
        <p><strong>Max People:</strong> {{ $data->max_people ?? 'N/A' }}</p>
    </div>
    <div class="col-md-5">
        <p><strong>Bedrooms:</strong> {{ $data->bedrooms ?? 'N/A' }}</p>
        <p><strong>Bathrooms:</strong> {{ $data->bathrooms ?? 'N/A' }}</p>
    </div>
    <div class="col-md-12 mt-3">
        <strong>Description:</strong>
        <div class="border bg-white p-2 mt-1 rounded" style="max-height: 200px; overflow-y: auto;">
            {!! $data->description ?? '<i>No description</i>' !!}
        </div>
    </div>
    @else
        <div class="col-12"><p class="text-muted">Data will be pulled from Lodgify after creation.</p></div>
    @endisset
</div>

<h4 class="text-warning">SEO & Additional Settings</h4>

<div class="row">
    <div class="col-md-6">
		<div class="form-group">
			{!! Form::label("location") !!}
			{!! Form::select("location_id",ModelHelper::getLocationSelectList(),null,["class"=>"form-control","placeholder"=>"Choose Location"]) !!}
			<span class="text-danger">{{ $errors->first("heading") }}</span>
		</div>
	</div>
  
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label("ordering") !!}
            {!! Form::number("ordering",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("ordering")}}</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label("custom_min_stay") !!}
            {!! Form::number("custom_min_stay",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("custom_min_stay")}}</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label("deposit_percent", "Deposit % (0–100, leave blank for full payment)") !!}
            {!! Form::number("deposit_percent",null,["class"=>"form-control","min"=>"1","max"=>"100","placeholder"=>"e.g. 50"]) !!}
            <small class="text-muted">If set, guest pays this % today and the balance is due at check-in.</small>
            <span class="text-danger">{{ $errors->first("deposit_percent")}}</span>
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
  <div class="col-md-12 d-none">
        <div class="form-group">
            {!! Form::label("virtual_tour") !!}
            {!! Form::textarea("virtual_tour",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("virtual_tour")}}</span>
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
    
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("house_rules", "House Rules") !!}
            {!! Form::textarea("house_rules",null,["class"=>"form-control","rows"=>"4"]) !!}
            <span class="text-danger">{{ $errors->first("house_rules")}}</span>
        </div>
    </div>

    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("cancellation_policy", "Cancellation Policy") !!}
            {!! Form::textarea("cancellation_policy",null,["class"=>"form-control","rows"=>"4"]) !!}
            <span class="text-danger">{{ $errors->first("cancellation_policy")}}</span>
        </div>
    </div>

    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("booking_policy", "Booking Policy") !!}
            {!! Form::textarea("booking_policy",null,["class"=>"form-control","rows"=>"4"]) !!}
            <span class="text-danger">{{ $errors->first("booking_policy")}}</span>
        </div>
    </div>

    <div class="col-md-12">
        <div class="card border-info">
            <div class="card-header bg-light">
                <strong>Rental Agreement</strong>
                <small class="text-muted d-block">Leave disabled if the PMS should handle its own agreement emails/documents.</small>
            </div>
            <div class="card-body">
                <div class="custom-control custom-switch mb-3">
                    {!! Form::hidden('rental_agreement_required', 0) !!}
                    {!! Form::checkbox('rental_agreement_required', 1, (bool)($data->rental_agreement_required ?? false), ['class' => 'custom-control-input', 'id' => 'pms_rental_agreement_required']) !!}
                    <label class="custom-control-label" for="pms_rental_agreement_required">Use website-managed rental agreement for this PMS listing</label>
                </div>
                <div class="form-group">
                    {!! Form::label('rental_agreement_signing_mode', 'Signing timing') !!}
                    {!! Form::select('rental_agreement_signing_mode', [
                        'inherit' => 'Use global setting',
                        'post_confirmation' => 'After booking confirmation',
                        'pre_payment' => 'Before payment prompt / consent at checkout',
                    ], null, ['class' => 'form-control']) !!}
                    <small class="text-muted">For PMS bookings, post-confirmation is usually safest: payment/reservation completes, then the website sends the signing link.</small>
                    <span class="text-danger">{{ $errors->first('rental_agreement_signing_mode') }}</span>
                </div>
                <div class="form-group">
                    {!! Form::label('rental_agreement_template_html', 'Agreement template override') !!}
                    {!! Form::textarea('rental_agreement_template_html', null, ['class' => 'form-control', 'rows' => 5, 'placeholder' => 'Leave blank to use the global rental agreement template']) !!}
                    <span class="text-danger">{{ $errors->first('rental_agreement_template_html') }}</span>
                </div>
                <div class="form-group mb-0">
                    {!! Form::label('rental_agreement_house_rules_html', 'House rules override') !!}
                    {!! Form::textarea('rental_agreement_house_rules_html', null, ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Leave blank to use the global house rules']) !!}
                    <span class="text-danger">{{ $errors->first('rental_agreement_house_rules_html') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
