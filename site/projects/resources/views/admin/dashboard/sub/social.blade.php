<div class="row mt-2"><div class="col-md-12 alert alert-info"><strong>Social profile links</strong><small class="d-block">URLs for the social icons in the site footer. Paste the full URL including <code>https://</code>. Leave a field blank to hide that icon.</small></div></div>

<div class="row">
	<div class="col-md-3"><i class="fab fa-fw fa-facebook"></i><strong> Facebook</strong></div>
	<div class="col-md-9">
		<div class="form-group">
			<input type="text" class="form-control" name="facebook" value="{{ ModelHelper::getDataFromSetting('facebook') }}" placeholder="https://www.facebook.com/yourpage">
			<small class="form-text text-muted">Full Facebook page URL. The Facebook icon in the footer links here when set.</small>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-3"><i class="fab fa-fw fa-instagram"></i><strong> Instagram</strong></div>
	<div class="col-md-9">
		<div class="form-group">
			<input type="text" class="form-control"  name="instagram" value="{{ ModelHelper::getDataFromSetting('instagram') }}"  placeholder="https://www.instagram.com/yourhandle">
			<small class="form-text text-muted">Full Instagram profile URL. The Instagram icon in the footer links here when set.</small>
		</div>
	</div>
</div>
