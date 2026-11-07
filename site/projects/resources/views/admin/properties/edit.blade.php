@extends('admin.layouts')
@section('title', 'Admin')
@php
    $name  = "Properties";
    $route = "properties";

    /* ─── Hero card data resolution ───
       Pull a representative photo for the property hero. Try the first
       gallery image, then feature_image, then banner_image. Each field
       stores a path relative to the htdocs root (uploads/...) per the
       project's flattened-public layout, so asset() resolves them. */
    $heroPhoto = null;
    if (isset($data)) {
        $firstGallery = \App\Models\PropertyGallery::where('property_id', $data->id)
            ->orderBy('sorting')->orderBy('id')->first();
        if ($firstGallery && $firstGallery->image) {
            $heroPhoto = asset(ltrim($firstGallery->image, '/'));
        } elseif (!empty($data->feature_image)) {
            $heroPhoto = asset(ltrim($data->feature_image, '/'));
        } elseif (!empty($data->banner_image)) {
            $heroPhoto = asset(ltrim($data->banner_image, '/'));
        }
    }

    $locationName = (isset($data) && $data->location) ? $data->location->name : null;
    $isLive       = isset($data) && in_array((string) $data->status, ['true', '1'], true);
    $typeLabel    = 'Property';

    $publicUrl = null;
    if (isset($data) && !empty($data->seo_url)) {
        $publicUrl = \App\Support\PropertyUrl::url($data);
    }

    $setupPhotoCount = isset($data)
        ? \App\Models\PropertyGallery::where('property_id', $data->id)->where('status', 'true')->count()
        : 0;
    $setupAmenityGroupIds = isset($data)
        ? \App\Models\PropertyAmenityGroup::where('property_id', $data->id)->where('status', 'true')->pluck('id')
        : collect();
    $setupAmenityCount = $setupAmenityGroupIds->isNotEmpty()
        ? \App\Models\PropertyAmenity::whereIn('property_amenity_id', $setupAmenityGroupIds)->where('status', 'true')->count()
        : 0;
    $setupRateRuleCount = isset($data)
        ? \App\Models\PropertyRateGroup::where('property_id', $data->id)->count()
        : 0;
    $setupDayOverrideCount = isset($data)
        ? \App\Models\PropertyRate::where('property_id', $data->id)->count()
        : 0;
    $setupFeeCount = isset($data)
        ? \App\Models\PropertyFee::where('property_id', $data->id)->where('status', 'active')->count()
        : 0;

    $setupItems = [
        [
            'id' => 'listing',
            'label' => 'Basics',
            'detail' => 'Name, URL, capacity, and listing card details',
            'done' => !empty($data->name) && !empty($data->seo_url) && ((int) ($data->sleeps ?? 0) > 0) && ((int) ($data->bedroom ?? 0) > 0),
        ],
        [
            'id' => 'photos',
            'label' => 'Photos',
            'detail' => 'Cover and gallery images',
            'done' => $setupPhotoCount > 0 || !empty($data->feature_image) || !empty($data->banner_image),
        ],
        [
            'id' => 'amenities',
            'label' => 'Amenities',
            'detail' => 'Amenity library picks and highlights',
            'done' => $setupAmenityCount > 0,
        ],
        [
            'id' => 'calendar',
            'label' => 'Rates & calendar',
            'detail' => 'Default rate, min nights, seasonal rules, blocks',
            'done' => ((float) ($data->standard_rate ?? 0) > 0) || $setupRateRuleCount > 0 || $setupDayOverrideCount > 0,
        ],
        [
            'id' => 'fees',
            'label' => 'Fees & deposits',
            'detail' => 'Cleaning, pets, add-ons, tax, security deposit',
            'done' => ((float) ($data->cleaning_fee ?? 0) > 0)
                || ((float) ($data->pet_fee ?? 0) > 0)
                || ((float) ($data->guest_fee ?? 0) > 0)
                || ((float) ($data->tax ?? 0) > 0)
                || ((float) ($data->security_deposit_amount ?? 0) > 0)
                || $setupFeeCount > 0,
        ],
        [
            'id' => 'booking',
            'label' => 'Booking settings',
            'detail' => 'Instant/request booking and stay-length rules',
            'done' => ((int) ($data->min_stay ?? 0) > 0) || $data->instant_booking_enabled !== null,
        ],
        [
            'id' => 'rules',
            'label' => 'Rules & cancellation',
            'detail' => 'Pets, smoking, parties, accessibility, cancellation text',
            'done' => !empty($data->cancellation_policy_type) || !empty($data->cancellation_policy),
        ],
        [
            'id' => 'stay',
            'label' => 'Guest stay info',
            'detail' => 'Welcome, check-in, checkout, Wi-Fi, parking',
            'done' => !empty($data->welcome_package_description)
                || !empty($data->checkin_arrival_window)
                || !empty($data->checkin_wifi_name)
                || !empty($data->checkout_departure_time),
        ],
        [
            'id' => 'seo',
            'label' => 'Preview & SEO',
            'detail' => 'Public page copy, meta title, description',
            'done' => !empty($data->long_description) && (!empty($data->meta_title) || !empty($data->meta_description)),
        ],
    ];
    if (!\App\Helper\FeatureFlags::isDeveloper()) {
        $setupItems = array_values(array_filter($setupItems, function ($item) {
            return $item['id'] === 'listing'
                || \App\Helper\FeatureFlags::enabled('prop_section_' . $item['id']);
        }));
    }
    $setupDoneCount = collect($setupItems)->where('done', true)->count();
    $setupPercent = count($setupItems) ? (int) round(($setupDoneCount / count($setupItems)) * 100) : 0;
@endphp

@section('content')
@parent

<div class="ls-studio">

    {{-- ─── Hero strip ─── tight one-row header. No photo banner — the
         page is for editing data, not admiring the listing. The thumbnail
         is small on the left as a quick visual anchor. --}}
    <header class="ls-hero">
        <a href="{{ route('properties.index') }}" class="ls-hero-back" title="All listings">
            <i class="fa fa-arrow-left"></i>
        </a>

        @if($heroPhoto)
            <img src="{{ $heroPhoto }}" alt="" class="ls-hero-thumb">
        @endif

        <div class="ls-hero-id">
            <div class="ls-hero-eyebrow">
                <span class="ls-status-pill {{ $isLive ? '' : 'is-draft' }}">
                    <span class="ls-status-dot"></span> {{ $isLive ? 'Live' : 'Draft' }}
                </span>
                <span>{{ $typeLabel }}</span>
                @if($locationName)
                    <span>·</span><span>{{ $locationName }}</span>
                @endif
            </div>
            <h1>{{ $data->name ?? 'Untitled listing' }}</h1>
        </div>

        <div class="ls-hero-meta">
            @if(!empty($data->bedroom))
                <span>{{ $data->bedroom }} bed{{ $data->bedroom != 1 ? 's' : '' }}</span>
            @endif
            @if(!empty($data->bathroom))
                <span>{{ $data->bathroom }} bath{{ $data->bathroom != 1 ? 's' : '' }}</span>
            @endif
            @if(!empty($data->sleeps))
                <span>Sleeps {{ $data->sleeps }}</span>
            @endif
            @if(!empty($data->area))
                <span>{{ $data->area }} sq ft</span>
            @endif
        </div>

        <div class="ls-hero-actions">
            <div class="ls-save-status" id="ls-save-status" aria-live="polite">
                <span class="ls-save-dot"></span>
                <span class="ls-save-label" id="ls-save-label">All changes saved</span>
            </div>
            @if($publicUrl)
                <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="ls-hero-action">
                    <i class="fa fa-external-link-alt"></i> View public
                </a>
            @endif
        </div>
    </header>

    <section class="ls-setup-panel" data-pe-nav-host>
        <div class="ls-setup-summary">
            <div>
                <span class="ls-setup-kicker">Listing setup</span>
                <h2>{{ $setupDoneCount }} of {{ count($setupItems) }} steps ready</h2>
            </div>
            <strong>{{ $setupPercent }}%</strong>
        </div>
        <div class="ls-setup-meter" aria-hidden="true"><span style="width: {{ $setupPercent }}%"></span></div>
        <div class="ls-setup-grid">
            @foreach($setupItems as $item)
                <a href="#{{ $item['id'] }}" data-pe-nav="{{ $item['id'] }}" class="ls-setup-item {{ $item['done'] ? 'is-done' : '' }}">
                    <span class="ls-setup-check">{{ $item['done'] ? '✓' : '○' }}</span>
                    <span>
                        <strong>{{ $item['label'] }}</strong>
                        <small>{{ $item['detail'] }}</small>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ─── Form (no card chrome, no sticky save bar) ─── --}}
    {!! Form::model($data, [
        'route'  => [$route.'.update', $data->id],
        'files'  => 'true',
        'method' => 'put',
        'id'     => 'property-edit-form',
    ]) !!}

        @include("admin.".$route.".form")

    {!! Form::close() !!}

</div>

@stop

@section("css")
@parent
<link rel="stylesheet" type="text/css" href="{{ asset('drag-drop-image-uploader/src/image-uploader.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css" integrity="sha512-ELV+xyi8IhEApPS/pSj66+Jiw+sOT1Mqkzlh8ExXihe4zfqbWkxPRi8wptXIO9g73FSlhmquFlUOuMSoXz5IRw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="{{ asset('admin-fullcalendar/main.min.css') }}">
<style>
    .gaurav-class {
        border: 1px solid #e8e3da;
        margin: 10px 0;
        padding: 14px;
        border-radius: 8px;
        background: #fafaf9;
    }
    .ls-setup-panel {
        background: #fff;
        border: 1px solid #e6e8ee;
        border-radius: 12px;
        padding: 16px;
        margin: 14px 0 18px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
    }
    .ls-setup-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
    }
    .ls-setup-kicker {
        display: inline-block;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 3px;
    }
    .ls-setup-summary h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
    }
    .ls-setup-summary strong {
        font-size: 20px;
        color: #15253f;
    }
    .ls-setup-meter {
        height: 8px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
        margin: 12px 0 14px;
    }
    .ls-setup-meter span {
        display: block;
        height: 100%;
        background: #15253f;
        border-radius: inherit;
    }
    .ls-setup-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }
    .ls-setup-item {
        display: flex;
        gap: 9px;
        align-items: flex-start;
        color: #475569;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px;
        text-decoration: none;
        background: #f8fafc;
    }
    .ls-setup-item:hover {
        color: #0f172a;
        background: #f1f5f9;
        text-decoration: none;
    }
    .ls-setup-item.is-done {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }
    .ls-setup-check {
        width: 20px;
        flex: 0 0 20px;
        color: #15803d;
        font-weight: 700;
        line-height: 1.2;
    }
    .ls-setup-item strong,
    .ls-setup-item small {
        display: block;
    }
    .ls-setup-item strong {
        font-size: 13px;
        color: #0f172a;
    }
    .ls-setup-item small {
        margin-top: 2px;
        color: #64748b;
        font-size: 11.5px;
        line-height: 1.35;
    }
    @media (max-width: 1200px) {
        .ls-setup-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 768px) {
        .ls-setup-grid { grid-template-columns: 1fr; }
    }
</style>
@stop

@section("js")
@parent
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js" integrity="sha512-57oZ/vW8ANMjR/KQ6Be9v/+/h6bq9/l3f0Oc7vn6qMqyhvPd1cvKBRWWpzu0QoneImqr2SkmO4MSqU+RpHom3Q==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ asset('drag-drop-image-uploader/src/image-uploader.js') }}"></script>
<script src="{{ asset('admin-fullcalendar/main.min.js') }}"></script>
<script>
    $(function() {
        var dropIndex;
        $("#image-list").sortable({
            update: function(event, ui) {
                dropIndex = ui.item.index();
            }
        });
        $('#submit').click(function(e) {
            var captionIdsArray = [];
            $('#image-list .gaurav-class input').each(function(index) {
                var id = $(this).attr('id');
                var split_id = id.split("_");
                captionIdsArray.push({
                    "id": split_id[1],
                    "value": $(this).val()
                });
            });
            $.ajax({
                url: '{{ route("update-property-caption-and-sorting") }}',
                type: 'post',
                data: {
                    captionidsarray: captionIdsArray,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    $("#txtresponse").css('display', 'inline-block');
                    $("#txtresponse").text(response);
                }
            });
            e.preventDefault();
        });

        // CKEditor's class-based auto-init may have already attached editors
        // to textareas with class="ckeditor". Skip the explicit replace if so;
        // otherwise apply our config (filebrowser upload route).
        var ckCfg = {
            allowedContent: true,
            filebrowserUploadUrl: "{{route('ckeditor.upload', ['_token' => csrf_token() ])}}",
            filebrowserUploadMethod: 'form'
        };
        ['get_quote_description','long_description','cancellation_policy','welcome_package_description'].forEach(function (name) {
            if (CKEDITOR.instances[name]) return;
            if (!document.getElementById(name)) return;
            CKEDITOR.replace(name, ckCfg);
        });

        $('.input-images-2').imageUploader({
            imagesInputName: 'images',
            extensions: ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp'],
            mimes: ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp']
        });
    });

    $(document).on("click", ".delete-image-product", function() {
        $id = $(this).data("id");
        data = {
            _token: "{{ csrf_token() }}",
            id: $id
        };
        $that = $(this);
        url = "{{ route('image-delete-asset') }}";
        $.post(url, data, function(data) {
            $that.parent('div').parent('div').remove();
        });
    });
</script>
<script>
    // Highlights / sleep-cards / amenity-highlights repeaters (kept verbatim
    // from the previous edit.blade.php — these power the "Add" buttons in
    // the corresponding sub-partials).

    // === Highlights repeater ===
    $(document).on("click", ".add-highlight-data", function() {
        var html = `
            <div class="row gaurav-delete-highlight">
                <input type="hidden" name="highlight_id[]" value="">
                <div class="col-md-1 d-flex align-items-center">
                    <span class="drag-handle text-muted" style="cursor:grab;font-size:18px;padding:6px 8px"><i class="fa fa-grip-vertical"></i></span>
                </div>
                <div class="col-md-2">
                    {!! Form::select("highlight_icon[]", ["layers"=>"layers","lock"=>"lock","mappin"=>"mappin"], null, ["class"=>"form-control"]) !!}
                </div>
                <div class="col-md-3">
                    {!! Form::text("highlight_title[]", null, ["class"=>"form-control","placeholder"=>"Title"]) !!}
                </div>
                <div class="col-md-5">
                    {!! Form::text("highlight_description[]", null, ["class"=>"form-control","placeholder"=>"Description"]) !!}
                </div>
                <div class="col-md-1">
                    <a href="javascript:;" class="delete-highlight-row btn btn-danger"><i class="fa fa-trash"></i></a>
                </div>
                <div class="col-md-12"><br></div>
            </div>
        `;
        $("#highlights-area-section").append(html);
    });

    $(document).on("click", ".delete-highlight-row", function() {
        $(this).parents(".gaurav-delete-highlight").remove();
    });

    $(document).on("click", ".delete-highlight-db", function() {
        var that = $(this);
        if (confirm("Are you sure want to delete this highlight?")) {
            $.post("{{ route('delete-property-highlight') }}", {
                _token: "{{ csrf_token() }}",
                id: that.data("id")
            }, function() { that.parents(".gaurav-delete-highlight").remove(); });
        }
    });

    // === Sleep cards repeater ===
    $(document).on("click", ".add-sleep-card-data", function() {
        var html = `
            <div class="row gaurav-delete-sleep-card">
                <input type="hidden" name="sleep_card_id[]" value="">
                <div class="col-md-1 d-flex align-items-center">
                    <span class="drag-handle text-muted" style="cursor:grab;font-size:18px;padding:6px 8px"><i class="fa fa-grip-vertical"></i></span>
                </div>
                <div class="col-md-4">
                    {!! Form::text("sleep_card_title[]", null, ["class"=>"form-control","placeholder"=>"Title"]) !!}
                </div>
                <div class="col-md-6">
                    {!! Form::text("sleep_card_text[]", null, ["class"=>"form-control","placeholder"=>"Text"]) !!}
                </div>
                <div class="col-md-1">
                    <a href="javascript:;" class="delete-sleep-card-row btn btn-danger"><i class="fa fa-trash"></i></a>
                </div>
                <div class="col-md-12"><br></div>
            </div>
        `;
        $("#sleep-cards-area-section").append(html);
    });

    $(document).on("click", ".delete-sleep-card-row", function() {
        $(this).parents(".gaurav-delete-sleep-card").remove();
    });

    $(document).on("click", ".delete-sleep-card-db", function() {
        var that = $(this);
        if (confirm("Are you sure want to delete this sleep card?")) {
            $.post("{{ route('delete-property-sleep-card') }}", {
                _token: "{{ csrf_token() }}",
                id: that.data("id")
            }, function() { that.parents(".gaurav-delete-sleep-card").remove(); });
        }
    });

    // === Amenity highlights repeater ===
    $(document).on("click", ".add-amenity-highlight-data", function() {
        var html = `
            <div class="row gaurav-delete-amenity-highlight">
                <input type="hidden" name="amenity_highlight_id[]" value="">
                <div class="col-md-1 d-flex align-items-center">
                    <span class="drag-handle text-muted" style="cursor:grab;font-size:18px;padding:6px 8px"><i class="fa fa-grip-vertical"></i></span>
                </div>
                <div class="col-md-3">
                    {!! Form::text("amenity_highlight_emoji[]", null, ["class"=>"form-control","placeholder"=>"🏖️"]) !!}
                </div>
                <div class="col-md-7">
                    {!! Form::text("amenity_highlight_label[]", null, ["class"=>"form-control","placeholder"=>"Beachfront"]) !!}
                </div>
                <div class="col-md-1">
                    <a href="javascript:;" class="delete-amenity-highlight-row btn btn-danger"><i class="fa fa-trash"></i></a>
                </div>
                <div class="col-md-12"><br></div>
            </div>
        `;
        $("#amenity-highlights-area-section").append(html);
    });

    $(document).on("click", ".delete-amenity-highlight-row", function() {
        $(this).parents(".gaurav-delete-amenity-highlight").remove();
    });

    $(document).on("click", ".delete-amenity-highlight-db", function() {
        var that = $(this);
        if (confirm("Are you sure want to delete this amenity highlight?")) {
            $.post("{{ route('delete-property-amenity-highlight') }}", {
                _token: "{{ csrf_token() }}",
                id: that.data("id")
            }, function() { that.parents(".gaurav-delete-amenity-highlight").remove(); });
        }
    });
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Tag every input inside `[data-pe-section]` blocks as belonging to the
    // outer property-edit-form. Necessary because some sub-partials (fees,
    // amenities, calendar) contain their own nested <form>s for AJAX panels —
    // without this, HTML5 closes the outer form at the first nested <form>
    // and inputs after it lose their form-ownership.
    function tagOuterFormOwner() {
        document.querySelectorAll('[data-pe-section] input, [data-pe-section] select, [data-pe-section] textarea').forEach(function (el) {
            var ancestorForm = el.closest('form');
            if (ancestorForm && ancestorForm.id !== 'property-edit-form') return;
            el.setAttribute('form', 'property-edit-form');
        });
    }
    tagOuterFormOwner();
    document.addEventListener('click', function (e) {
        if (e.target.closest('.add-highlight-data, .add-sleep-card-data, .add-amenity-highlight-data, .add-space-data')) {
            setTimeout(tagOuterFormOwner, 0);
        }
    });

    // Booking Rules — override-checkbox toggles the matching textarea.
    function toggleEditor(checkboxId, textareaName) {
        var checkbox = document.getElementById(checkboxId);
        var textarea = document.querySelector('textarea[name="' + textareaName + '"]');
        if (!checkbox || !textarea) return;
        checkbox.addEventListener('change', function () {
            if (this.checked) {
                textarea.removeAttribute('disabled');
            } else {
                textarea.value = '';
                textarea.setAttribute('disabled', 'disabled');
            }
        });
    }
    toggleEditor('override_template', 'rental_agreement_template_html');
    toggleEditor('override_rules',    'rental_agreement_house_rules_html');

    // SEO URL field validation messages.
    var seo = document.getElementById('seo-url-input');
    if (seo) {
        seo.addEventListener('invalid', function () {
            seo.setCustomValidity(seo.validity.patternMismatch
                ? 'Use lowercase letters, numbers, and hyphens only (e.g. demo-beach-house).'
                : 'Please fill out this field.');
        });
        seo.addEventListener('input', function () { seo.setCustomValidity(''); });
    }
});
</script>
{{-- Phase 2 + 3 — rental-agreement support: Word→HTML upload + merge-token
     chip bar. Both partials are shared with the global settings page so the
     behaviour is identical. --}}
@include('admin.settings._partials.rental-doc-upload-js')
@include('admin.settings._partials.merge-token-bar-js')
@stop
