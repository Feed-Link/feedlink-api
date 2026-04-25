<?php

namespace Tests\Feature\Geospatial;

use App\Models\User;
use App\Modules\Core\Entities\Tag;
use App\Modules\FoodListings\Entities\FoodListing;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Geospatial tests using real Kathmandu coordinates against the production PostGIS DB.
 * All data is wrapped in DatabaseTransactions and rolled back after each test.
 *
 * Reference points:
 *   Thamel        27.7172, 85.3240  (search origin)
 *   Durbarmarg    27.7097, 85.3180  ~1.0 km from Thamel
 *   Boudhanath    27.7215, 85.3620  ~3.8 km from Thamel
 *   Bhaktapur     27.6710, 85.4298  ~11.6 km from Thamel  (outside 5 km radius)
 */
class NearbyListingsTest extends TestCase
{
    // Search origin — Thamel, Kathmandu
    private const LAT = 27.7172;

    private const LNG = 85.3240;

    private User $donor;

    private User $recipient;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->donor = User::factory()->create(['email_verified_at' => now()]);
        $this->donor->assignRole('donor');

        $this->recipient = User::factory()->create(['email_verified_at' => now()]);
        $this->recipient->assignRole('recipient');
    }

    private function makeListing(float $lat, float $lng, array $overrides = []): FoodListing
    {
        return FoodListing::create(array_merge([
            'donor_id' => $this->donor->id,
            'title' => 'Test Food',
            'quantity' => '10 portions',
            'status' => 'active',
            'latitude' => $lat,
            'longitude' => $lng,
            'location' => Point::makeGeodetic($lat, $lng),
            'address' => 'Kathmandu',
            'expires_at' => now()->addHours(4),
            'pickup_before' => now()->addHours(6),
        ], $overrides));
    }

    // ─── Core radius filter ───────────────────────────────────────────────────

    public function test_listing_at_same_location_appears_in_nearby(): void
    {
        $listing = $this->makeListing(self::LAT, self::LNG, ['title' => 'Thamel food']);

        Passport::actingAs($this->recipient);
        $response = $this->getJson('/api/listings/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=5');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($listing->id, $ids, 'Listing at search origin should appear in results');
    }

    public function test_listing_3km_away_appears_within_5km_radius(): void
    {
        // Boudhanath ~3.8 km from Thamel
        $listing = $this->makeListing(27.7215, 85.3620, ['title' => 'Boudhanath food']);

        Passport::actingAs($this->recipient);
        $response = $this->getJson('/api/listings/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=5');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($listing->id, $ids, 'Listing ~3.8 km away should be within 5 km radius');
    }

    public function test_listing_outside_radius_is_excluded(): void
    {
        // Bhaktapur ~11.6 km from Thamel — should NOT appear in 5 km radius
        $listing = $this->makeListing(27.6710, 85.4298, ['title' => 'Bhaktapur food']);

        Passport::actingAs($this->recipient);
        $response = $this->getJson('/api/listings/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=5');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($listing->id, $ids, 'Listing ~11.6 km away should be outside 5 km radius');
    }

    public function test_listing_outside_radius_appears_when_radius_extended(): void
    {
        // Bhaktapur ~11.6 km — should appear with 15 km radius
        $listing = $this->makeListing(27.6710, 85.4298, ['title' => 'Bhaktapur food wide']);

        Passport::actingAs($this->recipient);
        $response = $this->getJson('/api/listings/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=15');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($listing->id, $ids, 'Listing ~11.6 km should appear with 15 km radius');
    }

    // ─── Ordering ─────────────────────────────────────────────────────────────

    public function test_results_ordered_by_distance_ascending(): void
    {
        // Bhaktapur far, Boudhanath medium, Thamel close — expect Thamel first
        $thamel = $this->makeListing(self::LAT, self::LNG, ['title' => 'A Thamel']);
        $boudhanath = $this->makeListing(27.7215, 85.3620, ['title' => 'B Boudhanath']);

        Passport::actingAs($this->recipient);
        $response = $this->getJson('/api/listings/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=10');

        $response->assertOk();
        $data = $response->json('data');

        $thamelPos = null;
        $boudhanathPos = null;

        foreach ($data as $index => $item) {
            if ($item['id'] === $thamel->id) {
                $thamelPos = $index;
            }
            if ($item['id'] === $boudhanath->id) {
                $boudhanathPos = $index;
            }
        }

        $this->assertNotNull($thamelPos, 'Thamel listing should be in results');
        $this->assertNotNull($boudhanathPos, 'Boudhanath listing should be in results');
        $this->assertLessThan($boudhanathPos, $thamelPos, 'Closer listing (Thamel) should appear before farther (Boudhanath)');
    }

    // ─── distance_km in response ──────────────────────────────────────────────

    public function test_response_includes_distance_km_field(): void
    {
        $this->makeListing(self::LAT, self::LNG);

        Passport::actingAs($this->recipient);
        $response = $this->getJson('/api/listings/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=5');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotEmpty($data, 'Should have at least one listing');
        $this->assertArrayHasKey('distance_km', $data[0], 'Each listing should include distance_km');
        $this->assertIsNumeric($data[0]['distance_km'], 'distance_km should be numeric');
        $this->assertGreaterThanOrEqual(0, $data[0]['distance_km'], 'distance_km should be non-negative');
    }

    public function test_listing_at_origin_has_near_zero_distance(): void
    {
        $listing = $this->makeListing(self::LAT, self::LNG, ['title' => 'Origin listing']);

        Passport::actingAs($this->recipient);
        $response = $this->getJson('/api/listings/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=5');

        $response->assertOk();
        $data = $response->json('data');
        $match = collect($data)->firstWhere('id', $listing->id);

        $this->assertNotNull($match);
        $this->assertLessThan(0.1, $match['distance_km'], 'Listing at same coords should have ~0 km distance');
    }

    public function test_boudhanath_distance_is_approximately_correct(): void
    {
        // Boudhanath to Thamel should be ~3–4 km
        $listing = $this->makeListing(27.7215, 85.3620, ['title' => 'Boudhanath distance check']);

        Passport::actingAs($this->recipient);
        $response = $this->getJson('/api/listings/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=10');

        $response->assertOk();
        $data = $response->json('data');
        $match = collect($data)->firstWhere('id', $listing->id);

        $this->assertNotNull($match);
        $this->assertGreaterThan(2.0, $match['distance_km'], 'Boudhanath should be > 2 km from Thamel');
        $this->assertLessThan(6.0, $match['distance_km'], 'Boudhanath should be < 6 km from Thamel');
    }

    // ─── Status filter ────────────────────────────────────────────────────────

    public function test_only_active_listings_returned_by_default(): void
    {
        $active = $this->makeListing(self::LAT, self::LNG, ['status' => 'active', 'title' => 'Active']);
        $claimed = $this->makeListing(self::LAT, self::LNG, ['status' => 'claimed', 'title' => 'Claimed']);
        $completed = $this->makeListing(self::LAT, self::LNG, ['status' => 'completed', 'title' => 'Completed']);

        Passport::actingAs($this->recipient);
        $response = $this->getJson('/api/listings/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=5');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains($active->id, $ids, 'Active listing should appear');
        $this->assertNotContains($claimed->id, $ids, 'Claimed listing should not appear');
        $this->assertNotContains($completed->id, $ids, 'Completed listing should not appear');
    }

    // ─── Tag-based food_type filter ───────────────────────────────────────────

    public function test_food_type_human_filter_excludes_animal_only_listings(): void
    {
        // Attach tags directly via the pivot table to control filtering precisely
        $humanListing = $this->makeListing(self::LAT, self::LNG, ['title' => 'For humans']);
        $animalListing = $this->makeListing(self::LAT, self::LNG, ['title' => 'For animals']);

        // Sync tags using tag slugs that exist in production DB
        $humanTagId = Tag::where('slug', 'for_humans')->value('id');
        $animalTagId = Tag::where('slug', 'for_animals')->value('id');

        if ($humanTagId && $animalTagId) {
            $humanListing->tags()->sync([$humanTagId]);
            $animalListing->tags()->sync([$animalTagId]);

            Passport::actingAs($this->recipient);
            $response = $this->getJson('/api/listings/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=5&food_type=human');

            $response->assertOk();
            $ids = collect($response->json('data'))->pluck('id')->toArray();

            $this->assertContains($humanListing->id, $ids, 'for_humans tagged listing should appear with food_type=human');
            $this->assertNotContains($animalListing->id, $ids, 'for_animals tagged listing should not appear with food_type=human');
        } else {
            $this->markTestSkipped('Tags (for_humans / for_animals) not found in DB — seed tags first');
        }
    }

    // ─── Auth guard ───────────────────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/listings/nearby?lat='.self::LAT.'&lng='.self::LNG.'&radius=5');
        $response->assertUnauthorized();
    }

    public function test_missing_lat_lng_returns_422(): void
    {
        Passport::actingAs($this->recipient);
        $response = $this->getJson('/api/listings/nearby');
        $response->assertUnprocessable();
    }
}
