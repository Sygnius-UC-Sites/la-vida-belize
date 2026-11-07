@php
    $cmsSeoUrl = isset($data) ? (string) ($data->seo_url ?? '') : '';
    $cmsTemplate = isset($data) ? (string) ($data->templete ?? '') : '';
    $cmsPageKey = $cmsTemplate !== '' ? $cmsTemplate : $cmsSeoUrl;
    $templateOptions = Helper::getTempletes();
    $usesCustomCmsTemplate = $cmsTemplate !== '' && !array_key_exists($cmsTemplate, $templateOptions);
    $isHomeCmsPage = $cmsPageKey === 'home';
@endphp

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('name') !!}
            {!! Form::text('name', null, ['class' => 'form-control', 'required' => 'required']) !!}
            <small class="form-text text-muted">Internal name shown in this admin list. Public visitors do not see this — they see the meta title and page headings instead.</small>
            <span class="text-danger">{{ $errors->first('name') }}</span>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            <label>SEO URL ( Only A-z,0-9,_,- are allowed)</label>
            {!! Form::text('seo_url', null, [
                'class' => 'form-control',
                'pattern' => '[a-zA-Z0-9-_]+',
                'title' => 'Enter Valid SEO URL',
                'oninvalid' =>
                    "this.setCustomValidity('SEO URL is not Valid Please enter first letter must be a-z and only accept chars a-z 0-9,-,_')",
                'onchange' => "try{setCustomValidity('')}catch(e){}",
                'oninput' => "setCustomValidity(' ')",
                'required' => 'required',
            ]) !!}
            <small class="form-text text-muted">URL slug for this page (e.g. <code>our-story</code> resolves at <code>/our-story</code>). Lowercase, hyphens or underscores only — never change once a page is live or you'll break inbound links.</small>
            <span class="text-danger">{{ $errors->first('seo_url') }}</span>
        </div>
    </div>

    {{-- ═══ INNER-PAGE BANNER (every page) ═══
         The active design has an inner-page banner photo at the top of every
         non-home page (FAQ, Contact, Attractions, Testimonial, Our Story,
         etc.). This block lets admins upload a custom banner per page. If
         left empty, the design's default `breadcrumb.avif` is used. --}}
    @isset($data)
        @if (!$isHomeCmsPage)
            <div class="col-md-12 alert alert-info mt-2">
                <h4>🖼️ Page Banner Image</h4>
                <small class="d-block">Banner photo shown at the top of this page (above the breadcrumbs). JPG / PNG / WebP, ~1920×600 works well. Leave empty to use the site default.</small>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    {!! Form::label('inner_banner_image', 'Upload Page Banner') !!}
                    {!! Form::file('inner_banner_image', ['class' => 'form-control']) !!}
                    @php $currentBanner = $data->inner_banner_image ?? $data->bannerImage ?? null; @endphp
                    @if (!empty($currentBanner))
                        <img src="{{ str_starts_with($currentBanner, 'http') ? $currentBanner : asset($currentBanner) }}" width="320" class="mt-2 d-block" style="border-radius:6px;">
                        <div class="custom-control custom-checkbox">
                            <input class="custom-control-input custom-control-input-danger" name="remove_inner_banner_image" value="yes" type="checkbox" id="remove_inner_banner_image">
                            <label for="remove_inner_banner_image" class="custom-control-label">Remove banner (revert to site default)</label>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    @endisset

    {{-- Page heading & banner text (inner pages: attractions / gallery / faq / reviews / contact / about) --}}
    @isset($data)
    @if(in_array($data->seo_url, ['attractions','gallery','faqs','faq','reviews','testimonials','contact-us','about-us']))
        <div class="col-md-12 alert alert-info"><h4>📝 Page Heading</h4><small class="d-block">The banner title + the section eyebrow / title / sub-text shown at the top of this page.</small></div>
        <div class="col-md-12"><div class="form-group">{!! Form::label('fw_banner_title','Banner Title') !!}{!! Form::text('fw_banner_title', null, ['class'=>'form-control']) !!}</div></div>
        <div class="col-md-4"><div class="form-group">{!! Form::label('fw_section_eyebrow','Section Eyebrow') !!}{!! Form::text('fw_section_eyebrow', null, ['class'=>'form-control']) !!}</div></div>
        <div class="col-md-8"><div class="form-group">{!! Form::label('fw_section_title','Section Title') !!}{!! Form::text('fw_section_title', null, ['class'=>'form-control']) !!}</div></div>
        <div class="col-md-12"><div class="form-group">{!! Form::label('fw_section_sub','Section Sub-text') !!}{!! Form::textarea('fw_section_sub', null, ['class'=>'form-control','rows'=>2]) !!}</div></div>
        @if($data->seo_url=='attractions' || str_starts_with((string) $data->seo_url, 'attractions-'))<div class="col-md-12 alert alert-secondary"><small>📍 Category cards on /attractions are managed in <a href="{{ url('admin/attraction-categories') }}">Attraction Categories</a>. Venue cards on the inner pages are managed in <a href="{{ url('admin/attractions') }}">Attractions</a>.</small></div>@endif
        @if(in_array($data->seo_url,['faqs','faq']))<div class="col-md-12 alert alert-secondary"><small>📍 The Q&amp;A items on this page are managed in <a href="{{ url('admin/faqs') }}">FAQs</a>.</small></div>@endif
    @endif
    @endisset

    {{-- ═══ Microcopy: editable button / tab / label text per page (ui_labels JSON) ═══ --}}
    @isset($data)
    @php
        $uiLabelDefaults = [
            'home' => [
                ['key'=>'btn_check_availability','value'=>'Check Availability'],
                ['key'=>'bb_checkin','value'=>'CHECK IN'],
                ['key'=>'bb_checkout','value'=>'CHECK OUT'],
                ['key'=>'bb_guests','value'=>'GUESTS'],
                ['key'=>'btn_view_more_photos','value'=>'View More Photos'],
                ['key'=>'btn_read_more','value'=>'Read More'],
                ['key'=>'tab_all','value'=>'All Questions'],
                ['key'=>'tab_booking','value'=>'Booking'],
                ['key'=>'tab_property','value'=>'Property'],
                ['key'=>'tab_amenities','value'=>'Amenities'],
                ['key'=>'tab_policies','value'=>'Policies'],
            ],
            'contact-us' => [
                ['key'=>'contact_form_heading','value'=>'Contact <span>Form</span>'],
                ['key'=>'contact_loc_label','value'=>'Our Location'],
                ['key'=>'contact_call_label','value'=>'Call Us'],
                ['key'=>'contact_email_label','value'=>'Get In Touch'],
                ['key'=>'form_name','value'=>'Full Name'],
                ['key'=>'form_email','value'=>'Email'],
                ['key'=>'form_phone','value'=>'Phone number'],
                ['key'=>'form_message','value'=>'Message'],
                ['key'=>'btn_submit','value'=>'Submit'],
            ],
            'faqs' => [
                ['key'=>'tab_all','value'=>'All Questions'],
                ['key'=>'tab_booking','value'=>'Booking'],
                ['key'=>'tab_property','value'=>'Property'],
                ['key'=>'tab_amenities','value'=>'Amenities'],
                ['key'=>'tab_policies','value'=>'Policies'],
                ['key'=>'btn_view_more','value'=>'View More'],
            ],
            'gallery' => [
                ['key'=>'btn_view_more_photos','value'=>'View More Photos'],
            ],
            'booking-confirmed' => [
                ['key'=>'bc_stay_title','value'=>'Your stay'],
                ['key'=>'bc_checkin','value'=>'Check-in'],
                ['key'=>'bc_checkout','value'=>'Check-out'],
                ['key'=>'bc_nights','value'=>'Nights'],
                ['key'=>'bc_guests','value'=>'Guests'],
                ['key'=>'bc_guest_info_title','value'=>'Guest information'],
                ['key'=>'bc_next_title','value'=>'What happens next'],
                ['key'=>'bc_next_step1','value'=>'We send a welcome email with directions, gate codes, Wi-Fi credentials, and tips for your stay.'],
                ['key'=>'bc_next_step2','value'=>"We'll follow up with a quick review request. Your refundable deposit (if any) is released within 7 days."],
                ['key'=>'bc_cancel_title','value'=>'Cancellation policy'],
                ['key'=>'bc_help_title','value'=>'Need help with your reservation?'],
                ['key'=>'bc_help_email','value'=>'Email us'],
                ['key'=>'bc_help_call','value'=>'Call us'],
                ['key'=>'bc_help_faqs','value'=>'FAQs'],
                ['key'=>'bc_receipt_title','value'=>'Receipt'],
                ['key'=>'bc_paid_today','value'=>'Paid today'],
                ['key'=>'bc_balance_due','value'=>'Balance due'],
                ['key'=>'bc_print','value'=>'Print or save as PDF'],
                ['key'=>'bc_back_home','value'=>'Back to home'],
            ],
        ];
        $pageLabels = $uiLabelDefaults[$cmsSeoUrl] ?? $uiLabelDefaults[$cmsPageKey] ?? [];
    @endphp
    @if(!empty($pageLabels))
        <div class="col-md-12"><hr><div class="alert alert-info"><h4>🔤 Button &amp; Label Text</h4><small class="d-block">Every button, tab, and label on this page. Edit the <strong>Text</strong> column; leave the <strong>Key</strong> column unchanged (it links each label to its spot on the page).</small></div></div>
        <div class="col-md-12">
            @include('admin.cms.partials.json-editor', [
                'jeName' => 'ui_labels',
                'jeValue' => (!empty($data->ui_labels) ? $data->ui_labels : json_encode($pageLabels)),
                'jeTitle' => 'Editable text on this page',
                'jeHint' => 'Change the Text column only.',
                'jeAddLabel' => '+ Add label',
                'jeSchema' => [
                    ['key'=>'key','label'=>'Key (do not change)','type'=>'text','col'=>4],
                    ['key'=>'value','label'=>'Text shown on the page','type'=>'text','col'=>8],
                ],
            ])
        </div>
    @endif
    @endisset

    @isset($data)
        @if ($isHomeCmsPage)

        {{-- ═══════════════════════════════════════════════════════════════
             HOMEPAGE
             Sections mirror the design ZIP one-to-one. Old-client sections
             (Condos / Houses / Experiences Split / Amenities / Location /
             Testimonials Heading / CTA Book Now) have been removed because
             they are not used by the active design and confused QA.
             ═════════════════════════════════════════════════════════════════ --}}

        {{-- ═══ HERO SECTION ═══ --}}
        <div class="col-md-12 alert alert-info">
            <h4>🌄 Hero Section</h4>
            <small class="d-block">Top-of-page hero: background photo carousel + eyebrow + 2-line headline + sub-text. The booking bar below is auto-rendered.</small>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('hero_eyebrow', 'Hero Eyebrow') !!}
                {!! Form::text('hero_eyebrow', null, ['class' => 'form-control', 'placeholder' => 'YOUR DESTINATION · PRIVATE STAYS']) !!}
                <small class="form-text text-muted">Small uppercase label above the headline (e.g. location + tagline).</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('hero_sub', 'Hero Sub-text') !!}
                {!! Form::text('hero_sub', null, ['class' => 'form-control', 'placeholder' => 'A unique underground retreat crafted for comfort, adventure, and lasting memories.']) !!}
                <small class="form-text text-muted">One short sentence under the headline. ~10–18 words works best.</small>
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('hero_heading', 'Hero Heading (use &lt;br&gt; for a line break)') !!}
                {!! Form::text('hero_heading', null, ['class' => 'form-control', 'placeholder' => 'Stay Somewhere<br>Extraordinary']) !!}
                <small class="form-text text-muted">The big headline. Insert a literal <code>&lt;br&gt;</code> where you want the line break (e.g. <code>Stay Somewhere&lt;br&gt;Extraordinary</code>).</small>
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('hero_carousel_images', 'Hero Carousel Images') !!}
                @include('admin.cms.partials.json-editor', [
                    'jeName'   => 'hero_carousel_images',
                    'jeValue'  => $data->hero_carousel_images ?? null,
                    'jeTitle'  => 'Background photo carousel behind the hero headline',
                    'jeHint'   => 'Upload 4–6 high-quality landscape photos at ≥1600px wide. You can also paste an existing image path if needed. Leave empty to use the neutral base placeholder.',
                    'jeAddLabel' => '+ Add hero image',
                    'jeStringArray' => true,
                    'jeSchema' => [
                        ['key'=>'value', 'label'=>'Hero image', 'type'=>'image', 'col'=>12, 'placeholder'=>'Upload an image or paste uploads/cms/hero-1.jpg'],
                    ],
                ])
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('hero_badges', 'Hero Trust Badges') !!}
                @include('admin.cms.partials.json-editor', [
                    'jeName'   => 'hero_badges',
                    'jeValue'  => $data->hero_badges ?? null,
                    'jeTitle'  => 'Small trust badges under the booking bar (e.g. Superhost, Guest Favourite)',
                    'jeHint'   => 'Upload the badge artwork for each trust badge. Leave empty to hide badges until the client has real trust assets.',
                    'jeAddLabel' => '+ Add badge image',
                    'jeStringArray' => true,
                    'jeSchema' => [
                        ['key'=>'value', 'label'=>'Badge image', 'type'=>'image', 'col'=>12, 'placeholder'=>'Upload an image or paste uploads/cms/trust-badge.png'],
                    ],
                ])
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('whatsapp_url', 'WhatsApp Link (floating button)') !!}
                {!! Form::text('whatsapp_url', null, ['class' => 'form-control', 'placeholder' => 'https://wa.me/19168976706']) !!}
            </div>
        </div>

        {{-- ═══ OUR STORY SECTION ═══ --}}
        <div class="col-md-12 alert alert-info mt-3">
            <h4>📖 Our Story Section</h4>
            <small class="d-block">Brand storytelling block on the homepage: eyebrow + headline + two body paragraphs + two photos (main + accent).</small>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('story_eyebrow', 'Eyebrow') !!}
                {!! Form::text('story_eyebrow', null, ['class' => 'form-control', 'placeholder' => 'Our Story']) !!}
                <small class="form-text text-muted">Small uppercase label above the heading.</small>
            </div>
        </div>
        <div class="col-md-9">
            <div class="form-group">
                {!! Form::label('story_title', 'Heading (use &lt;br&gt; for a line break)') !!}
                {!! Form::text('story_title', null, ['class' => 'form-control', 'placeholder' => 'A Dream Decades<br>in the Making']) !!}
                <small class="form-text text-muted">Section headline. Insert <code>&lt;br&gt;</code> to break to a second line.</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('story_body', 'Story Paragraph #1') !!}
                {!! Form::textarea('story_body', null, ['class' => 'form-control', 'rows' => '5']) !!}
                <small class="form-text text-muted">First paragraph of the story block.</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('story_body_2', 'Story Paragraph #2') !!}
                {!! Form::textarea('story_body_2', null, ['class' => 'form-control', 'rows' => '5']) !!}
                <small class="form-text text-muted">Second paragraph of the story block.</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('story_image', 'Story Image (main)') !!}
                {!! Form::file('story_image', ['class' => 'form-control']) !!}
                <small class="form-text text-muted">Primary photo in the story block. JPG / PNG / WebP, ~1200×900 works well.</small>
                @isset($data)
                    @if ($data->story_image != '')
                        <img src="{{ asset($data->story_image) }}" width="200" class="mt-2">
                        <div class="custom-control custom-checkbox">
                            <input class="custom-control-input custom-control-input-danger" name="remove_story_image" value="yes" type="checkbox" id="remove_story_image">
                            <label for="remove_story_image" class="custom-control-label">Remove main story image</label>
                        </div>
                    @endif
                @endisset
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('story_image_accent', 'Story Image (accent)') !!}
                {!! Form::file('story_image_accent', ['class' => 'form-control']) !!}
                <small class="form-text text-muted">Second photo in the story block — typically shown beside or behind the main one. Same dimensions work well.</small>
                @isset($data)
                    @php $accent = $data->story_image_accent ?: $data->location_image; @endphp
                    @if (!empty($accent))
                        <img src="{{ asset($accent) }}" width="200" class="mt-2">
                        <div class="custom-control custom-checkbox">
                            <input class="custom-control-input custom-control-input-danger" name="remove_story_image_accent" value="yes" type="checkbox" id="remove_story_image_accent">
                            <label for="remove_story_image_accent" class="custom-control-label">Remove accent story image</label>
                        </div>
                    @endif
                @endisset
            </div>
        </div>

        {{-- ═══ RETREATS SECTION ═══ --}}
        <div class="col-md-12 alert alert-info mt-3">
            <h4>🏡 Retreats Section</h4>
            <small class="d-block">Heading above the property grid. The actual property cards come from the <strong>Properties</strong> admin module (active properties are auto-listed).</small>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('retreats_eyebrow', 'Eyebrow') !!}
                {!! Form::text('retreats_eyebrow', null, ['class' => 'form-control', 'placeholder' => 'Our Retreats']) !!}
            </div>
        </div>
        <div class="col-md-8">
            <div class="form-group">
                {!! Form::label('retreats_title', 'Heading') !!}
                {!! Form::text('retreats_title', null, ['class' => 'form-control', 'placeholder' => 'Discover Your Private Retreat']) !!}
            </div>
        </div>

        {{-- ═══ HOMEPAGE GALLERY SECTION ═══ --}}
        <div class="col-md-12 alert alert-info mt-3">
            <h4>🖼️ Homepage Gallery Section</h4>
            <small class="d-block">A preview strip of gallery photos on the homepage with a "View More" link to the full <code>/gallery</code> page. Leave images empty to auto-pull from PropertyGallery.</small>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('gallery_eyebrow', 'Eyebrow') !!}
                {!! Form::text('gallery_eyebrow', null, ['class' => 'form-control', 'placeholder' => 'Gallery']) !!}
            </div>
        </div>
        <div class="col-md-8">
            <div class="form-group">
                {!! Form::label('gallery_title', 'Heading') !!}
                {!! Form::text('gallery_title', null, ['class' => 'form-control', 'placeholder' => 'Step Inside the Shire']) !!}
            </div>
        </div>
        <div class="col-md-12 alert alert-secondary">
            <small>🖼️ The homepage Gallery preview shows the first 9 images from your <a href="{{ url('admin/gallery') }}">Gallery</a> module (same source as the full Gallery page). Add, caption, reorder, or remove images there and both update together.</small>
        </div>

        {{-- ═══ BETTER THAN AIRBNB SECTION ═══ --}}
        <div class="col-md-12 alert alert-info mt-3">
            <h4>⭐ Better Than Airbnb Section</h4>
            <small class="d-block">4-card value-prop strip. Each card has an icon, title, and short body. Icon can be a Bootstrap icon name (<code>bi-piggy-bank</code>) OR a full image URL/path.</small>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('better_title', 'Heading') !!}
                {!! Form::text('better_title', null, ['class' => 'form-control', 'placeholder' => 'Book Direct Benefits']) !!}
            </div>
        </div>
        <div class="col-md-8">
            <div class="form-group">
                {!! Form::label('better_sub', 'Sub-text') !!}
                {!! Form::text('better_sub', null, ['class' => 'form-control', 'placeholder' => 'By booking directly on our website you get more benefits']) !!}
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('better_items', 'Value-Prop Cards (typically 4)') !!}
                @include('admin.cms.partials.json-editor', [
                    'jeName'   => 'better_items',
                    'jeValue'  => $data->better_items ?? null,
                    'jeTitle'  => 'Cards rendered in the Better-than-Airbnb section',
                    'jeHint'   => 'Icon: click "Upload image" to use your own icon/image, OR type a Bootstrap Icons class (e.g. bi-piggy-bank, bi-tags, bi-headset). Both render correctly.',
                    'jeAddLabel' => '+ Add value-prop card',
                    'jeSchema' => [
                        ['key'=>'icon',  'label'=>'Icon (image or class)', 'type'=>'image',    'col'=>3, 'placeholder'=>'bi-piggy-bank or upload'],
                        ['key'=>'title', 'label'=>'Title',                 'type'=>'text',     'col'=>3, 'placeholder'=>'Better rates'],
                        ['key'=>'body',  'label'=>'Body',                  'type'=>'textarea', 'col'=>6, 'rows'=>2, 'placeholder'=>'Avoiding fees from booking platforms…'],
                    ],
                ])
            </div>
        </div>

        {{-- ═══ EXPLORE / ATTRACTIONS SECTION ═══ --}}
        <div class="col-md-12 alert alert-info mt-3">
            <h4>🗺️ Explore Section</h4>
            <small class="d-block">Heading + tabbed attractions grid. Tabs are sourced from the <strong>Attractions → Categories</strong> admin module, and cards from <strong>Attractions</strong>.</small>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('explore_eyebrow', 'Eyebrow') !!}
                {!! Form::text('explore_eyebrow', null, ['class' => 'form-control', 'placeholder' => "Discover What's Nearby"]) !!}
            </div>
        </div>
        <div class="col-md-8">
            <div class="form-group">
                {!! Form::label('explore_title', 'Heading') !!}
                {!! Form::text('explore_title', null, ['class' => 'form-control', 'placeholder' => 'Stay Somewhere Extraordinary']) !!}
            </div>
        </div>
        <div class="col-md-8">
            <div class="form-group">
                {!! Form::label('explore_sub', 'Sub-text') !!}
                {!! Form::text('explore_sub', null, ['class' => 'form-control', 'placeholder' => 'Discover local attractions, scenic spots, and experiences…']) !!}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('explore_map_url', 'Map URL') !!}
                {!! Form::text('explore_map_url', null, ['class' => 'form-control', 'placeholder' => 'https://maps.google.com/?q=…']) !!}
                <small class="form-text text-muted">Used by the "View on Map" link.</small>
            </div>
        </div>

        {{-- ═══ FAQ SECTION (homepage) ═══ --}}
        <div class="col-md-12 alert alert-info mt-3">
            <h4>❓ Homepage FAQs</h4>
            <small class="d-block">Heading above the FAQ accordion on the home page. The actual question/answer rows are managed in the <strong>FAQs</strong> admin module.</small>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('faq_eyebrow', 'Eyebrow') !!}
                {!! Form::text('faq_eyebrow', null, ['class' => 'form-control', 'placeholder' => 'Your Questions Answered']) !!}
            </div>
        </div>
        <div class="col-md-8">
            <div class="form-group">
                {!! Form::label('faq_heading', 'Heading') !!}
                {!! Form::text('faq_heading', null, ['class' => 'form-control', 'placeholder' => 'Everything You Need to Know']) !!}
            </div>
        </div>

        {{-- ═══ TESTIMONIALS (Elfsight Reviews) ═══ --}}
        <div class="col-md-12 alert alert-info mt-3">
            <h4>💬 Reviews (Elfsight Widget)</h4>
            <small class="d-block">The site uses the Elfsight Reviews widget — paste your widget App ID here. When set, the manual testimonial cards are <strong>hidden</strong> across the site so you don't see duplicates.</small>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('elfsight_widget_id', 'Elfsight Widget App ID') !!}
                {!! Form::text('elfsight_widget_id', null, ['class' => 'form-control', 'placeholder' => 'fa9fc250-1d25-4935-800e-8c8581a28e3e']) !!}
                <small class="form-text text-muted">Find this in your Elfsight dashboard. The widget renders on the homepage testimonials slot and on the <code>/reviews</code> page.</small>
            </div>
        </div>

        @endif
    @endisset
    @isset($data)
        @if (in_array($data->seo_url, ['our-story','about-us']))

        {{-- About / Our Story page (matches design): banner + the "A Dream Decades" story section.
             Banner title lives in the Page Heading block above (fw_banner_title). --}}
        <div class="col-md-12"><hr><h4 class="mb-2">📖 Our Story Section</h4></div>
        <div class="col-md-4"><div class="form-group">{!! Form::label('story_eyebrow','Eyebrow') !!}{!! Form::text('story_eyebrow', null, ['class'=>'form-control','placeholder'=>'Our Story']) !!}</div></div>
        <div class="col-md-8"><div class="form-group">{!! Form::label('story_title','Headline (HTML ok)') !!}{!! Form::text('story_title', null, ['class'=>'form-control','placeholder'=>'A Dream Decades<br>in the Making']) !!}</div></div>
        <div class="col-md-12"><div class="form-group">{!! Form::label('story_body','Story paragraph 1') !!}{!! Form::textarea('story_body', null, ['class'=>'form-control','rows'=>3]) !!}</div></div>
        <div class="col-md-12"><div class="form-group">{!! Form::label('story_body_2','Further paragraphs (HTML — wrap each in &lt;p class="story-body"&gt;)') !!}{!! Form::textarea('story_body_2', null, ['class'=>'form-control','rows'=>5]) !!}<small class="form-text text-muted">Leave blank to show the neutral base copy.</small></div></div>
        <div class="col-md-6"><div class="form-group">{!! Form::label('story_image','Main image (path or URL)') !!}{!! Form::text('story_image', null, ['class'=>'form-control','placeholder'=>'https://…']) !!}</div></div>
        <div class="col-md-6"><div class="form-group">{!! Form::label('story_image_accent','Accent image (path or URL)') !!}{!! Form::text('story_image_accent', null, ['class'=>'form-control','placeholder'=>'https://…']) !!}</div></div>

        @endif
    @endisset

    {{-- ═══ Gallery cards (home + gallery pages) — matches design, editable ═══ --}}
    @isset($data)
    @if ($isHomeCmsPage || in_array($cmsSeoUrl, ['gallery']) || in_array($cmsPageKey, ['gallery']))
        <div class="col-md-12"><hr><div class="alert alert-info"><h4>🖼️ Gallery Photos</h4><small class="d-block">The captioned photo cards shown in the Gallery (and the homepage gallery preview). Upload or paste an image, set its caption + title. Leave empty to keep the gallery empty until the client has approved photography.</small></div></div>
        <div class="col-md-12">
            @include('admin.cms.partials.json-editor', [
                'jeName'   => 'gallery_cards',
                'jeValue'  => $data->gallery_cards ?? null,
                'jeTitle'  => 'Gallery photo cards',
                'jeAddLabel' => '+ Add gallery photo',
                'jeSchema' => [
                    ['key'=>'img',     'label'=>'Photo',   'type'=>'image',    'col'=>4],
                    ['key'=>'title',   'label'=>'Title',   'type'=>'text',     'col'=>3, 'placeholder'=>'Bright & Cheery'],
                    ['key'=>'caption', 'label'=>'Caption', 'type'=>'text',     'col'=>5, 'placeholder'=>'Main deck at sunset'],
                ],
            ])
        </div>
    @endif
    @endisset

    @isset($data)
        @if ($data->seo_url == 'condos')

        {{-- ═══ HERO ═══ --}}
        <div class="col-md-12 alert alert-info">
            <h4>🏝️ Hero Section</h4>
            <small class="d-block">Top banner of this category listing page.</small>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('shortDescription', 'Hero Sub-text') !!}
                {!! Form::textarea('shortDescription', null, ['class' => 'form-control', 'rows' => '2']) !!}
                <small class="form-text text-muted">Short tagline shown under the page heading on this listing hero.</small>
            </div>
        </div>

        {{-- ═══ FEATURE RIBBON ═══ --}}
        <div class="col-md-12 alert alert-info mt-3">
            <h4>🎯 Feature Ribbon (icons strip)</h4>
            <small class="d-block">A horizontal strip of icon + label pairs that highlight what's included with every property in this category.</small>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('feature_ribbon_items', 'Ribbon Items') !!}
                @include('admin.cms.partials.json-editor', [
                    'jeName'   => 'feature_ribbon_items',
                    'jeValue'  => $data->feature_ribbon_items ?? null,
                    'jeTitle'  => 'Icon strip below the hero',
                    'jeHint'   => '4-6 items reads best. Supported icons: wind, wifi, car, coffee, bed, home, chef-hat, waves, trees.',
                    'jeAddLabel' => '+ Add ribbon item',
                    'jeSchema' => [
                        ['key'=>'icon',  'label'=>'Icon',  'type'=>'text', 'col'=>4, 'placeholder'=>'wind'],
                        ['key'=>'label', 'label'=>'Label', 'type'=>'text', 'col'=>8, 'placeholder'=>'A/C in every room'],
                    ],
                ])
            </div>
        </div>

        {{-- ═══ CHECK AVAILABILITY ═══ --}}
        <div class="col-md-12 alert alert-info mt-3">
            <h4>📅 Check Availability Box</h4>
            <small class="d-block">Date-picker widget that lets visitors enter dates and jump straight to filtered results.</small>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('check_avail_heading', 'Heading') !!}
                {!! Form::text('check_avail_heading', null, ['class' => 'form-control']) !!}
                <small class="form-text text-muted">Heading above the date-picker widget (e.g. <code>Check availability</code>).</small>
            </div>
        </div>

        {{-- ═══ FILTER BAR ═══ --}}
        <div class="col-md-12 alert alert-info mt-3">
            <h4>🔍 Filter Bar</h4>
            <small class="d-block">Category chips above the condo grid. Visitors click a chip to filter the listing by tag.</small>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('condos_count_text', 'Count Text (right side)') !!}
                {!! Form::text('condos_count_text', null, ['class' => 'form-control', 'placeholder' => '10 properties available']) !!}
                <small class="form-text text-muted">Text shown on the right of the filter bar (e.g. <code>10 properties available</code>). Manual count — update when you add/remove properties.</small>
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('filter_categories', 'Filter Categories') !!}
                @include('admin.cms.partials.json-editor', [
                    'jeName'   => 'filter_categories',
                    'jeValue'  => $data->filter_categories ?? null,
                    'jeTitle'  => 'Category chips on the listing filter bar',
                    'jeHint'   => 'Each chip filters the property grid. The "key" must match a value in a property\'s tags field for the filter to find matches.',
                    'jeAddLabel' => '+ Add filter chip',
                    'jeSchema' => [
                        ['key'=>'key',         'label'=>'Key (slug)',   'type'=>'text', 'col'=>3, 'placeholder'=>'beachfront'],
                        ['key'=>'label',       'label'=>'Chip label',   'type'=>'text', 'col'=>3, 'placeholder'=>'Beachfront'],
                        ['key'=>'info_strong', 'label'=>'Tooltip bold', 'type'=>'text', 'col'=>3, 'placeholder'=>'Steps from sand'],
                        ['key'=>'info_text',   'label'=>'Tooltip text', 'type'=>'text', 'col'=>3, 'placeholder'=>'Direct ocean access'],
                    ],
                ])
            </div>
        </div>

        <div class="col-md-12 alert alert-warning mt-3">
            <strong>Listing Cards:</strong> Manage individual properties in <a href="{{ url('admin/properties') }}">Properties admin</a>. Fill <code>banner_image</code> + <code>property_view</code> + <code>card_amenities</code> + <code>tags</code> for each one. Only published properties with <code>banner_image</code> set will appear on the listing page.
        </div>

        @endif
    @endisset
    @isset($data)
        @if ($data->seo_url == 'houses')

        <div class="col-md-12 alert alert-info">
            <h4>🏝️ Hero Section</h4>
            <small class="d-block">Top banner of this property listing page.</small>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('shortDescription', 'Hero Sub-text') !!}
                {!! Form::textarea('shortDescription', null, ['class' => 'form-control', 'rows' => '2']) !!}
                <small class="form-text text-muted">Short tagline shown under the page heading on this listing hero.</small>
            </div>
        </div>

        <div class="col-md-12 alert alert-info mt-3">
            <h4>🎯 Feature Ribbon</h4>
            <small class="d-block">Horizontal strip of icon + label pairs highlighting what every property comes with.</small>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('feature_ribbon_items', 'Ribbon Items') !!}
                @include('admin.cms.partials.json-editor', [
                    'jeName'   => 'feature_ribbon_items',
                    'jeValue'  => $data->feature_ribbon_items ?? null,
                    'jeTitle'  => 'Icon strip below the listing hero',
                    'jeHint'   => '4-6 items reads best. Supported icons: bed, home, chef-hat, waves, trees.',
                    'jeAddLabel' => '+ Add ribbon item',
                    'jeSchema' => [
                        ['key'=>'icon',  'label'=>'Icon',  'type'=>'text', 'col'=>4, 'placeholder'=>'home'],
                        ['key'=>'label', 'label'=>'Label', 'type'=>'text', 'col'=>8, 'placeholder'=>'4-bedroom stays'],
                    ],
                ])
            </div>
        </div>

        <div class="col-md-12 alert alert-info mt-3">
            <h4>📊 Compare Table</h4>
            <small class="d-block">Side-by-side feature comparison so visitors can see how properties differ at a glance.</small>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('compare_heading', 'Compare Heading') !!}
                {!! Form::text('compare_heading', null, ['class' => 'form-control', 'placeholder' => 'Compare Our Houses']) !!}
                <small class="form-text text-muted">Heading above the comparison table.</small>
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('compare_table_data', 'Compare Table Data (advanced — raw JSON)') !!}
                {!! Form::textarea('compare_table_data', null, ['class' => 'form-control', 'rows' => '10', 'style' => 'font-family:ui-monospace,Menlo,monospace;font-size:13px;']) !!}
                <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:10px 12px;margin-top:6px;color:#78350f;font-size:12.5px;line-height:1.5;">
                    <strong>⚠️ This one's still raw JSON.</strong> The compare table is a nested 2-level structure (headers + rows-with-values-per-column) — a structured editor for it is queued as a future phase. For now, edit the JSON directly using the shape:
                    <code style="display:block;margin-top:6px;background:#fff;padding:6px 8px;border-radius:4px;color:#0f172a;">{"headers":["House A","House B"],"rows":[{"feature":"Bedrooms","values":["3","4"]},{"feature":"Pool","values":["Yes","No"]}]}</code>
                </div>
            </div>
        </div>

        <div class="col-md-12 alert alert-info mt-3">
            <h4>📅 CTA Section</h4>
            <small class="d-block">Closing call-to-action at the bottom of the Houses page.</small>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('cta_section_heading', 'Heading') !!}
                {!! Form::text('cta_section_heading', null, ['class' => 'form-control']) !!}
                <small class="form-text text-muted">Closing-section heading.</small>
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('cta_section_body', 'Body') !!}
                {!! Form::textarea('cta_section_body', null, ['class' => 'form-control', 'rows' => '2']) !!}
                <small class="form-text text-muted">One- or two-sentence supporting copy under the heading.</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cta_section_cta1_label', 'Button 1 Label') !!}
                {!! Form::text('cta_section_cta1_label', null, ['class' => 'form-control']) !!}
                <small class="form-text text-muted">Primary button text.</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cta_section_cta1_url', 'Button 1 URL') !!}
                {!! Form::text('cta_section_cta1_url', null, ['class' => 'form-control']) !!}
                <small class="form-text text-muted">Where the primary button goes.</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cta_section_cta2_label', 'Button 2 Label') !!}
                {!! Form::text('cta_section_cta2_label', null, ['class' => 'form-control']) !!}
                <small class="form-text text-muted">Secondary button text.</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cta_section_cta2_url', 'Button 2 URL') !!}
                {!! Form::text('cta_section_cta2_url', null, ['class' => 'form-control']) !!}
                <small class="form-text text-muted">Where the secondary button goes.</small>
            </div>
        </div>

        <div class="col-md-12 alert alert-warning mt-3">
            <strong>Property Cards:</strong> Manage individual properties in <a href="{{ url('admin/properties') }}">Properties admin</a>. Set <code>banner_image</code>, <code>subtitle</code>, <code>long_description</code>, <code>price</code>, <code>area</code>, <code>rating</code>, and <code>card_amenities</code> (JSON).
        </div>

        @endif
    @endisset
    @isset($data)
        @if ($data->seo_url == 'experiences')

        <div class="col-md-12 alert alert-info">
            <h4>🌴 Experiences Page</h4>
            <small class="d-block">Long-form intro plus a grid of activity cards. Each card links out to a partner or info page.</small>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('experiences_intro', 'Intro Paragraph') !!}
                {!! Form::textarea('experiences_intro', null, ['class' => 'form-control', 'rows' => '4']) !!}
                <small class="text-muted">Long-form intro shown above the grid.</small>
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('experience_grid_items', 'Grid Items') !!}
                @include('admin.cms.partials.json-editor', [
                    'jeName'   => 'experience_grid_items',
                    'jeValue'  => $data->experience_grid_items ?? null,
                    'jeTitle'  => 'Cards on the Experiences page',
                    'jeHint'   => 'Image is a path relative to project root (e.g. front/images/property/experience.jpg). URL is the external partner link.',
                    'jeAddLabel' => '+ Add experience card',
                    'jeSchema' => [
                        ['key'=>'image', 'label'=>'Image path / URL', 'type'=>'text', 'col'=>6, 'placeholder'=>'front/images/property/experience.jpg'],
                        ['key'=>'alt',   'label'=>'Alt text',          'type'=>'text', 'col'=>6, 'placeholder'=>'Zipline through canopy'],
                        ['key'=>'title', 'label'=>'Title',             'type'=>'text', 'col'=>6, 'placeholder'=>'Zipline adventure'],
                        ['key'=>'url',   'label'=>'External URL',      'type'=>'text', 'col'=>6, 'placeholder'=>'https://partner-site.com/book'],
                    ],
                ])
            </div>
        </div>

        @endif
    @endisset
    @isset($data)
        @if ($data->seo_url == 'faqs')

        <div class="col-md-12 alert alert-info">
            <h4>❓ FAQ Page</h4>
            <small class="d-block">Help-center style page. Hero copy + a quick-answer card strip + the full FAQ list (managed in the FAQs admin section).</small>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('shortDescription', 'Hero Sub-text') !!}
                {!! Form::textarea('shortDescription', null, ['class' => 'form-control', 'rows' => '2']) !!}
                <small class="form-text text-muted">Short tagline shown under the page heading on the FAQ hero.</small>
            </div>
        </div>

        <div class="col-md-12 alert alert-info mt-3">
            <h4>📌 Quick Answers</h4>
            <small class="d-block">Compact card strip near the top with the most common one-line answers (price range, check-in time, pet policy, etc.).</small>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('quick_answers_heading', 'Section Heading') !!}
                {!! Form::text('quick_answers_heading', null, ['class' => 'form-control', 'placeholder' => 'Quick Answers']) !!}
                <small class="form-text text-muted">Heading above the quick-answer cards.</small>
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('quick_answers', 'Quick Answer Cards') !!}
                @include('admin.cms.partials.json-editor', [
                    'jeName'   => 'quick_answers',
                    'jeValue'  => $data->quick_answers ?? null,
                    'jeTitle'  => 'Cards near the top of the FAQ page',
                    'jeHint'   => 'Compact cards for the most common one-line answers (check-in time, pet policy, etc.). 4-8 cards reads cleanest.',
                    'jeAddLabel' => '+ Add quick answer',
                    'jeSchema' => [
                        ['key'=>'label', 'label'=>'Label', 'type'=>'text', 'col'=>5, 'placeholder'=>'Check-in'],
                        ['key'=>'value', 'label'=>'Value', 'type'=>'text', 'col'=>7, 'placeholder'=>'3:00 PM'],
                    ],
                ])
            </div>
        </div>

        <div class="col-md-12 alert alert-info mt-3">
            <h4>📅 CTA Section</h4>
            <small class="d-block">Closing call-to-action at the bottom of the FAQ page (e.g. "still have questions? contact us").</small>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('cta_section_heading', 'Heading') !!}
                {!! Form::text('cta_section_heading', null, ['class' => 'form-control']) !!}
                <small class="form-text text-muted">Closing-section heading.</small>
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {!! Form::label('cta_section_body', 'Body') !!}
                {!! Form::textarea('cta_section_body', null, ['class' => 'form-control', 'rows' => '2']) !!}
                <small class="form-text text-muted">One- or two-sentence supporting copy under the heading.</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cta_section_cta1_label', 'Button 1 Label') !!}
                {!! Form::text('cta_section_cta1_label', null, ['class' => 'form-control']) !!}
                <small class="form-text text-muted">Primary button text.</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cta_section_cta1_url', 'Button 1 URL') !!}
                {!! Form::text('cta_section_cta1_url', null, ['class' => 'form-control']) !!}
                <small class="form-text text-muted">Where the primary button goes.</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cta_section_cta2_label', 'Button 2 Label') !!}
                {!! Form::text('cta_section_cta2_label', null, ['class' => 'form-control']) !!}
                <small class="form-text text-muted">Secondary button text.</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cta_section_cta2_url', 'Button 2 URL') !!}
                {!! Form::text('cta_section_cta2_url', null, ['class' => 'form-control']) !!}
                <small class="form-text text-muted">Where the secondary button goes.</small>
            </div>
        </div>

        <div class="col-md-12 alert alert-warning mt-3">
            <strong>📝 FAQ Items:</strong> Manage individual question/answer pairs in <a href="{{ url('admin/faqs') }}">FAQs admin</a>. Use <code>type</code> field for category (booking / payment / location / amenities / activities). All published FAQs render in order on the listing.
        </div>

        @endif
    @endisset







    <div class="col-md-12 d-none">
        <div class="form-group">
            {!! Form::label('template') !!}
            @if($usesCustomCmsTemplate)
                <input type="hidden" name="templete" value="{{ $cmsTemplate }}">
            @else
                {!! Form::select('templete', $templateOptions, null, [
                'class' => 'form-control',
                'required' => 'required',
            ]) !!}
            @endif
            <span class="text-danger">{{ $errors->first('templete') }}</span>
        </div>
    </div>
    <div class="col-md-4 d-none">
        <div class="form-group">
            {!! Form::label('publish') !!}
            {!! Form::select('publish', ['published' => 'published', 'draft' => 'draft', 'pending' => 'pending'], null, [
                'class' => 'form-control',
                'required' => 'required',
            ]) !!}
            <span class="text-danger">{{ $errors->first('publish') }}</span>
        </div>
    </div>


</div>
<div class="row ">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label('shortDescription') !!}
            {!! Form::textarea('shortDescription', null, ['class' => 'form-control', 'rows' => '2']) !!}
            <small class="form-text text-muted">A short tagline shown under the hero image on supported templates (listing and FAQ pages). Keep it under 160 characters.</small>
            <span class="text-danger">{{ $errors->first('shortDescription') }}</span>
        </div>
    </div>

</div>
<div class="row ">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label('mediumDescription') !!}
            {!! Form::textarea('mediumDescription', null, ['class' => 'form-control', 'rows' => '2']) !!}
            <small class="form-text text-muted">Mid-length description (CKEditor-enabled). Used by some templates as a sub-section above the main body. Optional on most pages.</small>
            <span class="text-danger">{{ $errors->first('mediumDescription') }}</span>
        </div>
    </div>

</div>
<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label('longDescription') !!}
            {!! Form::textarea('longDescription', null, ['class' => 'form-control', 'rows' => '2']) !!}
            <small class="form-text text-muted">Full rich-text body (CKEditor-enabled). Rendered as the main page content for templates that don't use a structured branch (e.g. <code>common</code> template — Privacy Policy, Terms).</small>
            <span class="text-danger">{{ $errors->first('longDescription') }}</span>
        </div>
    </div>

</div>
<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label('seo_section') !!}
            {!! Form::textarea('seo_section', null, ['class' => 'form-control', 'rows' => '2']) !!}
            <small class="form-text text-muted">Optional rich-text block (CKEditor-enabled) rendered at the bottom of the page for SEO-only content. Not visible on most templates.</small>
            <span class="text-danger">{{ $errors->first('seo_section') }}</span>
        </div>
    </div>

</div>

<div class="row">
    <div class="col-md-12 alert alert-warning text-center">
        <h3>SEO &amp; Search Engine Settings</h3>
        <small class="d-block text-muted">These fields control how this page appears in Google, Bing, and social shares. Leave blank to inherit site defaults.</small>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('meta_title') !!}
            {!! Form::textarea('meta_title', null, ['class' => 'form-control', 'rows' => '2']) !!}
            <small class="form-text text-muted">Title shown in browser tabs and Google search results. Aim for 50–60 characters; longer titles get truncated.</small>
            <span class="text-danger">{{ $errors->first('meta_title') }}</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('meta_keywords') !!}
            {!! Form::textarea('meta_keywords', null, ['class' => 'form-control', 'rows' => '2']) !!}
            <small class="form-text text-muted">Comma-separated keywords (e.g. <code>vacation rental, costa rica, beach villa</code>). Modern search engines mostly ignore this — use it for internal taxonomy if you like.</small>
            <span class="text-danger">{{ $errors->first('meta_keywords') }}</span>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label('meta_description') !!}
            {!! Form::textarea('meta_description', null, ['class' => 'form-control', 'rows' => '2']) !!}
            <small class="form-text text-muted">The blurb shown under the page title in Google search results. Aim for 150–160 characters. Make it compelling — this is your "ad copy" for organic search.</small>
            <span class="text-danger">{{ $errors->first('meta_description') }}</span>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label('header_section') !!}
            {!! Form::textarea('header_section', null, ['class' => 'form-control', 'rows' => '2']) !!}
            <small class="form-text text-muted">Raw HTML / script tags injected into the page <code>&lt;head&gt;</code>. Use for analytics snippets, custom meta tags, or third-party widgets scoped to this page only. Empty is fine.</small>
            <span class="text-danger">{{ $errors->first('header_section') }}</span>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label('footer_section') !!}
            {!! Form::textarea('footer_section', null, ['class' => 'form-control', 'rows' => '2']) !!}
            <small class="form-text text-muted">Raw HTML / script tags injected just before the closing <code>&lt;/body&gt;</code>. Use for tracking pixels, chat widgets, or page-specific scripts that need to load late. Empty is fine.</small>
            <span class="text-danger">{{ $errors->first('footer_section') }}</span>
        </div>
    </div>
</div>
