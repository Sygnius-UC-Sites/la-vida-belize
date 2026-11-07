<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingRequest extends Model
{
    use HasFactory;

    public $fillable=[
        // [PMS-MODULE] fillable additions (duplicates are harmless)
        'infants', 'first_name', 'last_name', 'pet', 'currency', 'deposit_due_amount', 'optional_services', 'requested_services', 'pms_booking_id', 'pms_room_id', 'pms_source', 'quote_data_all', 'pms_booking_response_all', 'status', 'stripe_deposit_charge_id', 'security_deposit', 'security_deposit_amount', 'security_deposit_currency', 'security_deposit_status', 'pms_recovery_attempts', 'pms_recovery_last_at', 'payment_token', 'stripe_payment_intent_id', 'payment_intent_id', 'deposit_payment_intent_id', 'balance_payment_intent_id', 'deposit_paid_at', 'balance_paid_at', 'stripe_refund_id', 'quote_expires_at', 'quoted_fees_snapshot', 'price_changed_to', 'price_changed_at', 'quote_refresh_failed', 'pms_payment_id', 'pms_order_uid', 'pms_sync_status', 'pms_sync_error',

            "property_id",
            "checkin",
            "checkout",
            "total_guests",
            "adults",
            "child",
            "gross_amount",
            "total_night",
            "sub_amount",
            "total_amount",
            "after_total_fees",
            "before_total_fees",
            "request_id",
            "booking_status",
            "email_status",
            "payment_status",
            "welcome_email",
            "welcome_email_sent_at",
            "welcome_email_attempted_at",
            "welcome_email_error",
            "review_email",
            "review_email_sent_at",
            "review_email_attempted_at",
            "review_email_error",
            "reminder_email",
            "third_reminder_email",
            "checkin_email",
            "checkin_email_sent_at",
            "checkin_email_attempted_at",
            "checkin_email_error",
            "checkout_email",
            "checkout_email_sent_at",
            "checkout_email_attempted_at",
            "checkout_email_error",
            "name",
            "first_name",
            "last_name",
            "source",
            "email",
            "mobile",
            "message",
            "ip_address",
            "cancel_reason",
            "note",
            'rental_aggrement_status',
            'rental_aggrement_signature',
            'rental_aggrement_images',
            // Phase 1 — frozen HTML the guest actually signed (audit trail).
            'rental_agreement_html_snapshot',
            'total_payment',
            'amount_data',
            'rental_agreement_link',
            'how_many_payment_done',
            
            'extra_amount',
            'total_pets',
            'pet_fee',
            'guest_fee',
            'rest_guests',
            'single_guest_fee',
            'discount',
            'discount_coupon',
            'after_discount_total',
            'extra_discount',
            'heating_pool_fee',
            'tax',
            'define_tax',
            'pet_fee_type',
            'heating_pool_fee_type',
            'booking_type_admin',
            'payment_method',

            'quote_date',
            'quote_ip',
            'rentar_agreement_date',
            'rentar_agreement_ip',
            'custom_before_total_fees',
            'heating_pool_fee_data_guarav',
            'operation',
            // Phase 3: rv_hookup_json / visitor_hookup_json / hottub_json / firewood_json columns dropped — addons live in before_total_fees JSON
            'customer_profile_id',
            'customer_profile_payment_id',
            'customer_json_data',
            'security_deposit_data',
      		'card_need_hide',
            'cancel_at',

    
        // mutation-test fix (2026-06-11): columns missing from $fillable
        'rental_agreement_pdf_path',
        'rental_agreement_signed_at',
        'rental_agreement_signed_ip',
        'rental_agreement_signed_user_agent',
        'rental_agreement_consent_text',
        'color',
    ];
}
