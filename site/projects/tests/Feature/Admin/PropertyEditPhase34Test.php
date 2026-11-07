<?php

namespace Tests\Feature\Admin;

use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyEditPhase34Test extends TestCase
{
    private int $propertyId;
    private int $userId;
    private ?int $locationId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name'                   => '_test_phase34_property_',
            'seo_url'                => '_test-phase34-' . uniqid(),
            'instant_booking_button' => '',
            'status'                 => 'true',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $this->userId = DB::table('users')->insertGetId([
            'name'       => '_test_phase34_user_',
            'email'      => '_test_phase34_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The Listing-card-display section uses a real location dropdown — pick any existing
        // location so the round-trip POSTs a valid location_id.
        $this->locationId = DB::table('locations')->value('id');
    }

    protected function tearDown(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->delete();
        DB::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    public function test_main_tab_full_round_trip_persists_every_section_group(): void
    {
        $seoUrl = DB::table('properties')->where('id', $this->propertyId)->value('seo_url');

        $payload = [
            // § Identification
            'name'              => '_test_phase34_property_renamed_',
            'heading'           => 'Beachfront Test House',
            'seo_url'           => $seoUrl,
            'type'              => 'condo',
            'status'            => 'true',

            // § Listing card display
            'subtitle'          => 'A test subtitle',
            'rating'            => '4.7',
            'ordering'          => '5',
            'is_home'           => 'false',

            // § Property specs
            'bedroom'           => '2',
            'bathroom'          => '2',
            'beds'              => '3',
            'sleeps'            => '4',
            'area'              => '100 m²',
            'property_view'     => 'Garden view',

            // § Contact
            'mobile'            => '+506 1234 5678',
            'email'             => 'prop@example.com',
            'address'           => '123 Test Street',

            // § External listings
            'vrbo_link'         => 'https://vrbo.com/test',
            'airbnb_link'       => 'https://airbnb.com/test',

            // § Description & tags
            'long_description'  => '<p>Hello world</p>',
            'card_amenities'    => 'Wi-Fi,Pool',
            'tags'              => 'oceanfront,family',
        ];

        if ($this->locationId) {
            $payload['location_id'] = $this->locationId;
        }

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->put('/admin/properties/' . $this->propertyId, $payload);
        $response->assertRedirect();
        $response->assertSessionMissing('errors');

        $p = Property::findOrFail($this->propertyId);
        $this->assertSame('_test_phase34_property_renamed_', $p->name);
        $this->assertSame('Beachfront Test House', $p->heading);
        $this->assertSame('condo', $p->type);
        $this->assertSame('A test subtitle', $p->subtitle);
        $this->assertSame('4.7', (string)$p->rating);
        $this->assertEquals(5, (int)$p->ordering);
        $this->assertSame('2', (string)$p->bedroom);
        $this->assertSame('100 m²', $p->area);
        $this->assertSame('Garden view', $p->property_view);
        $this->assertSame('+506 1234 5678', $p->mobile);
        $this->assertSame('prop@example.com', $p->email);
        $this->assertSame('123 Test Street', $p->address);
        $this->assertSame('https://vrbo.com/test', $p->vrbo_link);
        $this->assertSame('https://airbnb.com/test', $p->airbnb_link);
        $this->assertSame('<p>Hello world</p>', $p->long_description);
        // card_amenities is normalized to JSON by the controller
        $this->assertStringContainsString('Wi-Fi', (string)$p->card_amenities);
        $this->assertSame('oceanfront,family', $p->tags);
    }

    public function test_section_card_headings_render_on_edit_page(): void
    {
        $response = $this->actingAs(User::findOrFail($this->userId))
            ->get('/admin/properties/' . $this->propertyId . '/edit');
        $response->assertOk();

        // Main tab section cards
        $response->assertSee('Identification');
        $response->assertSee('Listing card display');
        $response->assertSee('Property specs');
        $response->assertSee('Contact');
        $response->assertSee('External listings');
        $response->assertSee('Media');
        $response->assertSee('Description &amp; tags', false);

        // Detail Page tab section cards
        $response->assertSee('Hero (top of the detail page)');
        $response->assertSee('Body sections (mid-page content)');
        $response->assertSee('House-only fields');
        $response->assertSee('Get-quote modal description');

        // Stay Info tab merge-token reference
        $response->assertSee('Merge tokens you can use in welcome / check-in / check-out content');

        // Phase 3.5: is_home label rewritten to "Show on homepage?"; old "Is Home" auto-label gone
        $response->assertSee('Show on homepage?');
        $response->assertDontSee('<label for="is_home">Is Home</label>', false);

        // Phase 3.6: representative per-field helpers render
        $response->assertSee('Yes = guest pays at booking. No = booking goes through admin approval first.');
        $response->assertSee('Per-pet rate in dollars. Multiplied by pet count and (optionally) by nights.');
        $response->assertSee('What the guest sees on the booking widget checkbox');
    }

    public function test_merge_token_reference_card_lists_all_ten_tokens(): void
    {
        $response = $this->actingAs(User::findOrFail($this->userId))
            ->get('/admin/properties/' . $this->propertyId . '/edit');
        $response->assertOk();

        // Each token appears literally as {{token}} inside a <code> tag.
        // assertSee with escape=false because the rendered HTML contains < and > which
        // assertSee would otherwise HTML-escape from the search needle.
        foreach ([
            '{{guest_name}}',
            '{{guest_email}}',
            '{{property_name}}',
            '{{property_address}}',
            '{{check_in}}',
            '{{check_out}}',
            '{{nights}}',
            '{{host_name}}',
            '{{host_phone}}',
            '{{emergency_phone}}',
        ] as $token) {
            $response->assertSee('<code>' . $token . '</code>', false);
        }
    }
}
