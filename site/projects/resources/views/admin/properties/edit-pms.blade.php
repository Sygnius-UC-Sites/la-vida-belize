@extends('admin.layouts')
@section('title', 'Admin')

@php
    $pmsColors = ['lodgify'=>'info','hostfully'=>'success','guesty'=>'warning','hostaway'=>'primary'];
    $pmsColor  = $pmsColors[$data->pms_source] ?? 'secondary';
    $publicUrl = \App\Support\PropertyUrl::url($data);
    $instantBookingValue = old('instant_booking_enabled', $data->instant_booking_enabled);
    $instantBookingValue = $instantBookingValue === null ? 1 : (int) $instantBookingValue;
@endphp

@section('content')
@parent
<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-12 d-flex align-items-center" style="gap:12px;">
        <a href="{{ route('properties.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fa fa-arrow-left"></i> Back to properties</a>
        <h1 class="m-0 text-dark" style="font-size:1.4rem;">{{ $data->name }}</h1>
        <span class="badge badge-{{ $pmsColor }}"><i class="fa fa-sync-alt"></i> Synced from {{ ucfirst($data->pms_source) }}</span>
      </div>
    </div>
  </div>
</section>

<section class="content">
  <div class="row">
    <div class="col-lg-8">

      {{-- Sync notice --}}
      <div class="callout callout-info" style="border-left:4px solid #17a2b8; background:#f1fbfd; padding:18px 20px; border-radius:6px; margin-bottom:20px;">
        <h5 style="margin:0 0 6px; font-weight:600;"><i class="fa fa-plug mr-1"></i> This property is managed by {{ ucfirst($data->pms_source) }}</h5>
        <p style="margin:0; color:#456;">
          Its name, description, photos, amenities, rates and calendar are pulled automatically
          from your {{ ucfirst($data->pms_source) }} account and refreshed on every sync — so they
          can't be edited here (any change would be overwritten on the next sync).
          <strong>You can customise the website-only settings below:</strong> its web address (SEO)
          and where it appears on the site.
        </p>
      </div>

      {{-- SEO + placement form (the only locally-editable fields) --}}
      <div class="card">
        <div class="card-header"><h3 class="card-title">Website settings</h3></div>
        {!! Form::model($data, ['route' => ['properties.update', $data->id], 'method' => 'put']) !!}
        <div class="card-body">

          @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
          @endif

          <div class="form-group">
            <label>Web address (slug) <span class="text-danger">*</span></label>
            <div class="input-group">
              <div class="input-group-prepend"><span class="input-group-text text-muted">{{ url('/') }}/</span></div>
              {!! Form::text('seo_url', null, ['class'=>'form-control', 'required', 'placeholder'=>'casa-magica']) !!}
            </div>
            <small class="form-text text-muted">The page lives at the URL above. Changing it changes the public link to this property.</small>
          </div>

          <hr>
          <h5 class="mb-3" style="font-weight:600;">Booking mode</h5>
          <div class="form-group">
            <label>Instant booking</label>
            {!! Form::select('instant_booking_enabled', [
              1 => 'Yes - book and pay immediately',
              0 => 'No - request to book',
            ], $instantBookingValue, ['class'=>'form-control']) !!}
            <small class="form-text text-muted">Controls whether this PMS-synced property can be reserved immediately on the website. It does not change synced PMS rates, calendar, or checkout logic.</small>
          </div>

          <hr>
          <h5 class="mb-3" style="font-weight:600;">Guest policy</h5>
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Pets</label>
              <select name="pets_allowed_override" class="form-control">
                <option value="" @selected($linkedRoom && $linkedRoom->pets_allowed === null)>Not supplied / ask host</option>
                <option value="0" @selected($linkedRoom && $linkedRoom->pets_allowed !== null && !(bool)$linkedRoom->pets_allowed)>Pets not allowed</option>
                <option value="1" @selected($linkedRoom && (bool)$linkedRoom->pets_allowed)>Pets allowed</option>
              </select>
              <small class="text-muted">Use this website override when the PMS does not provide a reliable pet policy.</small>
            </div>
            <div class="col-md-6 form-group">
              <label>Maximum pets</label>
              <input type="number" class="form-control" name="max_pets_override" min="1" max="50"
                     value="{{ old('max_pets_override', $linkedRoom->max_pets ?? '') }}">
              <small class="text-muted">Required only when pets are allowed.</small>
            </div>
          </div>

          <hr>
          <h5 class="mb-3" style="font-weight:600;">Property type & categories</h5>
          <div class="form-group">
            <label>Property type / division</label>
            {!! Form::select('type', [
              'property' => 'No division / general stay',
              'condo' => 'Condo',
              'house' => 'House',
            ], old('type', $data->type ?? 'property'), ['class'=>'form-control']) !!}
            <small class="text-muted">Controls whether the listing appears under Condos, Houses, or the undivided stay list. This is website-only and does not change PMS data.</small>
          </div>
          <div class="form-group">
            @forelse(($propertyCategories ?? collect()) as $propertyCategory)
              <label class="d-block" style="font-weight:400">
                <input type="checkbox" name="property_category_ids[]" value="{{ $propertyCategory->id }}"
                       @checked(in_array($propertyCategory->id, old('property_category_ids', $data->categories->pluck('id')->all())))>
                {{ $propertyCategory->name }}
              </label>
            @empty
              <p class="text-muted">No categories configured. The site will show one undivided property list.</p>
            @endforelse
          </div>

          <div class="form-group">
            <label>Card tag / subtitle</label>
            {!! Form::text('subtitle', null, ['class'=>'form-control', 'placeholder'=>'e.g. Scenic Views']) !!}
            <small class="form-text text-muted">Short label shown on the property card and detail banner.</small>
          </div>

          <hr>
          <h5 class="mb-3" style="font-weight:600;">Placement</h5>
          <div class="row">
            <div class="col-md-4 form-group">
              <label>Status</label>
              {!! Form::select('status', ['true'=>'Active (visible on site)', 'false'=>'Inactive (hidden)'], null, ['class'=>'form-control']) !!}
            </div>
            <div class="col-md-4 form-group">
              <label>Feature on home page?</label>
              {!! Form::select('is_home', ['true'=>'Yes', 'false'=>'No'], null, ['class'=>'form-control']) !!}
            </div>
            <div class="col-md-4 form-group">
              <label>Display order</label>
              {!! Form::number('ordering', null, ['class'=>'form-control', 'min'=>0]) !!}
            </div>
          </div>

          <hr>
          <h5 class="mb-3" style="font-weight:600;">Search engine (SEO)</h5>
          <div class="form-group">
            <label>Meta title</label>
            {!! Form::text('meta_title', null, ['class'=>'form-control', 'maxlength'=>70]) !!}
          </div>
          <div class="form-group">
            <label>Meta description</label>
            {!! Form::textarea('meta_description', null, ['class'=>'form-control', 'rows'=>2, 'maxlength'=>180]) !!}
          </div>
          <div class="form-group">
            <label>Meta keywords</label>
            {!! Form::text('meta_keywords', null, ['class'=>'form-control']) !!}
          </div>
        </div>
        <div class="card-footer">
          <button class="btn btn-success"><i class="fa fa-save"></i> Save website settings</button>
          <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary ml-1"><i class="fa fa-external-link-alt"></i> View public page</a>
        </div>
        {!! Form::close() !!}
      </div>

      {{-- Photos: the synced {{ ucfirst($data->pms_source) }} photos were copied into the
           site gallery when this listing was published. Owners often have MORE photos
           than the PMS listing — this manager lets them add extras (plus caption,
           reorder, set cover, delete). Synced content itself stays read-only. --}}
      <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fa fa-images mr-1"></i> Photos (site gallery)</h3></div>
        <div class="card-body">
          <p class="text-muted" style="font-size:13px;">
            These photos power this property's public gallery. The ones imported from
            {{ ucfirst($data->pms_source) }} arrived when the listing was published — and you can
            add extra photos here that the {{ ucfirst($data->pms_source) }} listing doesn't have.
            New uploads are saved when you click <strong>Save uploaded photos</strong>.
          </p>
          {!! Form::open(['route' => ['properties.update', $data->id], 'method' => 'put', 'files' => true, 'id' => 'pms-gallery-form']) !!}
            {{-- Re-post the current website settings untouched — the PMS update
                 branch validates/whitelists these fields. --}}
            <input type="hidden" name="seo_url" value="{{ $data->seo_url }}">
            <input type="hidden" name="status" value="{{ $data->status }}">
            <input type="hidden" name="is_home" value="{{ $data->is_home }}">
            <input type="hidden" name="ordering" value="{{ (int) $data->ordering }}">
            @foreach($data->categories as $propertyCategory)
              <input type="hidden" name="property_category_ids[]" value="{{ $propertyCategory->id }}">
            @endforeach
            @include('admin.properties.sub.gallery-images')
            <button class="btn btn-success mt-3"><i class="fa fa-upload"></i> Save uploaded photos</button>
          {!! Form::close() !!}
        </div>
      </div>
    </div>

    {{-- Sidebar: synced content snapshot (read-only) --}}
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Synced from {{ ucfirst($data->pms_source) }}</h3></div>
        <div class="card-body">
          @if($data->feature_image)
            <img src="{{ asset(ltrim($data->feature_image,'/')) }}" alt="" style="width:100%;border-radius:6px;margin-bottom:12px;object-fit:cover;max-height:160px;">
          @endif
          <table class="table table-sm mb-0">
            <tr><th>Sleeps</th><td>{{ $data->sleeps ?: '—' }}</td></tr>
            <tr><th>Bedrooms</th><td>{{ $data->bedroom }}</td></tr>
            <tr><th>Bathrooms</th><td>{{ $data->bathroom }}</td></tr>
            <tr><th>From</th><td>${{ number_format((float)$data->standard_rate) }}/night</td></tr>
          </table>
          @if($linkedRoom)
            <a href="{{ url('admin/pms_rooms/'.$linkedRoom->id.'/edit') }}" class="btn btn-sm btn-outline-info btn-block mt-3">
              <i class="fa fa-database"></i> View full PMS record (calendar, fees)
            </a>
          @endif
          <p class="text-muted mt-3 mb-0" style="font-size:12px;">
            To change the name, description, photos or rates, edit the listing in your
            {{ ucfirst($data->pms_source) }} account, then run a sync.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>
@stop

@section("css")
@parent
<link rel="stylesheet" type="text/css" href="{{ asset('drag-drop-image-uploader/src/image-uploader.css') }}">
@stop

@section("js")
@parent
<script src="{{ asset('drag-drop-image-uploader/src/image-uploader.js') }}"></script>
<script>
    $(function () {
        $('.input-images-2').imageUploader({
            imagesInputName: 'images',
            extensions: ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp'],
            mimes: ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp']
        });
    });
</script>
@stop
