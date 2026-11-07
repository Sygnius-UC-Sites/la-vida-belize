<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cms extends Model
{
	public $table = "cms";

	public $primaryKey = "id";

	public $timestamps = true;

	public $fillable = [
		'id',
		'name',
		'seo_url',
		// Field-wiring: reusable per-page heading/banner fields (each page's row holds its own).
		'fw_banner_title', 'fw_section_eyebrow', 'fw_section_title', 'fw_section_sub',
		// Microcopy: per-page editable UI strings (buttons, tab labels, section labels) as JSON.
		'ui_labels',
		'shortDescription',
		'mediumDescription',
		'longDescription',
		'description',
		'image',
		'meta_title',
		'meta_keywords',
		'meta_description',
		'templete',
		'bannerImage',
		'publish',
	

		'header_section',
		'footer_section',
		'seo_section',
		'image_2',
		'image_3',

		"faq_title",
		"faq_desction",
		"faq_image",
		"strip_title",
		"strip_desction",
		"strip_image",
		"about_image1",
		"about_image2",
		"owner_image",
		"about_background",
		"property_background",
		"gallery_background",
		"gallery_background",

		// Section headings
		"gallery_heading",
		"explore_attraction_heading",
		"visitors_heading",
		"faq_heading",
        "why_choose_us_heading",
        "why_choose_us_image",
        "why_choose_us_desc",
        "about_image4",
        "about_image5",
        "about_image6",

        // Design-specific home page fields used by richer public templates.
        'hero_eyebrow',
        'hero_heading',
        'hero_sub',
        'hero_cta',
        'video_url',
        'video_poster',
        'marquee_items',

        'villa_top_heading',
        'villa_heading',
        'villa_desc',
        'villa_image',
        'villa_cta',

        'living_heading',
        'living_desc',
        'living_image',
        'living_cta',
        'living_pills',

        'rooftop_heading',
        'rooftop_image',
        'rooftop_features',

        'beach_heading',
        'beach_desc',
        'beach_image',
        'beach_pills',

        'location_heading',
        'location_desc',
        'drive_time',
        'nearest_town',
        'beach_access',

        'gallery_sub',

        'experiences_heading',
        'experiences_desc',
        'experiences_image',
        'experiences_list',
        'experiences_cta',

        'faq_eyebrow',
        'faq_heading',
        'faq_sub',
        'whatsapp_url',

        // legacy fields kept for other pages
        'story_eyebrow',
        'story_title',
        'story_body_2',
        'retreats_eyebrow',
        'retreats_title',
        'better_title',
        'better_sub',
        'explore_eyebrow',
        'explore_title',
        'explore_sub',
        'explore_map_url',
        'faq_title',

        // Home page fields
        'hero_heading_gold',
        'hero_video_src',
        'hero_cta1_label', 'hero_cta1_url',
        'hero_cta2_label', 'hero_cta2_url',

        'condos_heading',
        'condos_cta_label', 'condos_cta_url',
        'condo_tabs',

        'houses_heading',
        'houses_card_eyebrow', 'houses_card_heading', 'houses_card_desc',
        'houses_card_tags',
        'houses_card_cta_label', 'houses_card_cta_url',

        'experiences_eyebrow', 'experiences_desc',
        'experiences_cta_label', 'experiences_cta_url',

        'story_image', 'story_body',
        'story_cta_label', 'story_cta_url',

        'amenities_heading', 'amenities_list',
        'amenities_cta1_label', 'amenities_cta1_url',
        'amenities_cta2_label', 'amenities_cta2_url',

        'location_body',
        'location_distances',
        'location_cta_label', 'location_cta_url',
        'location_image',
        'location_caption_eyebrow', 'location_caption_text',

        'testimonials_heading',
        'cta_section_heading', 'cta_section_body',
        'cta_section_cta1_label', 'cta_section_cta1_url',
        'cta_section_cta2_label', 'cta_section_cta2_url',
        'cta_section_response_note',

        // Our Story page fields
        'story_block_label',
        'story_block_heading',
        'story_block_body',
        'story_block_image',
        'story_block_author_name',
        'story_block_author_role',
        'quote_text',
        'milestones_label',
        'milestones_heading',
        'milestones_list',
        'stats_list',

        // Legacy condo/listing page fields
        'feature_ribbon_items',
        'filter_categories',
        'condos_count_text',
        'check_avail_heading',

        // Legacy condo/listing detail page fields
        'cd_category_heading',
        'cd_meta_rating',
        'cd_meta_location',
        'cd_gallery_images',
        'cd_host_heading',
        'cd_host_meta',
        'cd_highlights',
        'cd_about_heading',
        'cd_about_text',
        'cd_sleep_heading',
        'cd_sleep_cards',
        'cd_amenities_heading',
        'cd_amenities_list',
        'cd_booking_price',
        'cd_reviews_stats',
        'cd_reviews_list',
        'cd_reviews_show_all',
        'cd_offcanvas_amenities',
        'cd_offcanvas_reviews_heading',

        // Legacy house page fields
        'compare_heading',
        'compare_table_data',
        'houses_subtitle_heading',

        // Legacy house detail page fields (extends cd_* schema)
        'cd_booking_disclaimer',
        'cd_map_iframe_src',
        'cd_share_label',
        'cd_map_heading',

        // Legacy experiences page fields
        'experiences_intro',
        'experience_grid_items',

        // Legacy FAQ page fields
        'quick_answers',
        'quick_answers_heading',

        // 2026-06-11 mutation-test fix: these columns existed in the table but
        // were missing from $fillable, so admin edits to them were SILENTLY
        // DROPPED (taxonomy A1 — same class as the Lodgify-key bug).
        'booking_empty_message',
        'getting_here_steps',
        'getting_here_map_iframe_src',
        'getting_here_directions_url',
        'hero_carousel_images',
        'hero_badges',
        'gallery_cards',
        'better_items',
        'gallery_eyebrow',
        'gallery_title',
        'elfsight_widget_id',
        'story_image_accent',
        'home_gallery_images',
        'inner_banner_image',
	];

	public static $rules = [
		// create rules
	];

	public static function guestSeoUrls(): array
	{
		return [
			'home',
			'about-us',
			'about-owner',
			'properties',
			'faq',
			'attractions',
			'attractions-dining',
			'attractions-adventures',
			'attractions-taxi',
			'attractions-golf-cart',
			'attractions-car-rental',
			'contact',
			'experience',
			'groups',
			'reviews',
			'privacy',
			'terms',
		];
	}

	public function isGuestPage(): bool
	{
		return in_array((string) $this->seo_url, static::guestSeoUrls(), true);
	}
}
