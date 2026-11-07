<?php

namespace Tests\Feature;

use App\Models\Pms\PmsRoom;
use App\Modules\VacationRental\Pms\PmsAdapter;
use App\Modules\VacationRental\Pms\PmsManager;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsUnifiedCheckoutTest extends TestCase
{
    private function bindFakePmsManager(): void
    {
        $adapter = new class implements PmsAdapter {
            public function getName(): string { return 'hostfully'; }
            public function parseWebhook(array $payload): array { return []; }
            public function healthCheck(): array { return ['ok' => true]; }
            public function listProperties(array $options = []): array { return []; }
            public function getProperty(string $propertyId): array { return []; }
            public function listUnits(?string $propertyId = null): array { return []; }
            public function getAvailability(array $input): array { return []; }
            public function getQuote(array $input): array { return $this->getRawQuote($input); }
            public function getRawQuote(array $input): array
            {
                return [
                    'status' => 200,
                    'data' => [
                        'quote' => [
                            'currency' => 'USD',
                            'totalAmount' => 500,
                            'rent' => [
                                'rentNetPrice' => 450,
                                'taxAmount' => 50,
                            ],
                        ],
                    ],
                ];
            }
            public function createReservation(array $input): array { return []; }
            public function updateReservation(array $input): array { return []; }
            public function cancelReservation(array $input): array { return []; }
            public function listReservations(array $input = []): array { return []; }
            public function getPaymentLink(string $bookingId): array { return []; }
            public function recordPayment(string $bookingId, float $amount, string $currency, string $stripeIntentId = '', bool $isRetry = false): array { return []; }
            public function registerWebhook(string $url, array $events): array { return []; }
            public function getPropertyAddons(string $propertyId, array $query = []): array { return []; }
            public function getReviews(string $propertyId): array { return []; }
            public function fetchAndSaveRawData(string $propertyId): void {}
            public function getFrontendData(string $propertyId): array { return []; }
        };

        $this->app->instance(PmsManager::class, new class(app(), $adapter) extends PmsManager {
            public function __construct($app, private PmsAdapter $adapter)
            {
                parent::__construct($app);
            }

            public function driver(?string $driver = null): PmsAdapter
            {
                return $this->adapter;
            }
        });
    }

    private function quote(PmsRoom $room): \Illuminate\Testing\TestResponse
    {
        return $this->get('/pms/get-quote?' . http_build_query([
            'property_id' => $room->id,
            'start_date' => '2031-06-10',
            'end_date' => '2031-06-13',
            'adults' => 2,
            'child' => 0,
        ]));
    }

    public function test_pms_quote_renders_shared_checkout_flow(): void
    {
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'stripe_publish_key'],
            ['value' => 'pk_test_shared_checkout', 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'stripe_secret_key'],
            ['value' => 'sk_test_shared_checkout', 'updated_at' => now(), 'created_at' => now()]
        );

        $room = PmsRoom::create([
            'pms_property_id' => 'property-shared-checkout',
            'pms_room_id' => 'room-shared-checkout',
            'pms_source' => 'hostfully',
            'name' => 'PMS Shared Checkout',
            'seo_url' => 'pms-shared-checkout',
            'status' => 'true',
            'active' => 1,
            'max_people' => 4,
            'currency' => 'USD',
        ]);

        $this->bindFakePmsManager();
        $response = $this->quote($room);

        $response->assertOk();
        $response->assertSee('id="checkout-form"', false);
        $response->assertSee('data-checkout-mode="pms"', false);
        $response->assertSee('pms/save-booking-data', false);
        $response->assertSee('name="pms_ajax"', false);
        $response->assertSee('id="stripe-card-element"', false);
        $response->assertSee('confirmCardPayment', false);
        $response->assertDontSee('id="save-booking-data"', false);
        $response->assertDontSee('CONFIRM AND BOOK NOW');
    }

    public function test_pms_checkout_shows_agreement_consent_when_owner_enables_it(): void
    {
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'stripe_publish_key'],
            ['value' => 'pk_test_shared_checkout', 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'stripe_secret_key'],
            ['value' => 'sk_test_shared_checkout', 'updated_at' => now(), 'created_at' => now()]
        );

        $room = PmsRoom::create([
            'pms_property_id' => 'property-pms-agreement-checkout',
            'pms_room_id' => 'room-pms-agreement-checkout',
            'pms_source' => 'hostfully',
            'name' => 'PMS Agreement Checkout',
            'seo_url' => 'pms-agreement-checkout',
            'status' => 'true',
            'active' => 1,
            'max_people' => 4,
            'currency' => 'USD',
            'rental_agreement_required' => 1,
            'rental_agreement_signing_mode' => 'post_confirmation',
        ]);

        $this->bindFakePmsManager();
        $response = $this->quote($room);

        $response->assertOk();
        $response->assertSee('name="rental_agreement_accepted"', false);
        $response->assertSee("After payment we'll email you a secure link to sign the agreement before your check-in.", false);
    }
}
