<div class="row mt-2"><div class="col-md-12 alert alert-info"><strong>Branding &amp; assets</strong><small class="d-block">Site identity: favicon, header logo, owner / domain info, and the cache-busting asset version.</small></div></div>

<h5 class="text-warning mt-3">Favicon &amp; header logo</h5>

<div class="row">
	<div class="col-md-3"><strong>Favicon</strong></div>
	<div class="col-md-9">
		<div class="form-group">

			<input class="form-control" name="favicon" type="file" accept="image/x-icon,image/png,image/svg+xml">
			<small class="form-text text-muted"><strong>Browser-tab icon</strong> — also used as the site's logo in social previews + JSON-LD structured data. Use a square <code>.ico</code>, <code>.png</code>, or <code>.svg</code> at 32×32 or 64×64 for crisp rendering. If empty, falls back to <code>front/images/favicon.png</code>.</small>
			@if(ModelHelper::getDataFromSetting('favicon'))
				<div class="mt-2"><img src="{{ asset(ModelHelper::getDataFromSetting('favicon')) }}" width="64" style="border:1px solid #e2e8f0;border-radius:6px;padding:4px;background:#fff;" /></div>
				<input type="hidden" name="old_favicon" value="{{ModelHelper::getDataFromSetting('favicon')}} ">
			@endif

		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-3"><strong>Header logo</strong></div>
	<div class="col-md-9">
		<div class="form-group">

			<input class="form-control" name="header_logo" type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp">
			<small class="form-text text-muted">Logo used as the site's <strong>Open Graph / Twitter Card preview image</strong> when pages are shared on social. PNG with transparent background works best. Recommended ~1200×630 for clean social previews.</small>
			@if(ModelHelper::getDataFromSetting('header_logo'))
				<div class="mt-2"><img src="{{ asset(ModelHelper::getDataFromSetting('header_logo')) }}" width="200" style="border:1px solid #e2e8f0;border-radius:6px;padding:6px;background:#fff;" /></div>
			<input type="hidden" name="old_header_logo" value="{{ModelHelper::getDataFromSetting('header_logo')}} ">

			@endif
		</div>
	</div>
</div>

<h5 class="text-warning mt-4">Owner &amp; domain</h5>

<div class="row">
	<div class="col-md-3"><strong>Website domain</strong></div>
	<div class="col-md-9">
		<div class="form-group">

			<input class="form-control" name="website" placeholder="example.com" value="{{ ModelHelper::getDataFromSetting('website') }}">
			<small class="form-text text-muted">Bare domain (no <code>http://</code>, no <code>www.</code>). Used in the footer and email signatures.</small>
		</div>
	</div>
</div>
<div class="row">
	<div class="col-md-3"><strong>Owner name</strong></div>
	<div class="col-md-9">
		<div class="form-group">

			<input class="form-control" name="owner_name" placeholder="Owner Name" value="{{ ModelHelper::getDataFromSetting('owner_name') }}">
			<small class="form-text text-muted">Property owner's full name. Used in welcome emails and the "About" footer.</small>
		</div>
	</div>
</div>

<h5 class="text-warning mt-4">Cache busting</h5>

<div class="row">
	<div class="col-md-3"><strong>Asset version</strong></div>
	<div class="col-md-9">
		<div class="form-group">
			<input type="text" class="form-control" name="version_css_js" value="{{ ModelHelper::getDataFromSetting('version_css_js') }}" placeholder="2026.05.04">
			<small class="form-text text-muted">Cache-busting query string appended to CSS/JS asset URLs sitewide. Bump this when you deploy new asset versions to force visitor browsers to re-download them.</small>
		</div>
	</div>
</div>
