<div class="row mt-2"><div class="col-md-12 alert alert-info"><strong>Site basics — contact info shown in header &amp; footer</strong><small class="d-block">These values render across the public site (footer, contact page, "Book on Airbnb" buttons). Change here and they update everywhere.</small></div></div>

<div class="row">
	<div class="col-md-3"><strong>Address</strong></div>
	<div class="col-md-9">
		<div class="form-group">
			<textarea class="form-control" name="address" placeholder="123 Beach Road, Costa Rica">{{ ModelHelper::getDataFromSetting('address') }}</textarea>
			<small class="form-text text-muted">Postal address shown in the footer and on the contact page. Multi-line is fine; line breaks become <code>&lt;br&gt;</code>.</small>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-3"><strong>Phone</strong></div>
	<div class="col-md-9">
		<div class="form-group">
			<input type="text" class="form-control" name="mobile" value="{{ ModelHelper::getDataFromSetting('mobile') }}" placeholder="+1 (555) 123-4567">
			<small class="form-text text-muted">Main phone number. Shown in the header strip and footer as a clickable <code>tel:</code> link. Include country code.</small>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-3"><strong>Public email</strong></div>
	<div class="col-md-9">
		<div class="form-group">
			<input type="text" class="form-control" name="email" value="{{ ModelHelper::getDataFromSetting('email') }}" placeholder="hello@example.com">
			<small class="form-text text-muted">Public-facing email shown in the footer and on contact pages as a clickable <code>mailto:</code> link. (Outgoing mail uses the SMTP settings under the Mail tab.)</small>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-3"><strong>Copyright</strong></div>
	<div class="col-md-9">
		<div class="form-group">
			<textarea class="form-control" name="copyright" placeholder="© 2026 Your Company. All rights reserved.">{{ ModelHelper::getDataFromSetting('copyright') }}</textarea>
			<small class="form-text text-muted">Copyright line at the bottom of every page. HTML is allowed.</small>
		</div>
	</div>
</div>

<div class="row">
    <div class="col-md-3"><strong>Google Maps embed URL</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control" name="map" value="{{ ModelHelper::getDataFromSetting('map') }}" placeholder="https://www.google.com/maps/embed?pb=…">
            <small class="form-text text-muted">Embed URL for the contact page map. Get this from Google Maps → Share → Embed → copy the iframe's <code>src</code> value.</small>
        </div>
    </div>
</div>

