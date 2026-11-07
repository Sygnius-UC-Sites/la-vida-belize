@php
    $imagePreview = isset($data) && !empty($data->image) ? asset($data->image) : null;
    $bannerPreview = isset($data) && !empty($data->bannerImage) ? asset($data->bannerImage) : null;
@endphp

<div class="owner-form-grid">
    <div class="owner-form-card">
        <div class="owner-form-card-heading">Category details</div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label('name', 'Category name *') !!}
                    {!! Form::text('name', null, ['class' => 'form-control', 'required' => 'required', 'placeholder' => 'e.g. Hiking & trails']) !!}
                    <span class="text-danger">{{ $errors->first('name') }}</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label('seo_url', 'URL slug') !!}
                    {!! Form::text('seo_url', null, ['class' => 'form-control', 'placeholder' => 'Generated from name if left blank']) !!}
                    <small class="text-muted">Used in the attraction category URL. Leave blank unless you need a specific slug.</small>
                    <span class="text-danger">{{ $errors->first('seo_url') }}</span>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    {!! Form::label('description', 'Short description') !!}
                    {!! Form::textarea('description', null, ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'A short owner-facing summary for this attraction category.']) !!}
                    <span class="text-danger">{{ $errors->first('description') }}</span>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group mb-0">
                    {!! Form::label('longDescription', 'Full description') !!}
                    {!! Form::textarea('longDescription', null, ['class' => 'form-control', 'rows' => 5]) !!}
                    <span class="text-danger">{{ $errors->first('longDescription') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="owner-form-card">
        <div class="owner-form-card-heading">Images</div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label('image', 'Card image') !!}
                    {!! Form::file('image', ['class' => 'form-control']) !!}
                    @if($imagePreview)
                        <img src="{{ $imagePreview }}" alt="" class="owner-form-preview">
                        <div class="custom-control custom-checkbox mt-2">
                            <input class="custom-control-input custom-control-input-danger" name="remove_image" value="yes" type="checkbox" id="remove_image">
                            <label for="remove_image" class="custom-control-label">Remove image</label>
                        </div>
                    @endif
                    <span class="text-danger">{{ $errors->first('image') }}</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label('bannerImage', 'Banner image') !!}
                    {!! Form::file('bannerImage', ['class' => 'form-control']) !!}
                    @if($bannerPreview)
                        <img src="{{ $bannerPreview }}" alt="" class="owner-form-preview">
                        <div class="custom-control custom-checkbox mt-2">
                            <input class="custom-control-input custom-control-input-danger" name="remove_banner_image" value="yes" type="checkbox" id="remove_banner_image">
                            <label for="remove_banner_image" class="custom-control-label">Remove banner image</label>
                        </div>
                    @endif
                    <span class="text-danger">{{ $errors->first('bannerImage') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="owner-form-card">
        <div class="owner-form-card-heading">SEO</div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label('meta_title', 'Meta title') !!}
                    {!! Form::textarea('meta_title', null, ['class' => 'form-control', 'rows' => 2]) !!}
                    <span class="text-danger">{{ $errors->first('meta_title') }}</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label('meta_keywords', 'Meta keywords') !!}
                    {!! Form::textarea('meta_keywords', null, ['class' => 'form-control', 'rows' => 2]) !!}
                    <span class="text-danger">{{ $errors->first('meta_keywords') }}</span>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group mb-0">
                    {!! Form::label('meta_description', 'Meta description') !!}
                    {!! Form::textarea('meta_description', null, ['class' => 'form-control', 'rows' => 2]) !!}
                    <span class="text-danger">{{ $errors->first('meta_description') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

{!! Form::hidden('templete', 'common') !!}
{!! Form::hidden('publish', 'published') !!}
