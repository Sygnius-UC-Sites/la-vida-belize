<div class="row">
    <div class="col-md-12">
        <h4 class="text-primary mb-3">PMS Integrations & Webhooks</h4>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fa-check-circle mr-1"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('danger'))
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fa-exclamation-circle mr-1"></i> {{ session('danger') }}
            </div>
        @endif
        
        <div class="alert alert-info">
            <div class="row mb-3">
                <div class="col-md-3"><strong>Webhook Base URL</strong><br><small class="text-muted">Public URL this server is reachable at.<br>Local: use ngrok. Staging/Prod: your domain.</small></div>
                <div class="col-md-9">
                    <input type="text" class="form-control" name="webhook_base_url"
                        value="{{ ModelHelper::getDataFromSetting('webhook_base_url') }}"
                        placeholder="https://your-domain.com  or  https://abc123.ngrok-free.app">
                    @if(ModelHelper::getDataFromSetting('webhook_base_url'))
                        <small class="text-success mt-1 d-block">
                            <i class="fa fa-check-circle"></i>
                            Active: <code>{{ ModelHelper::getDataFromSetting('webhook_base_url') }}</code>
                        </small>
                    @else
                        <small class="text-warning mt-1 d-block">Not set — webhooks cannot be registered until this is saved.</small>
                    @endif
                </div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Webhook Token</strong><br><small class="text-muted">Secret embedded in webhook URLs for security.</small></div>
                <div class="col-md-9">
                    <input type="text" class="form-control" name="lodgify_webhook_token"
                        value="{{ ModelHelper::getDataFromSetting('lodgify_webhook_token') }}"
                        placeholder="E.g. some-random-string">
                </div>
            </div>
        </div>

        @php
            // Feature flags: only the PMS providers sold to this customer are
            // shown (dev panel → "PMS providers"). Developers see all four.
            $pmsShow = fn ($p) => \App\Helper\FeatureFlags::isDeveloper()
                || \App\Helper\FeatureFlags::enabled('pms_provider_' . $p);
        @endphp
        @if($pmsShow('lodgify'))
        <hr>

        <h5 class="text-warning">
            Lodgify
            <a href="{{ url('admin/setting/sync-pms-data/lodgify') }}" class="btn btn-sm btn-info float-right" onclick="return confirm('Sync Lodgify properties, rooms, and bookings?')">Sync Lodgify Data</a>
        </h5>
        <div class="row">
            <div class="col-md-3"><strong>API Key</strong></div>
            <div class="col-md-9">
                <div class="form-group">
                    <input type="text" class="form-control" name="lodgify_api_key" value="{{ ModelHelper::getDataFromSetting('lodgify_api_key') }}" placeholder="Lodgify API Key">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3"><strong>Webhook URL</strong></div>
            <div class="col-md-9">
                <p class="text-muted" style="word-break: break-all;">
                    <code>{{ url('/lodgify/new_property?token=' . ModelHelper::getDataFromSetting('lodgify_webhook_token')) }}</code><br>
                    <code>{{ url('/lodgify/updated_property?token=' . ModelHelper::getDataFromSetting('lodgify_webhook_token')) }}</code><br>
                    <code>{{ url('/lodgify/booking_change?token=' . ModelHelper::getDataFromSetting('lodgify_webhook_token')) }}</code><br>
                    ...and more (auto-registered)
                </p>
            </div>
        </div>

        @endif
        @if($pmsShow('hostfully'))
        <hr>

        <h5 class="text-warning">
            Hostfully
            <a href="{{ url('admin/setting/sync-pms-data/hostfully') }}" class="btn btn-sm btn-info float-right" onclick="return confirm('Sync Hostfully properties, rooms, and bookings?')">Sync Hostfully Data</a>
        </h5>
        <div class="row">
            <div class="col-md-3"><strong>Agency UID</strong></div>
            <div class="col-md-9">
                <div class="form-group">
                    <input type="text" class="form-control" name="hostfully_agency_uid" value="{{ ModelHelper::getDataFromSetting('hostfully_agency_uid') }}" placeholder="Hostfully Agency UID">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3"><strong>API Key</strong></div>
            <div class="col-md-9">
                <div class="form-group">
                    <input type="text" class="form-control" name="hostfully_api_key" value="{{ ModelHelper::getDataFromSetting('hostfully_api_key') }}" placeholder="Hostfully API Key">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3"><strong>Webhook URL</strong></div>
            <div class="col-md-9">
                <p class="text-muted" style="word-break: break-all;">
                    <code>{{ url('/webhooks/hostfully?token=' . ModelHelper::getDataFromSetting('lodgify_webhook_token')) }}</code>
                </p>
            </div>
        </div>

        @endif
        @if($pmsShow('guesty'))
        <hr>

        <h5 class="text-warning">
            Guesty
            <a href="{{ url('admin/setting/sync-pms-data/guesty') }}" class="btn btn-sm btn-info float-right" onclick="return confirm('Sync Guesty properties, rooms, and bookings?')">Sync Guesty Data</a>
        </h5>
        <div class="row">
            <div class="col-md-3"><strong>Client ID</strong></div>
            <div class="col-md-9">
                <div class="form-group">
                    <input type="text" class="form-control" name="guesty_client_id" value="{{ ModelHelper::getDataFromSetting('guesty_client_id') }}" placeholder="Guesty Client ID">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3"><strong>API Secret Key</strong></div>
            <div class="col-md-9">
                <div class="form-group">
                    <input type="text" class="form-control" name="guesty_api_key" value="{{ ModelHelper::getDataFromSetting('guesty_api_key') }}" placeholder="Guesty API Secret Key">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3"><strong>Webhook URL</strong></div>
            <div class="col-md-9">
                <p class="text-muted" style="word-break: break-all;">
                    <code>{{ url('/webhooks/guesty?token=' . ModelHelper::getDataFromSetting('lodgify_webhook_token')) }}</code>
                </p>
            </div>
        </div>

        @endif
        @if($pmsShow('hostaway'))
        <hr>

        <h5 class="text-warning">
            Hostaway
            <a href="{{ url('admin/setting/sync-pms-data/hostaway') }}" class="btn btn-sm btn-info float-right" onclick="return confirm('Sync Hostaway properties, rooms, and bookings?')">Sync Hostaway Data</a>
        </h5>
        <div class="row">
            <div class="col-md-3"><strong>Account ID</strong></div>
            <div class="col-md-9">
                <div class="form-group">
                    <input type="text" class="form-control" name="hostaway_account_id" value="{{ ModelHelper::getDataFromSetting('hostaway_account_id') }}" placeholder="Hostaway Account ID">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3"><strong>API Key</strong></div>
            <div class="col-md-9">
                <div class="form-group">
                    <input type="text" class="form-control" name="hostaway_api_key" value="{{ ModelHelper::getDataFromSetting('hostaway_api_key') }}" placeholder="Hostaway API Key">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3"><strong>Webhook URL</strong></div>
            <div class="col-md-9">
                <p class="text-muted" style="word-break: break-all;">
                    <code>{{ url('/webhooks/hostaway?token=' . ModelHelper::getDataFromSetting('lodgify_webhook_token')) }}</code>
                    <br><small class="text-danger">* Must be registered manually in the Hostaway Dashboard.</small>
                </p>
            </div>
        </div>

        @endif
        <hr>

        @if(\App\Helper\FeatureFlags::isDeveloper() || \App\Helper\FeatureFlags::enabled('pms_review_sync'))
        <div class="card bg-light mt-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fa fa-star"></i> Sync Guest Reviews</h5>
                <p class="card-text">Pulls guest reviews from Guesty, Hostaway, and Hostfully and saves them to the testimonials table. Reviews will then appear on each property page. (Lodgify does not expose a reviews API on standard plans.)</p>
                <a href="{{ url('admin/setting/sync-pms-reviews') }}"
                   class="btn btn-success"
                   onclick="return confirm('Sync guest reviews from all PMS providers?')">
                    <i class="fa fa-star"></i> Sync Reviews from All PMS
                </a>
            </div>
        </div>

        @endif
        @if(\App\Helper\FeatureFlags::isDeveloper() || \App\Helper\FeatureFlags::enabled('pms_webhooks'))
        <div class="card bg-light mt-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fa fa-plug"></i> Webhook Auto-Registration</h5>
                <p class="card-text">
                    Registers your site's webhook URLs with Hostfully and Guesty via API.
                    Hostaway and Lodgify will show the URLs to paste in their dashboards.
                    <br><strong>Save settings first</strong>, then click the button.
                </p>

                @php $baseUrl = ModelHelper::getDataFromSetting('webhook_base_url'); @endphp

                @if(!$baseUrl)
                    <div class="alert alert-warning py-2">
                        <i class="fa fa-exclamation-triangle"></i>
                        <strong>Webhook Base URL not set.</strong> Add it above and save before registering.
                    </div>
                @else
                    <p class="text-muted mb-2">
                        Will register: <code>{{ $baseUrl }}/webhooks/{pms}?token=...</code>
                    </p>
                @endif

                @if(ModelHelper::getDataFromSetting('pms_webhooks_registered_at'))
                    <p class="text-success mb-3">
                        <i class="fa fa-check-circle"></i>
                        <strong>Last registered:</strong> {{ ModelHelper::getDataFromSetting('pms_webhooks_registered_at') }}
                    </p>
                @endif

                <a href="{{ url('admin/setting/register-pms-webhooks') }}"
                   class="btn btn-primary {{ !$baseUrl ? 'disabled' : '' }}"
                   onclick="return {{ $baseUrl ? "confirm('Register webhooks with Hostfully and Guesty? Hostaway and Lodgify URLs will be shown in the response.')" : 'false' }}">
                    <i class="fa fa-plug"></i> Register Webhooks with PMS
                </a>

                @if(session('webhook_register_output'))
                    <div class="mt-3">
                        <h6>Registration Output:</h6>
                        <pre class="bg-dark text-light p-3 rounded" style="font-size:.8rem; max-height:300px; overflow-y:auto;">{{ session('webhook_register_output') }}</pre>
                    </div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
