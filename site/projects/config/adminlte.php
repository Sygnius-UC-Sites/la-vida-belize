<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Title
    |--------------------------------------------------------------------------
    */

    'title' => 'Administrator',
    'title_prefix' => '',
    'title_postfix' => '',

    /*
    |--------------------------------------------------------------------------
    | Favicon
    |--------------------------------------------------------------------------
    */

    'use_ico_only' => false,
    'use_full_favicon' => false,

    /*
    |--------------------------------------------------------------------------
    | Logo
    |--------------------------------------------------------------------------
    */

    'logo' => '<span class="ca-brand-chip" aria-hidden="true"></span><span class="ca-brand-text">Administrator</span>',
    'logo_img' => 'front/themes/base/img/logo.svg',
    'logo_img_class' => 'brand-image img-circle elevation-3',
    'logo_img_xl' => null,
    'logo_img_xl_class' => 'brand-image-xs',
    'logo_img_alt' => 'Administrator',

    /*
    |--------------------------------------------------------------------------
    | User Menu
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | User Menu
    |--------------------------------------------------------------------------
    */

    'usermenu_enabled' => true,
    'usermenu_header' => false,
    'usermenu_header_class' => 'bg-primary',
    'usermenu_image' => false,
    'usermenu_desc' => false,
    'usermenu_profile_url' => false,

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    */

    'layout_topnav' => null,
    'layout_boxed' => null,
    'layout_fixed_sidebar' => null,
    'layout_fixed_navbar' => null,
    'layout_fixed_footer' => null,
    'layout_dark_mode' => null,

    /*
    |--------------------------------------------------------------------------
    | Authentication Views Classes
    |--------------------------------------------------------------------------
    */

    'classes_auth_card' => '',
    'classes_auth_header' => 'bg-gradient-info',
    'classes_auth_body' => '',
    'classes_auth_footer' => 'text-center',
    'classes_auth_icon' => 'fa-lg text-info',
    'classes_auth_btn' => 'btn-flat btn-primary',

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Classes
    |--------------------------------------------------------------------------
    */

    'classes_body' => '',
    'classes_brand' => '',
    'classes_brand_text' => '',
    'classes_content_wrapper' => '',
    'classes_content_header' => '',
    'classes_content' => '',
    'classes_sidebar' => 'sidebar-light-primary',
    'classes_sidebar_nav' => '',
    'classes_topnav' => 'navbar-white navbar-light',
    'classes_topnav_nav' => 'navbar-expand',
    'classes_topnav_container' => 'container',

    /*
    |--------------------------------------------------------------------------
    | Sidebar
    |--------------------------------------------------------------------------
    */

    'sidebar_mini' => 'lg',
    'sidebar_collapse' => false,
    'sidebar_collapse_auto_size' => false,
    'sidebar_collapse_remember' => false,
    'sidebar_collapse_remember_no_transition' => true,
    'sidebar_scrollbar_theme' => 'os-theme-light',
    'sidebar_scrollbar_auto_hide' => 'l',
    'sidebar_nav_accordion' => true,
    'sidebar_nav_animation_speed' => 300,

    /*
    |--------------------------------------------------------------------------
    | Control Sidebar (Right Sidebar)
    |--------------------------------------------------------------------------
    */

    'right_sidebar' => false,
    'right_sidebar_icon' => 'fas fa-cogs',
    'right_sidebar_theme' => 'dark',
    'right_sidebar_slide' => true,
    'right_sidebar_push' => true,
    'right_sidebar_scrollbar_theme' => 'os-theme-light',
    'right_sidebar_scrollbar_auto_hide' => 'l',

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    */

    'use_route_url' => false,
    'dashboard_url' => 'admin',
    'logout_url' => 'logout',
    'login_url' => 'login',
    'register_url' => false,
    'password_reset_url' => 'password/reset',
    'password_email_url' => 'password/email',
    'profile_url' => false,


    /*
    |--------------------------------------------------------------------------
    | Laravel Mix
    |--------------------------------------------------------------------------
    */

    'enabled_laravel_mix' => false,
    'laravel_mix_css_path' => 'css/app.css',
    'laravel_mix_js_path' => 'js/app.js',

    /*
    |--------------------------------------------------------------------------
    | Menu Items
    |--------------------------------------------------------------------------
    */

    'menu' => [

        /* Phase 4 of admin polish — Airbnb-style 5-group hierarchy. The old
         * 9-flat-settings list was visually noisy. Now everything lives
         * inside one of five top-level groups so admins scan five chunks,
         * not fifteen items.
         *
         * Mental model:
         *   Reservations — daily work: who booked, who paid, who emailed
         *   Listings     — what we sell: properties, attractions, coupons
         *   Content      — what guests read: CMS pages, FAQs
         *   Communications — what we send: email templates, rental agreement, spam
         *   System       — global config the host rarely touches
         */

        // ─── Reservations ─── (daily work)
        [
            'text'    => 'Reservations',
            'icon'    => 'fas fa-fw fa-calendar-check',
            'submenu' => [
                ['text' => 'Booking enquiries',  'url' => 'admin/booking-enquiries',     'icon' => 'fas fa-fw fa-bookmark',  'permission' => 'admin'],
                ['text' => 'Contact form',       'url' => 'admin/contact-us-enquiries',  'icon' => 'fas fa-fw fa-phone-alt', 'permission' => 'admin'],
                ['text' => 'Payments',           'url' => 'admin/payments',              'icon' => 'fas fa-fw fa-dollar-sign','permission' => 'admin'],
                ['text' => 'Scheduled payments', 'url' => 'admin/scheduled-payments',    'icon' => 'fas fa-fw fa-calendar-check','permission' => 'admin'],
            ],
        ],

        // ─── Listings ─── (the inventory)
        [
            'text'    => 'Listings',
            'icon'    => 'fas fa-fw fa-hotel',
            'submenu' => [
                ['text' => 'Properties',         'url' => 'admin/properties',            'icon' => 'fas fa-fw fa-building',  'permission' => 'admin'],
                ['text' => 'Property categories','url' => 'admin/property-categories',   'icon' => 'fas fa-fw fa-layer-group','permission' => 'admin'],
                ['text' => 'Attractions',        'url' => 'admin/attractions',           'icon' => 'fas fa-fw fa-magnet',    'permission' => 'admin'],
                ['text' => 'Coupons',            'url' => 'admin/coupons',               'icon' => 'fas fa-fw fa-tag',       'permission' => 'admin'],
                ['text' => 'Airbnb listing importer','url' => 'admin/scrape-sources',    'icon' => 'fas fa-fw fa-cloud-download-alt','permission' => 'admin'],
            ],
        ],

        // ─── Website content ─── (what guests see on the public site)
        [
            'text'    => 'Website content',
            'icon'    => 'fas fa-fw fa-scroll',
            'submenu' => [
                ['text' => 'CMS pages',          'url' => 'admin/cms',                   'icon' => 'fas fa-fw fa-file-alt',  'permission' => 'admin'],
                ['text' => 'FAQs',               'url' => 'admin/faqs',                  'icon' => 'fas fa-fw fa-question',  'permission' => 'admin'],
            ],
        ],

        // ─── Communications ─── (content of what the system sends —
        //     the copy / templates / agreement, NOT the plumbing).
        [
            'text'    => 'Communications',
            'icon'    => 'fas fa-fw fa-paper-plane',
            'submenu' => [
                ['text' => 'Email templates',    'url' => 'admin/settings/stay-communications', 'icon' => 'fas fa-fw fa-envelope-open-text', 'permission' => 'admin'],
                ['text' => 'Email previews',     'url' => 'admin/email-templates',              'icon' => 'fas fa-fw fa-eye',                'permission' => 'admin'],
                ['text' => 'Rental agreement',   'url' => 'admin/settings/rental-agreement',    'icon' => 'fas fa-fw fa-file-signature',     'permission' => 'admin'],
            ],
        ],

        // ─── Settings ─── (infrastructure / plumbing config — payment
        //     gateways, SMTP, spam filters, currency. The host rarely
        //     touches these once the install is done).
        [
            'text'    => 'Settings',
            'icon'    => 'fas fa-fw fa-cog',
            'submenu' => [
                ['text' => 'General settings',   'url' => 'admin/setting',                      'icon' => 'fas fa-fw fa-sliders-h',          'permission' => 'admin'],
                ['text' => 'Users',              'url' => 'admin/users',                        'icon' => 'fas fa-fw fa-users-cog',          'permission' => 'admin'],
                ['text' => 'PMS settings & sync','url' => 'admin/pms-settings',                 'icon' => 'fas fa-fw fa-plug',               'permission' => 'admin'],
                ['text' => 'Go live status',     'url' => 'admin/operations',                   'icon' => 'fas fa-fw fa-clipboard-check',    'permission' => 'admin'],
                ['text' => 'Payment settings',   'url' => 'admin/settings/payment',             'icon' => 'fas fa-fw fa-money-check-alt',    'permission' => 'admin'],
                ['text' => 'Payment gateways',   'url' => 'admin/settings/payment-gateways',    'icon' => 'fas fa-fw fa-credit-card',        'permission' => 'admin'],
                ['text' => 'Mail / SMTP',        'url' => 'admin/settings/mail',                'icon' => 'fas fa-fw fa-server',             'permission' => 'admin'],
                ['text' => 'Spam protection',    'url' => 'admin/settings/spam-protection',     'icon' => 'fas fa-fw fa-shield-alt',         'permission' => 'admin'],
            ],
        ],

        // Help — opens the per-install support URL when configured.
        ['text' => 'Help & support', 'url' => 'admin/help', 'icon' => 'fas fa-fw fa-life-ring', 'target' => '_blank'],
        ['text' => 'Log out',        'url' => 'logout',     'icon' => 'fas fa-fw fa-sign-out-alt'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu Filters
    |--------------------------------------------------------------------------
    */

    'filters' => [
        JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\HrefFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\SearchFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ActiveFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ClassesFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\LangFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\DataFilter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins Initialization
    |--------------------------------------------------------------------------
    */

    'plugins' => [
        'Datatables' => [
            'active' => true,
            'files' => [
                ['type' => 'js', 'asset' => true, 'location' => '//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js',],
                ['type' => 'js', 'asset' => true, 'location' => '//cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js',],
                ['type' => 'css', 'asset' => true, 'location' => '//cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css',],
            ],
        ],
        'Select2' => [
            'active' => true,
            'files' => [
                ['type' => 'js', 'asset' => true, 'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js',],
                ['type' => 'css', 'asset' => true, 'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.css',],
            ],
        ],
        'Chartjs' => [
            'active' => true,
            'files' => [
                ['type' => 'js', 'asset' => true, 'location' => '//cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.0/Chart.bundle.min.js',],
            ],
        ],
        'Sweetalert2' => [
            'active' => true,
            'files' => [
                ['type' => 'js', 'asset' => true, 'location' => '//cdn.jsdelivr.net/npm/sweetalert2@8',],
            ],
        ],
        'Pace' => [
            'active' => false,
            'files' => [
                ['type' => 'css', 'asset' => false, 'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/themes/blue/pace-theme-center-radar.min.css',],
                ['type' => 'js', 'asset' => false, 'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/pace.min.js',],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IFrame
    |--------------------------------------------------------------------------
    */

    'iframe' => [
        'default_tab' => ['url' => null, 'title' => null,],
        'buttons' => ['close' => true, 'close_all' => true, 'close_all_other' => true, 'scroll_left' => true, 'scroll_right' => true, 'fullscreen' => true,],
        'options' => ['loading_screen' => 1000, 'auto_show_new_tab' => true, 'use_navbar_items' => true,],
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire
    |--------------------------------------------------------------------------
    */

    'livewire' => false,
];
