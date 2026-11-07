<div class="owner-form-grid">
<div class="owner-form-card">
<div class="owner-form-card-heading">Attraction details</div>
<div class="row">
    <div class="col-md-6 col-lg-4">
        <div class="form-group">
            {!! Form::label("name", "Attraction name *") !!}
            {!! Form::text("name",null,["class"=>"form-control","required"=>"required", "placeholder" => "e.g. Bell Rock Trail"]) !!}
            <span class="text-danger">{{ $errors->first("name")}}</span>
        </div>
    </div>
 
    <div class="col-md-6 col-lg-4">
        <div class="form-group">
            <label>URL slug</label>
            {!! Form::text("seo_url",null,["class"=>"form-control","placeholder"=>"auto-generated from name"]) !!}
            <small class="text-muted">Optional — auto-generated from the name if blank.</small>
            <span class="text-danger">{{ $errors->first("seo_url")}}</span>
        </div>
    </div>

    <div class="col-md-6 col-lg-4">
        <div class="form-group">
            {!! Form::label("shortDescription", "Tag / Badge Label") !!}
            {!! Form::text("shortDescription",null,["class"=>"form-control","placeholder"=>"e.g. Water Sports"]) !!}
            <span class="text-danger">{{ $errors->first("shortDescription")}}</span>
        </div>
    </div>
 


    <div class="col-md-6 col-lg-4">
        <div class="form-group">
            {!! Form::label("image", "Card image") !!}
            {!! Form::file("image",["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("image")}}</span>
             @isset($data)
                @if($data->image!="")
                     <img src="{{ asset(($data->image)) }}" class="owner-form-preview" alt=""> <br>
                     
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input custom-control-input-danger" name="remove_image" value="yes" type="checkbox" id="remove_image" >
                        <label for="remove_image" class="custom-control-label">Remove  Image</label>
                    </div>
                @endif
            @endisset
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="form-group">
            {!! Form::label("bannerImage", "Banner image") !!}
            {!! Form::file("bannerImage",["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("bannerImage")}}</span>
             @isset($data)
                @if($data->bannerImage!="")
                     <img src="{{ asset(($data->bannerImage)) }}" class="owner-form-preview" alt=""><br>
                     
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input custom-control-input-danger" name="remove_banner_image" value="yes" type="checkbox" id="remove_banner_image" >
                        <label for="remove_banner_image" class="custom-control-label">Remove Banner Image</label>
                    </div> 
                @endif
            @endisset
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="form-group">
            {!! Form::label("location_id", "Destination") !!}
            <div class="input-group">
                {!! Form::select("location_id",ModelHelper::getLocationSelectList(),null,["class"=>"form-control","placeholder"=>"Choose Location"]) !!}
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-primary" id="btn-quick-create-location-attraction" title="Add a new location">
                        <i class="fa fa-plus"></i>
                    </button>
                </div>
            </div>
            <span class="text-danger">{{ $errors->first("location_id") }}</span>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="form-group">
            {!! Form::label("category_id", "Category") !!}
            <div class="input-group">
                {!! Form::select("category_id",ModelHelper::getAttractionCategorySelectList(),null,["class"=>"form-control","placeholder"=>"Choose Category"]) !!}
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-primary" id="btn-quick-create-category-attraction" title="Add a new category">
                        <i class="fa fa-plus"></i>
                    </button>
                </div>
            </div>
            <span class="text-danger">{{ $errors->first("category_id") }}</span>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="form-group">
            {!! Form::label("Linked Property") !!}
            {!! Form::select("property_id",ModelHelper::getPropertyDropdown(),null,["class"=>"form-control","placeholder"=>"Generic — any property"]) !!}
            <small class="text-muted d-block">Optional — link this attraction to a specific property's "Nearby Attractions" widget.</small>
            <span class="text-danger">{{ $errors->first("property_id") }}</span>
        </div>
    </div>

    <div class="col-md-6 col-lg-4">
        <div class="form-group">
            {!! Form::label("type", "Link type") !!}
            {!! Form::select("type",["internal"=>"Internal page","external"=>"External link"],null,["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("type") }}</span>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-4">
        <div class="form-group">
            {!! Form::label("ordering", "Display order") !!}
            {!! Form::number("ordering",null,["class"=>"form-control","placeholder"=>"0"]) !!}
            <span class="text-danger">{{ $errors->first("ordering")}}</span>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("mobile") !!}
            {!! Form::text("mobile",null,["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("mobile")}}</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("address") !!}
            {!! Form::text("address",null,["class"=>"form-control"]) !!}
            <span class="text-danger">{{ $errors->first("address")}}</span>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('map_url', 'Google Maps URL') !!}
            {!! Form::text('map_url', null, ['class' => 'form-control', 'placeholder' => 'https://maps.google.com/?q=...']) !!}
            <span class="text-danger">{{ $errors->first('map_url') }}</span>
        </div>
    </div>

    <div class="col-md-4 d-none">
        <div class="form-group">
            {!! Form::label("templete") !!}
            {!! Form::select("templete",["common"=>"common"],null,["class"=>"form-control","required"=>"required"]) !!}
            <span class="text-danger">{{ $errors->first("templete")}}</span>
        </div>
    </div>
    <div class="col-md-4 d-none">
        <div class="form-group">
            {!! Form::label("publish") !!}
            {!! Form::select("publish",["published"=>"published","draft"=>"draft","pending"=>"pending"],'published',["class"=>"form-control","required"=>"required"]) !!}
            <span class="text-danger">{{ $errors->first("publish")}}</span>
        </div>
    </div>

   
</div>
</div>

<div class="owner-form-card">
<div class="owner-form-card-heading">Content</div>
<div class="row ">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("description", "Short description") !!}
            {!! Form::textarea("description",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("description")}}</span>
        </div>
    </div>
</div>


<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("longDescription", "Full description") !!}
            {!! Form::textarea("longDescription",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("longDescription")}}</span>
        </div>
    </div>
  
</div>
</div>

<div class="owner-form-card">
<div class="owner-form-card-heading">SEO</div>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("meta_title", "Meta title") !!}
            {!! Form::textarea("meta_title",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("meta_title")}}</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label("meta_keywords", "Meta keywords") !!}
            {!! Form::textarea("meta_keywords",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("meta_keywords")}}</span>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("meta_description", "Meta description") !!}
            {!! Form::textarea("meta_description",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("meta_description")}}</span>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("header_section", "Header scripts") !!}
            {!! Form::textarea("header_section",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("header_section")}}</span>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("footer_section", "Footer scripts") !!}
            {!! Form::textarea("footer_section",null,["class"=>"form-control","rows"=>"2"]) !!}
            <span class="text-danger">{{ $errors->first("footer_section")}}</span>
        </div>
    </div>
</div>
</div>
</div>

{{-- Phase 11 — Inline-create modals (Location + Attraction Category).
     Use <div> not <form> so HTML5's nested-form parser rule doesn't drop the inner form.
     Submission is programmatic via FormData(field collection). --}}
<div class="modal fade" id="quick-create-location-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div id="quick-create-location-form" data-enctype="multipart/form-data">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Quick add location</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
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

<div class="modal fade" id="quick-create-category-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div id="quick-create-category-form" data-enctype="multipart/form-data">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Quick add attraction category</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
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
                    <a href="{{ url('admin/attraction-categories') }}" target="_blank" rel="noopener" class="text-muted small mr-auto">Edit existing →</a>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" data-quickcreate-submit>Create &amp; Select</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function openAdminModal(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
            window.jQuery('#' + modalId).modal('show');
            return;
        }
        modal.classList.add('show');
        modal.style.display = 'block';
        modal.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show quick-create-fallback-backdrop';
        backdrop.dataset.modalId = modalId;
        document.body.appendChild(backdrop);
    }

    function closeAdminModal(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
            window.jQuery('#' + modalId).modal('hide');
        }
        modal.classList.remove('show');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.querySelectorAll('.modal-backdrop[data-modal-id="' + modalId + '"], .modal-backdrop.quick-create-fallback-backdrop').forEach(function (el) {
            el.remove();
        });
        if (!document.querySelector('.modal.show')) {
            document.body.classList.remove('modal-open');
            document.body.style.paddingRight = '';
        }
    }

    function wireQuickCreate(triggerId, modalId, containerId, dropdownName, postUrl) {
        var trigger = document.getElementById(triggerId);
        var container = document.getElementById(containerId);
        var dropdown = document.querySelector('select[name="' + dropdownName + '"]');
        if (!trigger || !container || !dropdown) return;

        var submitBtn = container.querySelector('[data-quickcreate-submit]');

        function resetContainer() {
            container.querySelectorAll('input[data-field]').forEach(function (el) {
                if (el.type === 'file') el.value = '';
                else el.value = '';
            });
            container.querySelectorAll('[data-field-error]').forEach(function (el) { el.textContent = ''; });
        }

        trigger.addEventListener('click', function () {
            resetContainer();
            openAdminModal(modalId);
        });

        container.querySelectorAll('[data-dismiss="modal"], .close').forEach(function (button) {
            button.addEventListener('click', function () {
                closeAdminModal(modalId);
            });
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
            fetch(postUrl, {
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
                    closeAdminModal(modalId);
                }
            }).catch(function () {
                var el = container.querySelector('[data-field-error="name"]');
                if (el) el.textContent = 'Network error — please try again.';
            });
        }

        if (submitBtn) submitBtn.addEventListener('click', doSubmit);
    }

    wireQuickCreate(
        'btn-quick-create-location-attraction',
        'quick-create-location-modal',
        'quick-create-location-form',
        'location_id',
        '{{ route("admin.locations.quick-create") }}'
    );
    wireQuickCreate(
        'btn-quick-create-category-attraction',
        'quick-create-category-modal',
        'quick-create-category-form',
        'category_id',
        '{{ route("admin.attraction-categories.quick-create") }}'
    );
});
</script>
