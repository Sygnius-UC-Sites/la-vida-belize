<?php

namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PmsRoom extends Model
{
    use HasFactory;
    protected $table = 'pms_rooms';

    public $fillable=[
        "pms_property_id",
        "pms_room_id",
        "description",
        "breakfast_included",
        "has_parking",
        "adults_only",
        "pets_allowed",
        "show_additional_key_facts",
        "name",
        "image_url",
        "max_people",
        "units",
        "has_wifi",
        "has_meal_plan",
        "bedrooms",
        "bathrooms",
        "area_unit",
        "area",
        "min_price",
        "original_min_price",
        "max_price",
        "original_max_price",
        "price_unit_in_days",
        "currency",
        "images_json",
        "amenities_json",
        "seo_url",
        "meta_title",
        "meta_keywords",
        "meta_description",
        'is_home',
        'status',
        'banner_image',
        'location_id',
        'review_widget',
        'map','ordering','custom_min_stay','deposit_percent','balance_due_days',
        'api_id',
        'api_pms','virtual_tour',
        'house_rules',
        'cancellation_policy',
        'booking_policy',
        'key_facts_json',
        'optional_fees_json',
        'max_pets',
        'pricing_json',
        'pms_source',
        'address',
        'city',
        'state',
        'country',
        'zip',
        'lat',
        'lng',
        'property_type',
        'bed_config_json',
        'last_synced_at',
        'rental_agreement_required',
        'rental_agreement_signing_mode',
        'rental_agreement_template_html',
        'rental_agreement_house_rules_html',
    
        // mutation-test fix (2026-06-11): columns missing from $fillable
        'accommodates',
        'min_stay',
        'active',
    ];

    public function effectiveSigningMode(): string
    {
        $perRoom = $this->rental_agreement_signing_mode ?: 'inherit';
        if ($perRoom === 'pre_payment' || $perRoom === 'post_confirmation') {
            return $perRoom;
        }

        $global = (string) (\ModelHelper::getDataFromSetting('rental_agreement_signing_mode_global') ?: 'pre_payment');
        return $global === 'post_confirmation' ? 'post_confirmation' : 'pre_payment';
    }

    public function requiresPrePaymentSigning(): bool
    {
        return ((int) ($this->rental_agreement_required ?? 0)) === 1
            && $this->effectiveSigningMode() === 'pre_payment';
    }

    public function requiresPostConfirmationSigning(): bool
    {
        return ((int) ($this->rental_agreement_required ?? 0)) === 1
            && $this->effectiveSigningMode() === 'post_confirmation';
    }
}
