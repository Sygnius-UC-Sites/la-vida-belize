<?php

namespace Tests\Unit\Payment;

use App\Http\Controllers\Payment\PmsStripeController;
use App\Models\BookingRequest;
use App\Models\Pms\PmsRoom;
use App\Modules\VacationRental\Pms\PmsAdapter;
use App\Modules\VacationRental\Pms\PmsManager;
use App\Services\PmsBookingService;
use ReflectionMethod;
use Tests\TestCase;

class PmsStripeControllerTest extends TestCase
{
    public function test_non_recoverable_pms_validation_failures_are_refundable(): void
    {
        $method = new ReflectionMethod(PmsStripeController::class, 'shouldRefundNonRecoverablePmsFailure');
        $method->setAccessible(true);

        $controller = new PmsStripeController();

        $this->assertTrue($method->invoke($controller, [
            'ok' => false,
            'error' => 'Minimum stay requirement is not met.',
        ]));

        $this->assertTrue($method->invoke($controller, [
            'ok' => false,
            'error' => 'The selected dates are no longer available.',
        ]));

        $this->assertFalse($method->invoke($controller, [
            'ok' => false,
            'error' => 'Hostfully API timed out while creating the reservation.',
        ]));
    }

    public function test_pms_booking_service_preserves_hostfully_api_error_message(): void
    {
        $room = PmsRoom::create([
            'pms_property_id' => 'property-123',
            'pms_room_id' => 'room-123',
            'pms_source' => 'hostfully',
            'name' => 'Hostfully Test Listing',
            'seo_url' => 'hostfully-test-listing',
            'status' => 'true',
            'active' => 1,
            'max_people' => 4,
            'currency' => 'USD',
        ]);

        $booking = BookingRequest::create([
            'property_id' => $room->id,
            'pms_source' => 'hostfully',
            'pms_room_id' => 'room-123',
            'checkin' => '2027-01-01',
            'checkout' => '2027-01-04',
            'adults' => 2,
            'child' => 0,
            'total_guests' => 2,
            'total_pets' => 0,
            'total_night' => 3,
            'gross_amount' => 400,
            'sub_amount' => 400,
            'total_amount' => 400,
            'currency' => 'USD',
            'request_id' => 'hostfully-error-test',
            'booking_status' => 'pending-payment',
            'payment_status' => 'pending',
            'name' => 'Test Guest',
            'email' => 'guest@example.test',
            'after_total_fees' => '[]',
            'before_total_fees' => '[]',
        ]);

        $adapter = new class implements PmsAdapter {
            public function getName(): string { return 'hostfully'; }
            public function parseWebhook(array $payload): array { return []; }
            public function healthCheck(): array { return ['ok' => true]; }
            public function listProperties(array $options = []): array { return []; }
            public function getProperty(string $propertyId): array { return []; }
            public function listUnits(?string $propertyId = null): array { return []; }
            public function getAvailability(array $input): array
            {
                return [
                    'status' => 200,
                    'data' => [
                        'result' => [
                            ['date' => '2027-01-01', 'available' => true, 'minimumStay' => 1],
                            ['date' => '2027-01-02', 'available' => true, 'minimumStay' => 1],
                            ['date' => '2027-01-03', 'available' => true, 'minimumStay' => 1],
                        ],
                    ],
                ];
            }
            public function getRawQuote(array $input): array
            {
                return [
                    'status' => 200,
                    'data' => [
                        'quote' => [
                            'currency' => 'USD',
                            'totalAmount' => 400,
                            'rent' => ['rentNetPrice' => 400],
                        ],
                    ],
                ];
            }
            public function getQuote(array $input): array { return $this->getRawQuote($input); }
            public function createReservation(array $input): array
            {
                return [
                    'status' => 400,
                    'error' => ['apiErrorMessage' => 'Minimum stay requirement is not met.'],
                ];
            }
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

        $manager = new class(app(), $adapter) extends PmsManager {
            public function __construct($app, private PmsAdapter $adapter)
            {
                parent::__construct($app);
            }

            public function driver(?string $driver = null): PmsAdapter
            {
                return $this->adapter;
            }
        };

        $result = (new PmsBookingService($manager))->create($booking);

        $this->assertFalse($result['ok']);
        $this->assertSame('Minimum stay requirement is not met.', $result['error']);
    }
}
