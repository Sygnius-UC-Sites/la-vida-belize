<div class="owner-form-grid">
    <div class="owner-form-card">
        <div class="owner-form-card-heading">FAQ category</div>
        <div class="form-group">
            {!! Form::label("name", "Category Name *") !!}
            {!! Form::text("name", null, ["class" => "form-control", "required", "placeholder" => "e.g. Booking"]) !!}
            <small class="form-text text-muted">Shown as the filter tab label on the public FAQ page.</small>
            <span class="text-danger">{{ $errors->first("name") }}</span>
        </div>

        <div class="form-group">
            {!! Form::label("seo_url", "Slug") !!}
            {!! Form::text("seo_url", null, ["class" => "form-control", "placeholder" => "auto-generated from name if left blank"]) !!}
            <small class="form-text text-muted">Lowercase identifier (e.g. <code>booking</code>). Used to link FAQs to this category. Leave blank to auto-generate.</small>
            <span class="text-danger">{{ $errors->first("seo_url") }}</span>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group mb-md-0">
                    {!! Form::label("ordering", "Order") !!}
                    {!! Form::number("ordering", null, ["class" => "form-control", "placeholder" => "0"]) !!}
                    <small class="form-text text-muted">Lower numbers appear first. Blank saves as 0.</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group mb-0">
                    {!! Form::label("publish", "Status") !!}
                    {!! Form::select("publish", ['published' => 'Published', 'draft' => 'Draft'], null, ["class" => "form-control"]) !!}
                </div>
            </div>
        </div>
    </div>
</div>
