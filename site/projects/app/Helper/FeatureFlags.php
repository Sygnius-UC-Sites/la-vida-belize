<?php

namespace App\Helper;

use Illuminate\Support\Facades\DB;

/**
 * Per-customer admin feature flags.
 *
 * A hidden, developer-only panel (/admin/dev/features) toggles which admin
 * features this customer's install exposes — so the menu and the routes match
 * exactly what was sold. Flags live in basic_settings.feature_flags (JSON
 * {key: bool}; missing key = enabled).
 *
 * Enforcement is two-layer:
 *   • Menu — the admin mega-menu skips items whose URL matches a disabled
 *     feature's prefixes (developers still see everything, marked "hidden").
 *   • Routes — the feature.gate middleware 403s direct URL access for
 *     non-developer users.
 */
class FeatureFlags
{
    /** Developer identities allowed into the hidden panel (and exempt from gating). */
    private const DEV_EMAILS  = ['steven@webdesignvr.com'];
    private const DEV_DOMAINS = [];

    /**
     * Registry of toggleable features. Core areas (properties, bookings,
     * general settings, users) are intentionally NOT listed — they can't be
     * switched off. 'prefixes' are admin URL path prefixes (no leading slash).
     */
    public const FEATURES = [
        'attractions'        => ['label' => 'Attractions',            'desc' => 'Local attractions content (admin/attractions + categories)', 'prefixes' => ['admin/attractions', 'admin/attraction-categories']],
        'coupons'            => ['label' => 'Coupons',                'desc' => 'Promotional discount codes',                                  'prefixes' => ['admin/coupons']],
        'airbnb_importer'    => ['label' => 'Airbnb listing importer','desc' => 'Scrape sources / review imports',                             'prefixes' => ['admin/scrape-sources']],
        'cms_pages'          => ['label' => 'CMS pages',              'desc' => 'Page content & SEO editor',                                   'prefixes' => ['admin/cms']],
        'faqs'               => ['label' => 'FAQs',                   'desc' => 'FAQ manager',                                                 'prefixes' => ['admin/faqs']],
        'testimonials'       => ['label' => 'Testimonials / Reviews', 'desc' => 'Guest review management',                                     'prefixes' => ['admin/testimonials']],
        'payments_admin'     => ['label' => 'Payments list',          'desc' => 'Charges, refunds, payouts screen',                            'prefixes' => ['admin/payments']],
        'scheduled_payments' => ['label' => 'Scheduled payments',     'desc' => 'Installment auto-charge dashboard',                           'prefixes' => ['admin/scheduled-payments']],
        'email_templates'    => ['label' => 'Email templates',        'desc' => 'Stay-cycle email content + previews',                         'prefixes' => ['admin/settings/stay-communications', 'admin/email-templates']],
        'rental_agreement'   => ['label' => 'Rental agreement',       'desc' => 'Signable terms settings',                                     'prefixes' => ['admin/settings/rental-agreement']],
        'pms'                => ['label' => 'PMS settings & sync',    'desc' => 'Lodgify/PMS integration screens',                             'prefixes' => ['admin/pms-settings', 'admin/pms_rooms', 'admin/pms-bookings']],
        'payment_settings'   => ['label' => 'Payment settings',      'desc' => 'Deposits, taxes, fees, gateways',                              'prefixes' => ['admin/settings/payment', 'admin/settings/payment-gateways']],
        'mail_settings'      => ['label' => 'Mail / SMTP',            'desc' => 'SMTP & deliverability settings',                              'prefixes' => ['admin/settings/mail']],
        'spam_protection'    => ['label' => 'Spam protection',        'desc' => 'Bot challenge and blocklists',                                'prefixes' => ['admin/settings/spam-protection']],
        'contact_enquiries'  => ['label' => 'Contact form enquiries', 'desc' => 'Guest contact-form inbox',                                    'prefixes' => ['admin/contact-us-enquiries']],
    ];

    /**
     * Sub-features — controls INSIDE pages (no routes of their own; enforced
     * by blade conditionals). Grouped for the dev panel. Note: 'coupons'
     * above also hides the guest-facing promo-code inputs on the booking
     * funnel, not just the admin page.
     */
    public const SUB_FEATURES = [
        'Property editor — sections' => [
            'prop_section_photos'   => ['label' => 'Photos',                     'desc' => 'Gallery manager tab'],
            'prop_section_amenities'=> ['label' => 'Amenities',                  'desc' => 'Amenity groups & highlights tab'],
            'prop_section_pricing'  => ['label' => 'Pricing & taxes',            'desc' => 'Rates, taxes, discounts tab'],
            'prop_section_calendar' => ['label' => 'Calendar & rates',           'desc' => 'Per-date rates / blocks tab'],
            'prop_section_fees'     => ['label' => 'Add-ons & extra fees',       'desc' => 'Additional fees tab'],
            'prop_section_booking'  => ['label' => 'Booking settings',           'desc' => 'Instant booking, min stay tab'],
            'prop_section_rules'    => ['label' => 'House rules & cancellation', 'desc' => 'Rules + cancellation policy tab'],
            'prop_section_rental'   => ['label' => 'Rental agreement',           'desc' => 'Per-property agreement tab'],
            'prop_section_stay'     => ['label' => 'Stay info & guest comms',    'desc' => 'Check-in info, stay emails tab'],
            'prop_section_detail'   => ['label' => 'Detail page (CMS)',          'desc' => 'Public detail-page content tab'],
            'prop_section_seo'      => ['label' => 'SEO',                        'desc' => 'Meta title/description tab'],
        ],
        'Public website — guest-facing pages' => [
            'front_attractions' => ['label' => 'Attractions page', 'desc' => 'Hides the public /attractions page + its nav/footer links'],
            'front_reviews'     => ['label' => 'Reviews page',     'desc' => 'Hides the public /reviews page + its nav/footer links'],
            'front_gallery'     => ['label' => 'Gallery page',     'desc' => 'Hides the public /gallery page + its nav links'],
            'front_faq'         => ['label' => 'FAQ page',         'desc' => 'Hides the public /faq page + its nav/footer links'],
            'front_map'         => ['label' => 'Property page map','desc' => 'Google Maps embed on property detail pages'],
        ],
        'Booking enquiries page' => [
            'enquiries_imported_tab' => ['label' => 'Imported reservations tab', 'desc' => 'The read-only PMS reservations tab inside Booking enquiries'],
        ],
        'PMS providers (PMS settings page)' => [
            'pms_provider_lodgify'   => ['label' => 'Lodgify',   'desc' => 'Lodgify credentials + sync section'],
            'pms_provider_hostfully' => ['label' => 'Hostfully', 'desc' => 'Hostfully credentials + sync section'],
            'pms_provider_guesty'    => ['label' => 'Guesty',    'desc' => 'Guesty credentials + sync section'],
            'pms_provider_hostaway'  => ['label' => 'Hostaway',  'desc' => 'Hostaway credentials + sync section'],
        ],
        'Payment gateways' => [
            'gateway_stripe'    => ['label' => 'Stripe',       'desc' => 'Admin option + checkout card form'],
            'gateway_authorize' => ['label' => 'AuthorizeNet', 'desc' => 'Admin option + checkout card form'],
            'gateway_square'    => ['label' => 'Square',       'desc' => 'Admin option + checkout card form'],
            'gateway_paypal'    => ['label' => 'PayPal',       'desc' => 'Admin option + checkout PayPal button'],
        ],
        'General settings — tabs' => [
            'settings_tab_social'     => ['label' => 'Social media tab', 'desc' => 'Social profile links editor'],
            'settings_tab_branding'   => ['label' => 'Branding tab',     'desc' => 'Logos, colors, brand assets'],
            'settings_tab_navigation' => ['label' => 'Navigation tab',   'desc' => 'Drag-and-drop nav menu builder'],
        ],
        'PMS settings page — extras' => [
            'pms_review_sync' => ['label' => 'Guest review sync',        'desc' => 'The "Sync Guest Reviews" card'],
            'pms_webhooks'    => ['label' => 'Webhook auto-registration','desc' => 'The webhook registration card (real-time PMS updates)'],
        ],
        'Bookings — guest communications' => [
            'magic_link_payments' => ['label' => 'Magic-link payments', 'desc' => 'Send guests an "awaiting payment" pay-by-link email'],
        ],
    ];

    /** Is a payment gateway available on this install? (gw: stripe|authorize|square|paypal) */
    public static function gatewayEnabled(string $gw): bool
    {
        return self::enabled('gateway_' . strtolower($gw));
    }

    /**
     * Map a public CMS page (template or slug) to its feature key, or null
     * if the page isn't gateable. Used by the front catch-all to 404 pages
     * for disabled features and by header/footer to hide their nav links.
     */
    public static function frontPageFeature(string $templateOrSlug): ?string
    {
        return match (strtolower($templateOrSlug)) {
            'attractions'        => 'front_attractions',
            'reviews'            => 'front_reviews',
            'gallery'            => 'front_gallery',
            'faq', 'faqs'        => 'front_faq',
            default              => null,
        };
    }

    /** Convenience: is the public page for this template/slug visible? */
    public static function frontPageEnabled(string $templateOrSlug): bool
    {
        $key = self::frontPageFeature($templateOrSlug);
        return $key === null || self::enabled($key);
    }

    /** Every toggleable key (page features + sub-features). */
    public static function allKeys(): array
    {
        $keys = array_keys(self::FEATURES);
        foreach (self::SUB_FEATURES as $group) {
            $keys = array_merge($keys, array_keys($group));
        }
        return $keys;
    }

    private static ?array $flags = null;

    /** Raw flags map {key: bool}. Missing key = enabled. */
    public static function flags(): array
    {
        if (self::$flags === null) {
            try {
                $raw = DB::table('basic_settings')->where('name', 'feature_flags')->value('value');
                self::$flags = json_decode((string) $raw, true) ?: [];
            } catch (\Throwable $e) {
                self::$flags = []; // settings table unavailable — everything on
            }
        }
        return self::$flags;
    }

    public static function enabled(string $key): bool
    {
        return (bool) (self::flags()[$key] ?? true);
    }

    /** Is the given (or current) user a developer? */
    public static function isDeveloper($user = null): bool
    {
        $user  = $user ?: auth()->user();
        if ((bool) ($user->is_developer ?? false)) return true;

        $email = strtolower((string) ($user->email ?? ''));
        if ($email === '') return false;
        if (in_array($email, self::developerEmails(), true)) return true;
        $domain = substr(strrchr($email, '@') ?: '', 1);
        return in_array($domain, self::DEV_DOMAINS, true);
    }

    public static function developerEmails(): array
    {
        return array_map('strtolower', self::DEV_EMAILS);
    }

    public static function emailWouldBeDeveloper(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') return false;
        if (in_array($email, self::developerEmails(), true)) return true;

        $domain = substr(strrchr($email, '@') ?: '', 1);
        return in_array($domain, self::DEV_DOMAINS, true);
    }

    /**
     * Feature key whose prefixes match this admin path, or null.
     * $path: request path without leading slash (e.g. "admin/coupons/3/edit").
     */
    public static function featureForPath(string $path): ?string
    {
        $path = ltrim($path, '/');
        foreach (self::FEATURES as $key => $def) {
            foreach ($def['prefixes'] as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                    return $key;
                }
            }
        }
        return null;
    }

    /** Should this menu URL be hidden for the CURRENT user? (devs see all) */
    public static function urlHidden(string $url): bool
    {
        if (self::isDeveloper()) return false;
        $key = self::featureForPath($url);
        return $key !== null && !self::enabled($key);
    }

    public static function save(array $flags): void
    {
        $clean = [];
        foreach (self::allKeys() as $key) {
            $clean[$key] = (bool) ($flags[$key] ?? true);
        }
        $row = DB::table('basic_settings')->where('name', 'feature_flags')->first();
        if ($row) {
            DB::table('basic_settings')->where('id', $row->id)->update(['value' => json_encode($clean), 'updated_at' => now()]);
        } else {
            DB::table('basic_settings')->insert(['name' => 'feature_flags', 'value' => json_encode($clean), 'created_at' => now(), 'updated_at' => now()]);
        }
        self::$flags = $clean;
    }
}
