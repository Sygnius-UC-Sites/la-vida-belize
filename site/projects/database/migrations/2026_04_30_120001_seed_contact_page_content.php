<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Cms;

return new class extends Migration {
    public function up(): void {
        Cms::where('seo_url','contact-us')->update([
            'getting_here_steps' => json_encode([
                ['title' => 'From San José Airport (SJO)', 'text' => '45-minute drive toward Valle Verde'],
                ['title' => 'Follow Highway 27 South',     'text' => 'Take the exit toward Dominical/V.Vi'],
                ['title' => 'Private Road',                'text' => "Take the private road (we'll send directions)"],
            ]),
            'getting_here_map_iframe_src' => 'https://www.google.com/maps/embed?pb=!1m16!1m12!1m3!1d1005980.9027766865!2d-85.09636517545431!3d9.966128791097296!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!2m1!1sValle%20Verde%2C%20Costa%20Rica!5e0!3m2!1sen!2sin!4v1774610273973!5m2!1sen!2sin',
            'getting_here_directions_url' => 'https://maps.google.com/?q=Valle+Verde+Costa+Rica',
        ]);
    }

    public function down(): void {
        // Forward-only.
    }
};
