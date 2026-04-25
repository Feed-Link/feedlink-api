<?php

namespace Tests\Feature\Geospatial;

use App\Models\User;
use App\Modules\FoodListings\Entities\FoodRequest;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Geospatial tests for food requests using real Kathmandu coordinates.
 *
 * Reference points:
 *   Thamel     27.7172, 85.3240  (search origin, ~0 km)
 *   Boudhanath 27.7215, 85.3620  (~3.8 km)
 *   Bhaktapur  27.6710, 85.4298  (~11.6 km)
 */
class NearbyRequestsTest extends TestCase
{
    private const LAT = 27.7172;

    private const LNG = 85.3240;

    private User $donor;

    private User $recipient;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->recipient = User::factory()->create(['email_verified_at' => now()]);
        $this->recipient->assignRole('recipient');

        $this->donor = User::factory()->create(['email_verified_at' => now()]);
        $this->donor->assignRole('donor');
    }

    private function makeRequest(float $lat, float $lng, array $overrides = []): FoodRequest
    {
        return FoodRequest::create(array_merge([
            'recipient_id' => $this->recipient->id,
            'title' => 'Need food',
            'quantity_needed' => '10 kg',
            'status' => 'open',
            'needed_by' => now()->addDays(2),
            'expires_at' => now()->addDays(2),
            'latitude' => $lat,
            'longitude' => $lng,
            'location' => Point::makeGeodetic($lat, $lng),
            'address' => 'Kathmandu',
        ], $overrides));
    }

    // ─── Core radius filter ───────────────────────────────────────────────────

    public function test_request_at_origin_appears_in_nearby(): void
    {
        $request = $this->makeRequest(self::LAT, self::LNG, ['title' => 'Thamel request']);

        Passport::actingAs($this->donor);
        $response = $this->getJson('/api/requests/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=5');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($request->id, $ids);
    }

    public function test_request_3km_away_appears_within_5km_radius(): void
    {
        $request = $this->makeRequest(27.7215, 85.3620, ['title' => 'Boudhanath request']);

        Passport::actingAs($this->donor);
        $response = $this->getJson('/api/requests/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=5');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($request->id, $ids, 'Request ~3.8 km away should appear within 5 km radius');
    }

    public function test_request_outside_radius_is_excluded(): void
    {
        $request = $this->makeRequest(27.6710, 85.4298, ['title' => 'Bhaktapur request']);

        Passport::actingAs($this->donor);
        $response = $this->getJson('/api/requests/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=5');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($request->id, $ids, 'Request ~11.6 km away should be excluded from 5 km radius');
    }

    // ─── Ordering ─────────────────────────────────────────────────────────────

    public function test_results_ordered_by_distance_ascending(): void
    {
        $thamel = $this->makeRequest(self::LAT, self::LNG, ['title' => 'Near request']);
        $boudhanath = $this->makeRequest(27.7215, 85.3620, ['title' => 'Far request']);

        Passport::actingAs($this->donor);
        $response = $this->getJson('/api/requests/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=10');

        $response->assertOk();
        $data = $response->json('data');

        $thamelPos = null;
        $boudhanathPos = null;

        foreach ($data as $i => $item) {
            if ($item['id'] === $thamel->id) {
                $thamelPos = $i;
            }
            if ($item['id'] === $boudhanath->id) {
                $boudhanathPos = $i;
            }
        }

        $this->assertNotNull($thamelPos);
        $this->assertNotNull($boudhanathPos);
        $this->assertLessThan($boudhanathPos, $thamelPos, 'Closer request should appear first');
    }

    // ─── distance_km ─────────────────────────────────────────────────────────

    public function test_response_includes_distance_km(): void
    {
        $this->makeRequest(self::LAT, self::LNG);

        Passport::actingAs($this->donor);
        $response = $this->getJson('/api/requests/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=5');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('distance_km', $data[0]);
        $this->assertGreaterThanOrEqual(0, $data[0]['distance_km']);
    }

    public function test_boudhanath_distance_within_expected_range(): void
    {
        $request = $this->makeRequest(27.7215, 85.3620, ['title' => 'Boudhanath dist check']);

        Passport::actingAs($this->donor);
        $response = $this->getJson('/api/requests/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=10');

        $response->assertOk();
        $match = collect($response->json('data'))->firstWhere('id', $request->id);

        $this->assertNotNull($match);
        $this->assertGreaterThan(2.0, $match['distance_km'], 'Boudhanath should be > 2 km');
        $this->assertLessThan(6.0, $match['distance_km'], 'Boudhanath should be < 6 km');
    }

    // ─── Status filter ────────────────────────────────────────────────────────

    public function test_only_open_requests_returned_by_default(): void
    {
        $open = $this->makeRequest(self::LAT, self::LNG, ['status' => 'open', 'title' => 'Open']);
        $accepted = $this->makeRequest(self::LAT, self::LNG, ['status' => 'accepted', 'title' => 'Accepted']);
        $fulfilled = $this->makeRequest(self::LAT, self::LNG, ['status' => 'fulfilled', 'title' => 'Fulfilled']);

        Passport::actingAs($this->donor);
        $response = $this->getJson('/api/requests/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=5');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains($open->id, $ids, 'Open request should appear');
        $this->assertNotContains($accepted->id, $ids, 'Accepted request should not appear by default');
        $this->assertNotContains($fulfilled->id, $ids, 'Fulfilled request should not appear');
    }

    // ─── Donor browse endpoint (/donor/requests) ──────────────────────────────

    public function test_donor_requests_endpoint_uses_stored_location_fallback(): void
    {
        // Update donor's stored location to Thamel
        $this->donor->update(['latitude' => self::LAT, 'longitude' => self::LNG]);

        $request = $this->makeRequest(self::LAT, self::LNG, ['title' => 'Near donor']);

        Passport::actingAs($this->donor);

        // No lat/lng in query — should fall back to donor's stored location
        $response = $this->getJson('/api/donor/requests?radius=5');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($request->id, $ids, 'Should use stored location when lat/lng not provided');
    }

    public function test_donor_requests_endpoint_fails_without_location(): void
    {
        // Donor has no stored location
        $this->donor->update(['latitude' => null, 'longitude' => null]);

        Passport::actingAs($this->donor);
        $response = $this->getJson('/api/donor/requests?radius=5');

        $response->assertStatus(422);
    }

    // ─── Auth guard ───────────────────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/requests/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=5');
        $response->assertUnauthorized();
    }
}
