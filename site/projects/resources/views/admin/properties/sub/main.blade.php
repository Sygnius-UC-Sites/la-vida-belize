{{-- ========== SECTION 1: Identification ========== --}}
<div class="card card-info">
	<div class="card-header">
		<h5 class="card-title m-0">Identification</h5>
	</div>
	<div class="card-body">
		<div class="row">
			<div class="col-md-4">
				<div class="form-group">
					{!! Form::label("name*") !!}
					{!! Form::text("name",null,["class"=>"form-control","placeholder"=>"Enter name","required"=>"required"]) !!}
					<small class="text-muted">Property's main name. Shown to guests on listing cards, booking pages, payment pages, and emails.</small>
					<span class="text-danger">{{ $errors->first("name") }}</span>
				</div>
			</div>
			<div class="col-md-4">
				<div class="form-group">
					{!! Form::label("heading*") !!}
					{!! Form::text("heading",null,["class"=>"form-control","placeholder"=>"Enter heading","required"=>"required"]) !!}
					<small class="text-muted">Public display heading shown on booking pages and listing cards (e.g. "Beachfront House").</small>
					<span class="text-danger">{{ $errors->first("heading") }}</span>
				</div>
			</div>
			<div class="col-md-4">
				<div class="form-group">
					<label>SEO URL ( Only A-z,0-9,_,- are allowed)*</label>
					{!! Form::text("seo_url",null,["class"=>"form-control", "id"=>"seo-url-input", "pattern"=>"[a-zA-Z0-9-_]+", "title"=>"Enter Valid SEO URL", "required"=>"required"]) !!}
					<small class="text-muted">Public slug used in the canonical property URL, e.g. /stay/&lt;seo_url&gt; or /condos/&lt;seo_url&gt; when the site has divisions. Letters, digits, '-' and '_' only.</small>
					<span class="text-danger">{{ $errors->first("seo_url") }}</span>
				</div>
			</div>
			<div class="col-md-5">
				<div class="form-group">
					{!! Form::label("type", "Property type / division") !!}
					{!! Form::select("type", [
						"property" => "No division / general stay",
						"condo" => "Condo",
						"house" => "House",
					], old("type", $data->type ?? "property"), ["class"=>"form-control"]) !!}
					<small class="text-muted">Controls where this property appears on the guest site. Condo shows under /condos, House shows under /houses. Use No division for clients with one shared stay list.</small>
					<span class="text-danger">{{ $errors->first("type") }}</span>
				</div>
			</div>
			<div class="col-md-4">
				<div class="form-group">
					<label>Property categories</label>
					<div class="border rounded p-2">
						@forelse(($propertyCategories ?? collect()) as $propertyCategory)
							<label class="d-block mb-1" style="font-weight:400">
								<input type="checkbox" name="property_category_ids[]" value="{{ $propertyCategory->id }}"
									@checked(in_array($propertyCategory->id, old('property_category_ids', isset($data) ? $data->categories->pluck('id')->all() : [])))>
								{{ $propertyCategory->name }}
							</label>
						@empty
							<span class="text-muted">No categories configured. This property will appear in the main undivided list.</span>
						@endforelse
					</div>
					<small class="text-muted">Optional. Create owner-defined groups under Listings → Property categories.</small>
				</div>
			</div>
			<div class="col-md-3">
				<div class="form-group">
					{!! Form::label("status", "Listing status") !!}
					{!! Form::select("status",Helper::getStatusOptions(),null,["class"=>"form-control","required"]) !!}
					<small class="text-muted">Inactive properties are hidden from listing pages and can't be booked.</small>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- ========== SECTION 2: Listing card display ========== --}}
<div class="card card-info">
	<div class="card-header">
		<h5 class="card-title m-0">Listing card display</h5>
	</div>
	<div class="card-body">
		<div class="row">
			<div class="col-md-4">
				<div class="form-group">
					{!! Form::label("subtitle", "Listing card subtitle") !!}
					{!! Form::text("subtitle", null, ["class"=>"form-control","placeholder"=>"Enter subtitle"]) !!}
					<small class="text-muted">Secondary text on the listing card under the heading.</small>
					<span class="text-danger">{{ $errors->first("subtitle") }}</span>
				</div>
			</div>
			<div class="col-md-2">
				<div class="form-group">
					{!! Form::label("rating", "Listing card rating (e.g. 4.9)") !!}
					{!! Form::text("rating", null, ["class"=>"form-control","placeholder"=>"4.9"]) !!}
					<small class="text-muted">Listing card rating star (e.g. 4.9). For the hero badge on the detail page itself, see Detail Page tab → Hero rating.</small>
					<span class="text-danger">{{ $errors->first("rating") }}</span>
				</div>
			</div>
			<div class="col-md-2">
				<div class="form-group">
					{!! Form::label("ordering", "Sort order on listing pages") !!}
					{!! Form::number("ordering",null,["class"=>"form-control","placeholder"=>"Enter ordering"]) !!}
					<small class="text-muted">Lower numbers appear first on listing pages.</small>
					<span class="text-danger">{{ $errors->first("ordering") }}</span>
				</div>
			</div>
			<div class="col-md-4">
				<div class="form-group">
					{!! Form::label("location*") !!}
					<div class="input-group">
						{!! Form::select("location_id",ModelHelper::getLocationSelectList(),null,["class"=>"form-control","placeholder"=>"Choose Location"]) !!}
						<div class="input-group-append">
							<button type="button" class="btn btn-outline-primary" id="btn-quick-create-location-property" title="Add a new location">
								<i class="fa fa-plus"></i>
							</button>
						</div>
					</div>
					<small class="text-muted">Determines which location chip shows on the listing card and which filter group the property appears under on /booking.</small>
					<span class="text-danger">{{ $errors->first("location_id") }}</span>
				</div>
			</div>
			<div class="col-md-3">
				<div class="form-group">
					{!! Form::label("is_home", "Show on homepage?") !!}
					{!! Form::select("is_home",Helper::getYesNoOptions(),null,["class"=>"form-control"]) !!}
					<small class="text-muted">Show this property on the homepage?</small>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- ========== SECTION 3: Property specs ========== --}}
<div class="card card-info">
	<div class="card-header">
		<h5 class="card-title m-0">Property specs</h5>
	</div>
	<div class="card-body">
		<div class="row">
			<div class="col-md-2">
				<div class="form-group">
					{!! Form::label("bedroom", "Bedrooms") !!}
					{!! Form::selectRange("bedroom",1,100,null,["class"=>"form-control","placeholder"=>"Select bedroom"]) !!}
					<small class="text-muted">Shown on the listing card and detail-page hero.</small>
					<span class="text-danger">{{ $errors->first("bedroom") }}</span>
				</div>
			</div>
			<div class="col-md-2">
				<div class="form-group">
					{!! Form::label("bathroom", "Bathrooms") !!}
					{!! Form::selectRange("bathroom",1,100,null,["class"=>"form-control","placeholder"=>"select bathroom"]) !!}
					<small class="text-muted">Shown on the listing card and detail-page hero.</small>
					<span class="text-danger">{{ $errors->first("bathroom") }}</span>
				</div>
			</div>
			<div class="col-md-2">
				<div class="form-group">
					{!! Form::label("beds", "Beds") !!}
					{!! Form::selectRange("beds",1,100,null,["class"=>"form-control","placeholder"=>"select beds"]) !!}
					<small class="text-muted">Shown on the listing card and detail-page hero.</small>
					<span class="text-danger">{{ $errors->first("beds") }}</span>
				</div>
			</div>
			<div class="col-md-2">
				<div class="form-group">
					{!! Form::label("sleeps", "Sleeps (max guests)") !!}
					{!! Form::selectRange("sleeps",1,100,null,["class"=>"form-control","placeholder"=>"select sleeps"]) !!}
					<small class="text-muted">Shown on the listing card and detail-page hero.</small>
					<span class="text-danger">{{ $errors->first("sleeps") }}</span>
				</div>
			</div>
			<div class="col-md-2">
				<div class="form-group">
					{!! Form::label("area", "Area / size") !!}
					{!! Form::text("area",null,["class"=>"form-control","placeholder"=>"Enter area"]) !!}
					<small class="text-muted">Free-form text shown next to the specs (e.g. "120 m²" or "1,290 ft²").</small>
					<span class="text-danger">{{ $errors->first("area") }}</span>
				</div>
			</div>
			<div class="col-md-2">
				<div class="form-group">
					{!! Form::label("property_view", "View") !!}
					{!! Form::text("property_view",null,["class"=>"form-control","placeholder"=>"Enter property_view"]) !!}
					<small class="text-muted">Free-form text (e.g. "Ocean view", "Garden view", "Mountain view").</small>
					<span class="text-danger">{{ $errors->first("property_view") }}</span>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- ========== SECTION 4: Contact ========== --}}
<div class="card card-info">
	<div class="card-header">
		<h5 class="card-title m-0">Contact</h5>
	</div>
	<div class="card-body">
		<div class="row">
			<div class="col-md-3">
				<div class="form-group">
					{!! Form::label("mobile", "Property phone") !!}
					{!! Form::text("mobile",null,["class"=>"form-control","placeholder"=>"Enter mobile"]) !!}
					<small class="text-muted">Phone number for the property — used in admin emails.</small>
					<span class="text-danger">{{ $errors->first("mobile") }}</span>
				</div>
			</div>
			<div class="col-md-3">
				<div class="form-group">
					{!! Form::label("email", "Property email") !!}
					{!! Form::email("email",null,["class"=>"form-control","placeholder"=>"Enter email"]) !!}
					<small class="text-muted">Email for the property — used in admin emails.</small>
					<span class="text-danger">{{ $errors->first("email") }}</span>
				</div>
			</div>
			<div class="col-md-6">
				<div class="form-group">
					{!! Form::label("address", "Address") !!}
					{!! Form::textarea("address",null,["class"=>"form-control","placeholder"=>"Enter address","rows"=>2]) !!}
					<small class="text-muted">Used in the rental agreement PDF + check-in info. Not shown publicly on the listing card.</small>
					<span class="text-danger">{{ $errors->first("address") }}</span>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- ========== SECTION 5: External listings ========== --}}
<div class="card card-info">
	<div class="card-header">
		<h5 class="card-title m-0">External listings</h5>
	</div>
	<div class="card-body">
		<div class="row">
			<div class="col-md-6">
				<div class="form-group">
					{!! Form::label("vrbo_link", "VRBO listing URL") !!}
					{!! Form::text("vrbo_link",null,["class"=>"form-control","placeholder"=>"Enter VRBO LINK"]) !!}
					<small class="text-muted">Optional. URL of the VRBO listing for cross-platform reference.</small>
					<span class="text-danger">{{ $errors->first("vrbo_link") }}</span>
				</div>
			</div>
			<div class="col-md-6">
				<div class="form-group">
					{!! Form::label("airbnb_link", "Airbnb listing URL") !!}
					{!! Form::text("airbnb_link",null,["class"=>"form-control","placeholder"=>"Enter AIRBNB LINK"]) !!}
					<small class="text-muted">Optional. URL of the Airbnb listing.</small>
					<span class="text-danger">{{ $errors->first("airbnb_link") }}</span>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- ========== SECTION 6: Media ========== --}}
<div class="card card-info">
	<div class="card-header">
		<h5 class="card-title m-0">Media</h5>
	</div>
	<div class="card-body">
		<div class="row">
			<div class="col-md-6">
				<div class="form-group">
					{!! Form::label("feature_image", "Feature image (primary photo)") !!}
					{!! Form::file("feature_image",["class"=>"form-control"]) !!}
					<small class="text-muted">Primary image used everywhere — listing cards, detail page hero, booking previews, payment pages.</small>
					<span class="text-danger">{{ $errors->first("feature_image")}}</span>
					@isset($data)
						@if($data->feature_image!="")
							<div class="mt-1"><img src="{{ asset(($data->feature_image)) }}" width="200"></div>
						@endif
					@endisset
				</div>
			</div>
			<div class="col-md-6">
				<div class="form-group">
					{!! Form::label("banner_image", "Banner image (top of detail page)") !!}
					{!! Form::file("banner_image",["class"=>"form-control"]) !!}
					<small class="text-muted">Wide banner image shown at the top of the property detail page.</small>
					<span class="text-danger">{{ $errors->first("banner_image")}}</span>
					@isset($data)
						@if($data->banner_image!="")
							<div class="mt-1"><img src="{{ asset(($data->banner_image)) }}" width="200"></div>
						@endif
					@endisset
				</div>
			</div>
		</div>
	</div>
</div>

{{-- ========== SECTION 7: Description & tags ========== --}}
<div class="card card-info">
	<div class="card-header">
		<h5 class="card-title m-0">Description &amp; tags</h5>
	</div>
	<div class="card-body">
		<div class="row">
			<div class="col-md-12">
				<div class="form-group">
					{!! Form::label("Property Detail Description") !!}
					{!! Form::textarea("long_description",null,["class"=>"form-control"]) !!}
					<small class="text-muted">Long-form property description. Shown on the detail page below the booking widget. For section-by-section copy on the detail page (About, Sleep, Amenities, etc.), see the Detail Page tab.</small>
					<span class="text-danger">{{ $errors->first("long_description")}}</span>
				</div>
			</div>
			<div class="col-md-12">
				<div class="form-group">
					{!! Form::label("tags", "Tags") !!}
					{!! Form::text("tags",null,["class"=>"form-control","data-role"=>"tagsinput","placeholder"=>"oceanfront, pet-friendly, family"]) !!}
					<small class="text-muted">Comma-separated tags used by the property listing filter.</small>
					<span class="text-danger">{{ $errors->first("tags") }}</span>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- Phase 11 — Inline-create Location modal (Property edit form).
     Uses <div> not <form> to avoid HTML5's nested-form-dropping rule
     when this partial renders inside the outer Property::model form. --}}
<div class="modal fade" id="quick-create-location-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div id="quick-create-location-form">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Quick add location</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                {{-- IMPORTANT: this modal is rendered inside the property edit
                     form. HTML5 nested-form association makes any `required` /
                     `pattern` attribute on inputs here apply to the OUTER form's
                     validation. When the admin clicks the main Update button,
                     the browser tries to validate this hidden modal's required
                     input and crashes with "not focusable" because the modal is
                     display:none. Drop native validation here — the quick-create
                     JS at the bottom of this partial does its own validation
                     when the modal's own Create button fires. --}}
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" data-field="name" class="form-control">
                        <div class="text-danger small mt-1" data-field-error="name"></div>
                    </div>
                    <div class="form-group">
                        <label>Slug <small class="text-muted">(auto-generated from name if blank)</small></label>
                        <input type="text" data-field="seo_url" class="form-control">
                        <div class="text-danger small mt-1" data-field-error="seo_url"></div>
                    </div>
                    <div class="form-group mb-0">
                        <label>Image <small class="text-muted">(optional — JPG/PNG/WEBP, max 4 MB)</small></label>
                        <input type="file" data-field="image" accept="image/*" class="form-control-file">
                        <div class="text-danger small mt-1" data-field-error="image"></div>
                    </div>
                </div>
                <div class="modal-footer py-2 d-flex">
                    <a href="{{ url('admin/locations') }}" target="_blank" rel="noopener" class="text-muted small mr-auto">Edit existing →</a>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" data-quickcreate-submit>Create &amp; Select</button>
                </div>
            </div>
        </div>
    </div>
</div>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
	    var trigger = document.getElementById('btn-quick-create-location-property');
	    var modalEl = document.getElementById('quick-create-location-modal');
	    var container = document.getElementById('quick-create-location-form');
	    var dropdown = document.querySelector('select[name="location_id"]');
	    if (!trigger || !modalEl || !container || !dropdown) return;
	    if (modalEl.parentNode !== document.body) document.body.appendChild(modalEl);
	    var submitBtn = container.querySelector('[data-quickcreate-submit]');

    function resetContainer() {
        container.querySelectorAll('input[data-field]').forEach(function (el) { el.value = ''; });
        container.querySelectorAll('[data-field-error]').forEach(function (el) { el.textContent = ''; });
    }

    function forceCloseModal() {
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.removeAttribute('aria-modal');
        if (!document.querySelector('.modal.show')) {
            document.body.classList.remove('modal-open');
            document.body.style.paddingRight = '';
        }
        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.parentNode.removeChild(backdrop);
        });
    }

    function hideQuickCreateModal() {
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery(modalEl).modal('hide');
            setTimeout(function () {
                if (modalEl.classList.contains('show') || modalEl.style.display === 'block') {
                    forceCloseModal();
                }
            }, 150);
        } else {
            forceCloseModal();
        }
    }

	    trigger.addEventListener('click', function () {
	        resetContainer();
	        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.modal) {
	            container.querySelector('[data-field-error="name"]').textContent = 'Modal scripts did not load. Please refresh and try again.';
	            return;
	        }
	        window.jQuery(modalEl).modal('show');
	    });

    modalEl.addEventListener('click', function (e) {
        if (e.target.closest('[data-dismiss="modal"], .close')) {
            e.preventDefault();
            e.stopPropagation();
            hideQuickCreateModal();
        }
    });

    function doSubmit() {
        var fd = new FormData();
        container.querySelectorAll('input[data-field]').forEach(function (el) {
            var fieldName = el.dataset.field;
            if (el.type === 'file') {
                if (el.files && el.files[0]) fd.append(fieldName, el.files[0]);
            } else {
                fd.append(fieldName, el.value);
            }
        });
        fetch('{{ route("admin.locations.quick-create") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: fd,
        }).then(function (r) {
            return r.json().then(function (j) { return { status: r.status, body: j }; });
        }).then(function (resp) {
            container.querySelectorAll('[data-field-error]').forEach(function (el) { el.textContent = ''; });
            if (resp.status === 422) {
                var errs = (resp.body && resp.body.errors) || {};
                Object.keys(errs).forEach(function (field) {
                    var el = container.querySelector('[data-field-error="' + field + '"]');
                    if (el) el.textContent = (errs[field] || []).join(' ');
                });
                return;
            }
            if (resp.status >= 200 && resp.status < 300 && resp.body && resp.body.id) {
                var opt = document.createElement('option');
                opt.value = resp.body.id;
                opt.textContent = resp.body.name;
                opt.selected = true;
                dropdown.insertBefore(opt, dropdown.firstChild);
                dropdown.value = resp.body.id;
	                hideQuickCreateModal();
	            }
        }).catch(function () {
            var el = container.querySelector('[data-field-error="name"]');
            if (el) el.textContent = 'Network error — please try again.';
        });
    }

    if (submitBtn) submitBtn.addEventListener('click', doSubmit);
});
</script>
