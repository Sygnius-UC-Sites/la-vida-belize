<div class="row mt-2"><div class="col-md-12 alert alert-info"><strong>Email — where notifications go, how outgoing mail is sent &amp; when guest emails go out</strong><small class="d-block">In most cases you only need to fill in <strong>Primary admin email</strong> + the SMTP credentials. The per-event overrides at the bottom are for advanced setups that route different events to different inboxes.</small></div></div>

<div class="row">
    <div class="col-md-3"><strong>Primary admin email</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control" name="admin_email_primary" value="{{ ModelHelper::getDataFromSetting('admin_email_primary') }}" placeholder="admin@example.com">
            <small class="form-text text-muted">Your main admin inbox. Used as the destination for <strong>every</strong> system notification (contact form, bookings, payments, cancellations, reviews) <strong>unless</strong> you set a more specific address in the "Per-event overrides" section below.</small>
        </div>
    </div>
</div>

<div class="row mt-3"><div class="col-md-12 alert alert-info"><strong>Outgoing-mail identity</strong><small class="d-block">Who recipients see in the "From:" line of every transactional email.</small></div></div>

<div class="row ">
    <div class="col-md-3"><strong>Mail From — address</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control" name="mail_from" value="{{ ModelHelper::getDataFromSetting('mail_from') }}" placeholder="no-reply@example.com">
            <small class="form-text text-muted">The "From:" address recipients see. Use a domain you control so messages don't land in spam.</small>
        </div>
    </div>
</div>
<div class="row ">
    <div class="col-md-3"><strong>Mail From — name</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control" name="mail_from_name" value="{{ ModelHelper::getDataFromSetting('mail_from_name') }}" placeholder="Base Vacation Rentals">
            <small class="form-text text-muted">Display name shown in the inbox (e.g. "Your Brand &lt;no-reply@…&gt;"). Use your brand name.</small>
        </div>
    </div>
</div>
<div class="row ">
    <div class="col-md-3"><strong>Mail footer line</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control" name="mail_footer" value="{{ ModelHelper::getDataFromSetting('mail_footer') }}" placeholder="© 2026 Your Company">
            <small class="form-text text-muted">Footer text appended to every transactional email (© line, address, etc.).</small>
        </div>
    </div>
</div>

<div class="row mt-3"><div class="col-md-12 alert alert-info"><strong>SMTP server credentials</strong><small class="d-block">How the server actually sends mail. Get these from your mail provider.</small></div></div>

<div class="row ">
    <div class="col-md-3"><strong>SMTP host</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control" name="smtp_host" value="{{ ModelHelper::getDataFromSetting('smtp_host') }}" placeholder="smtp.gmail.com">
            <small class="form-text text-muted">SMTP server hostname (e.g. <code>smtp.gmail.com</code>, <code>smtp.mailgun.org</code>).</small>
        </div>
    </div>
</div>
<div class="row ">
    <div class="col-md-3"><strong>SMTP port</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control" name="smtp_port" value="{{ ModelHelper::getDataFromSetting('smtp_port') }}" placeholder="587">
            <small class="form-text text-muted"><code>587</code> for TLS, <code>465</code> for SSL, <code>25</code> for unencrypted (avoid).</small>
        </div>
    </div>
</div>
<div class="row ">
    <div class="col-md-3"><strong>Encryption</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control" name="email_encryption" value="{{ ModelHelper::getDataFromSetting('email_encryption') }}" placeholder="tls">
            <small class="form-text text-muted"><code>tls</code> (port 587) or <code>ssl</code> (port 465).</small>
        </div>
    </div>
</div>
<div class="row ">
    <div class="col-md-3"><strong>SMTP username</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control" name="smtp_user" value="{{ ModelHelper::getDataFromSetting('smtp_user') }}" placeholder="no-reply@example.com">
            <small class="form-text text-muted">Usually the same as the From address.</small>
        </div>
    </div>
</div>
<div class="row ">
    <div class="col-md-3"><strong>SMTP password</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control" name="smtp_pass" value="{{ ModelHelper::getDataFromSetting('smtp_pass') }}" placeholder="(your SMTP password or app password)">
            <small class="form-text text-muted">Password from your SMTP provider. For Gmail, generate an "App password" — your normal password won't work.</small>
        </div>
    </div>
</div>

<div class="row mt-3"><div class="col-md-12 alert alert-warning"><strong>📧 Looking for guest email scheduling?</strong><small class="d-block mt-1">Welcome package, check-in, check-out, and review email send timing — plus the actual email content + house manual fields — live on the dedicated <a href="{{ url('admin/settings/stay-communications') }}" style="font-weight:600;">Guest Emails</a> page (also in the left sidebar).</small></div></div>

<div class="row mt-3">
    <div class="col-md-12">
        <button type="button" class="btn btn-link p-0" id="mail-overrides-toggle" style="font-weight:600;color:#1e293b;text-decoration:none;">
            <i class="fa fa-chevron-right" id="mail-overrides-chevron" style="margin-right:6px;transition:transform .15s;"></i>
            Per-event overrides — route different notifications to different inboxes
        </button>
        <small class="d-block text-muted mt-1" style="margin-left:20px;">Skip this section unless you need fine-grained routing. Empty fields fall back to the Primary admin email above.</small>
    </div>
</div>

<div id="mail-overrides-section" style="display:none;" class="mt-2">

<div class="row">
    <div class="col-md-3"><strong>Contact form</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control mail-override" name="contact_us_receiving_mail" value="{{ ModelHelper::getDataFromSetting('contact_us_receiving_mail') }}" placeholder="(uses Primary admin email)">
            <small class="form-text text-muted">Override inbox for messages submitted via the public contact form.</small>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3"><strong>Newsletter signup</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control mail-override" name="newsletter_receiving_mail" value="{{ ModelHelper::getDataFromSetting('newsletter_receiving_mail') }}" placeholder="(uses Primary admin email)">
            <small class="form-text text-muted">Override inbox for newsletter subscriptions.</small>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3"><strong>Booking received</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control mail-override" name="booking_receiving_mail" value="{{ ModelHelper::getDataFromSetting('booking_receiving_mail') }}" placeholder="(uses Primary admin email)">
            <small class="form-text text-muted">Override inbox for booking enquiries and confirmations.</small>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3"><strong>Payment events</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control mail-override" name="payment_receiving_mail" value="{{ ModelHelper::getDataFromSetting('payment_receiving_mail') }}" placeholder="(uses Primary admin email)">
            <small class="form-text text-muted">Override inbox for payment success / failure events.</small>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3"><strong>Rental agreement</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control mail-override" name="rental_aggrement_receiving_mail" value="{{ ModelHelper::getDataFromSetting('rental_aggrement_receiving_mail') }}" placeholder="(uses Primary admin email)">
            <small class="form-text text-muted">Override inbox for signed rental agreements (PDF attached).</small>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3"><strong>Welcome package</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control mail-override" name="welcome_package_receiving_mail" value="{{ ModelHelper::getDataFromSetting('welcome_package_receiving_mail') }}" placeholder="(uses Primary admin email)">
            <small class="form-text text-muted">Override inbox BCC'd on welcome-package emails.</small>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3"><strong>Reminder package</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control mail-override" name="reminder_package_receiving_mail" value="{{ ModelHelper::getDataFromSetting('reminder_package_receiving_mail') }}" placeholder="(uses Primary admin email)">
            <small class="form-text text-muted">Override inbox BCC'd on check-in reminders.</small>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3"><strong>Review request</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control mail-override" name="review_receiving_mail" value="{{ ModelHelper::getDataFromSetting('review_receiving_mail') }}" placeholder="(uses Primary admin email)">
            <small class="form-text text-muted">Override inbox BCC'd on post-stay review requests.</small>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3"><strong>Cancellation</strong></div>
    <div class="col-md-9">
        <div class="form-group">
            <input type="text" class="form-control mail-override" name="cancel_receiving_mail" value="{{ ModelHelper::getDataFromSetting('cancel_receiving_mail') }}" placeholder="(uses Primary admin email)">
            <small class="form-text text-muted">Override inbox for booking cancellations.</small>
        </div>
    </div>
</div>

</div>

<script>
(function () {
    var $btn = document.getElementById('mail-overrides-toggle');
    var $sec = document.getElementById('mail-overrides-section');
    var $chev = document.getElementById('mail-overrides-chevron');
    if (!$btn || !$sec) return;
    // Auto-expand if any override is already filled in
    var anyFilled = Array.prototype.some.call(document.querySelectorAll('.mail-override'), function (i) {
        return i.value && i.value.trim() !== '';
    });
    if (anyFilled) {
        $sec.style.display = 'block';
        if ($chev) $chev.style.transform = 'rotate(90deg)';
    }
    $btn.addEventListener('click', function () {
        var open = $sec.style.display === 'block';
        $sec.style.display = open ? 'none' : 'block';
        if ($chev) $chev.style.transform = open ? 'rotate(0deg)' : 'rotate(90deg)';
    });
})();
</script>
