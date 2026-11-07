<div class="alert alert-info">
    <strong>Detail Page Content</strong><br>
    <small>Used by /{slug}. Each field below controls one heading or text block on the public detail page. Leave blank if not applicable.</small>
</div>

{{-- ========== SECTION 1: Hero (top of the detail page) ========== --}}
<div class="card card-info">
    <div class="card-header">
        <h5 class="card-title m-0">Hero (top of the detail page)</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label>Main heading (large H1 at the top)</label>
                    <input type="text" name="detail_category_heading" class="form-control" value="{{ old('detail_category_heading', $data->detail_category_heading ?? '') }}">
                    <small class="text-muted">The big text shown at the top of the property detail page.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Hero rating badge</label>
                    <input type="text" name="detail_meta_rating" class="form-control" value="{{ old('detail_meta_rating', $data->detail_meta_rating ?? '') }}">
                    <small class="text-muted">Rating shown above the property name on the detail page (e.g. "4.9").</small>
                </div>
            </div>
            <div class="col-md-9">
                <div class="form-group">
                    <label>Hero location badge</label>
                    <input type="text" name="detail_meta_location" class="form-control" value="{{ old('detail_meta_location', $data->detail_meta_location ?? '') }}">
                    <small class="text-muted">Free-form location text shown next to the hero rating (e.g. "La Fortuna, Costa Rica").</small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========== SECTION 2: Body sections (mid-page content) ========== --}}
<div class="card card-info">
    <div class="card-header">
        <h5 class="card-title m-0">Body sections (mid-page content)</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Host section heading</label>
                    <input type="text" name="detail_host_heading" class="form-control" value="{{ old('detail_host_heading', $data->detail_host_heading ?? '') }}">
                    <small class="text-muted">Heading above the "about your hosts" block (e.g. "About your hosts").</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Host details</label>
                    <textarea name="detail_host_meta" class="form-control" rows="2">{{ old('detail_host_meta', $data->detail_host_meta ?? '') }}</textarea>
                    <small class="text-muted">Sub-text under the host heading (e.g. "Hosting since 2019 · English &amp; Spanish").</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>About-section heading</label>
                    <input type="text" name="detail_about_heading" class="form-control" value="{{ old('detail_about_heading', $data->detail_about_heading ?? '') }}">
                    <small class="text-muted">Heading above the property description block (e.g. "About this property").</small>
                </div>
            </div>
            <div class="col-md-8">
                <div class="form-group">
                    <label>About-section text</label>
                    <textarea name="detail_about_text" class="form-control" rows="6">{{ old('detail_about_text', $data->detail_about_text ?? '') }}</textarea>
                    <small class="text-muted">Property description shown under the heading. Separate paragraphs with a blank line.</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Sleep-section heading</label>
                    <input type="text" name="detail_sleep_heading" class="form-control" value="{{ old('detail_sleep_heading', $data->detail_sleep_heading ?? '') }}">
                    <small class="text-muted">Heading above the sleep cards (configured below ↓).</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Amenities-section heading</label>
                    <input type="text" name="detail_amenities_heading" class="form-control" value="{{ old('detail_amenities_heading', $data->detail_amenities_heading ?? '') }}">
                    <small class="text-muted">Heading above the amenity highlights (configured below ↓).</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Reviews drawer heading</label>
                    <input type="text" name="detail_offcanvas_reviews_heading" class="form-control" value="{{ old('detail_offcanvas_reviews_heading', $data->detail_offcanvas_reviews_heading ?? '') }}">
                    <small class="text-muted">Heading shown when the guest opens the "See all reviews" panel.</small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========== SECTION 3: House-only fields ========== --}}
<div class="card card-warning">
    <div class="card-header">
        <h5 class="card-title m-0">House-only fields</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">These render only when the property's <code>type</code> is <code>house</code>. For condo records, leave blank.</p>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Share button label</label>
                    <input type="text" name="detail_share_label" class="form-control" value="{{ old('detail_share_label', $data->detail_share_label ?? '') }}">
                    <small class="text-muted">Text on the "share this listing" button (e.g. "Share", "Share this villa").</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Booking disclaimer</label>
                    <input type="text" name="detail_booking_disclaimer" class="form-control" value="{{ old('detail_booking_disclaimer', $data->detail_booking_disclaimer ?? '') }}">
                    <small class="text-muted">Small-print text shown under the booking widget.</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Map section heading</label>
                    <input type="text" name="detail_map_heading" class="form-control" value="{{ old('detail_map_heading', $data->detail_map_heading ?? '') }}">
                    <small class="text-muted">Heading above the map iframe.</small>
                </div>
            </div>
            <div class="col-md-8">
                <div class="form-group">
                    <label>Google Maps embed URL</label>
                    <textarea name="detail_map_iframe_src" class="form-control" rows="2">{{ old('detail_map_iframe_src', $data->detail_map_iframe_src ?? '') }}</textarea>
                    <small class="text-muted">Full Google Maps iframe <code>src=</code> URL (the <code>src</code> attribute, not the whole <code>&lt;iframe&gt;</code> tag).</small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========== SECTION 4: Get-quote modal description ========== --}}
<div class="card card-info">
    <div class="card-header">
        <h5 class="card-title m-0">Get-quote modal description</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    {!! Form::label("get_quote_description", "Get-quote modal description (shown when guest clicks 'Get a quote')") !!}
                    {!! Form::textarea("get_quote_description", $data->get_quote_description ?? null, ["class" => "form-control ckeditor", "rows" => 6]) !!}
                </div>
            </div>
        </div>
    </div>
</div>

<hr>
@include("admin.properties.sub.highlights")
<hr>
@include("admin.properties.sub.sleep-cards")
<hr>
@include("admin.properties.sub.amenity-highlights")

<div class="alert alert-info mt-3">
    <strong>Gallery images</strong> are managed in the <strong>Gallery Images</strong> tab.<br>
    <strong>Offcanvas amenities</strong> (full amenities drawer on the detail page) are managed in the <strong>Amenities</strong> tab.
</div>
