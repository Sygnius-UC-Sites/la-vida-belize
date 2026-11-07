<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    public $fillable=[
        "name",
            "firebase_base_price",
            "seo_url",
            "heading",
            "address",
            'property_status',
            'location_id','review_text',
            "mobile",
            "email",
            "website",
            "short_description",
            "long_description",
            "description",
			"is_parent",
            "cancellation_policy",
            "booking_policy",
            "notes",

            "bedroom",
            "bathroom",
            "beds",
            "sleeps",
            "area",
            "full_bath",
            "half_bath",
            "spaces",
            "feature_image",
            "banner_image",
            "cleaning_fee",
            "heating_swimming_pool_fee",
            "pet_fee",
            "tax",
            "propane_gas",

            'checkin',
            'checkout',
            'category',
            'bed_type',
            'property_view',


            "status",
            "meta_title",
            "meta_keywords",
            "meta_description",
            "header_section",
            "footer_section",


            'tags',
            'is_home',
            'is_trending',
            'is_top',
            'is_feature',
            'is_bestseller',
            'is_sale',
            'is_hot',
            'min_stay',
            'standard_rate',
            'weekend_rate',
            'weekend_days',
                'checkin_day',
            'checkout_day',
            'map',


               'max_pet',
            'pet_fee',
            'pet_fee_interval',
            'guest_fee',
            'no_of_guest',

            'welcome_package_description',
            'welcome_package_attachment',
            'welcome_email_send_day',
            'checkin_email_send_day',
            'checkout_email_send_day',
            'checkin_arrival_window',
            'checkin_door_code',
            'checkin_wifi_name',
            'checkin_wifi_password',
            'checkin_parking_instructions',
            'checkin_host_name',
            'checkin_host_phone',
            'checkin_emergency_phone',
            'detail_check_in_instructions',
            'checkout_departure_time',
            'checkout_key_return',
            'checkout_cleaning_checklist',
            'checkout_notes',
            'rental_aggrement_attachment',
            // Phase 1 — rental-agreement rebuild: source .docx + signing-mode override.
            'rental_agreement_source_doc_path',
            'rental_agreement_signing_mode',
            'instant_booking_button',
            'api_id',
            'api_pms',
            'extra_bed',
            'queen_beds',
            'king_beds',
            
            
            'ordering',
            'vrbo_link',
            'airbnb_link',
            
            'heating_pool_fee',
            'heating_pool_fee_type',
            'pet_fee_type',
            // Phase 3: one_pet_fee through five_pet_fee removed; pet fee is now a single per-pet rate × pet_fee_interval

        "bedroom_text",
        "bathroom_text",
        'beds_text',
        'sleeps_text',
        'visitor_rate_info',
      
      'get_quote_description',
      'rates','max_rv_hook_up',
      'box_heading','box_description',
      'security_deposit_amount',
      'security_deposit_text',
      'security_deposit_charge_at_booking',
      'total_amount_include_text',

      // Legacy listing type: distinguish condo vs house listings
      'type',
      'card_amenities',
      'rating',
      'subtitle',

      // Task 2: property-detail-restructure — 19 new detail_* fields
      'detail_category_heading',
      'detail_meta_rating',
      'detail_meta_location',
      'detail_gallery_images',
      'detail_host_heading',
      'detail_host_meta',
      'detail_highlights',
      'detail_about_heading',
      'detail_about_text',
      'detail_sleep_heading',
      'detail_sleep_cards',
      'detail_amenities_heading',
      'detail_amenities_list',
      'detail_offcanvas_amenities',
      'detail_offcanvas_reviews_heading',
      'detail_share_label',
      'detail_booking_disclaimer',
      'detail_map_heading',
      'detail_map_iframe_src',

      // Plan A: Booking rules tab
      'instant_booking_enabled',
      'rental_agreement_required',
      'cancellation_policy_type',
      'max_stay',
      'accepts_pets',
      'smoking_allowed',
      'parties_allowed',
      'wheelchair_accessible',

      // Plan A: Per-property installment override (nullable = use global)
      'installment_plan',
      'installment_2_days',
      'installment_2_percent',
      'installment_2_min_lead_days',
      'installment_3_days',
      'installment_3_percent',
      'installment_3_min_lead_days',

      // Plan A: Per-property tax base override (nullable boolean)
      'tax_applies_to_nights',
      'tax_applies_to_cleaning_fee',
      'tax_applies_to_pet_fee',
      'tax_applies_to_extra_guest_fee',
      'tax_applies_to_hottub_addon',
      'tax_applies_to_firewood_addon',
      'tax_applies_to_rv_hookup_addon',
      'tax_applies_to_extra_visitor_addon',

      // Plan A: Per-property discount base override (nullable boolean)
      'discount_applies_to_nights',
      'discount_applies_to_cleaning_fee',
      'discount_applies_to_pet_fee',
      'discount_applies_to_extra_guest_fee',
      'discount_applies_to_hottub_addon',
      'discount_applies_to_firewood_addon',
      'discount_applies_to_rv_hookup_addon',
      'discount_applies_to_extra_visitor_addon',

      // MultiPms module — links this property to its PMS source
      'pms_source',
      'pms_room_id',
    ];

    public function galleryImages()
    {
        return $this->hasMany(\App\Models\PropertyGallery::class, 'property_id');
    }

    public function location()
    {
        return $this->belongsTo(\App\Models\Location::class, 'location_id');
    }

    public function categories()
    {
        return $this->belongsToMany(
            PropertyCategory::class,
            'property_category_property'
        )->withTimestamps();
    }

    public function pmsRoom()
    {
        return $this->belongsTo(\App\Models\Pms\PmsRoom::class, 'pms_room_id');
    }

    public function galleryPhotos()
    {
        if ($this->pmsRoom && $this->pmsRoom->pms_room_id) {
            $pms = \DB::table('pms_images')->where('pms_room_id', $this->pmsRoom->pms_room_id)->orderBy('id')->get();
            if ($pms->isNotEmpty()) {
                return $pms->map(function ($im) {
                    $url = (string) ($im->url ?? '');
                    if (\Illuminate\Support\Str::startsWith($url, '//')) { $url = 'https:' . $url; }
                    $o = new \stdClass(); $o->image = $url; $o->caption = $im->text ?? ''; $o->category = null; $o->is_remote = true; return $o;
                });
            }
        }
        return \App\Models\PropertyGallery::where('property_id', $this->id)->whereNotIn('status', ['false', '0', 'inactive'])->orderBy('sorting')->orderByDesc('id')->get()->map(function ($g) { $g->is_remote = false; return $g; });
    }

    /**
     * Phase 4 — single source of truth for the rental-agreement signing flow.
     *
     * Returns 'pre_payment' or 'post_confirmation'. The 'inherit' value on
     * the property column means "use the global setting"; this helper does
     * that resolution so call sites don't have to remember.
     *
     * 'pre_payment'      — block checkout until the agreement is signed.
     * 'post_confirmation' — fire a magic-link "please sign" email after the
     *                       initial payment lands; check-in cron emails are
     *                       held until signing completes.
     */
    public function effectiveSigningMode(): string
    {
        $perProperty = $this->rental_agreement_signing_mode ?: 'inherit';
        if ($perProperty === 'pre_payment' || $perProperty === 'post_confirmation') {
            return $perProperty;
        }
        $global = (string) (\ModelHelper::getDataFromSetting('rental_agreement_signing_mode_global') ?: 'pre_payment');
        return $global === 'post_confirmation' ? 'post_confirmation' : 'pre_payment';
    }

    /**
     * Convenience boolean — true when the agreement is required AND the
     * signing-mode is pre-payment, i.e. checkout must be gated. Phase 4.
     */
    public function requiresPrePaymentSigning(): bool
    {
        return ((int) ($this->rental_agreement_required ?? 0)) === 1
            && $this->effectiveSigningMode() === 'pre_payment';
    }

    /**
     * True when the agreement is required AND the signing-mode is
     * post-confirmation. Used by the post-payment email pipeline + the
     * check-in cron's "skip if unsigned" guard.
     */
    public function requiresPostConfirmationSigning(): bool
    {
        return ((int) ($this->rental_agreement_required ?? 0)) === 1
            && $this->effectiveSigningMode() === 'post_confirmation';
    }
}
