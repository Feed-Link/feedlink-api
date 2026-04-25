# Donor Power Features Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add four donor-side features — impact stats, quick re-list, early listing recovery (reopen), and cancel-when-claimed extension — with full test coverage.

**Architecture:** All features extend the existing `DonorFoodListingController` → `FoodListingService` → `FoodListingRepository` stack. `ListingClaimRepository` gets two new bulk-update methods. Two `NotificationTypeEnum` cases are added. No new migrations required.

**Tech Stack:** Laravel 12, Passport auth, Spatie Permission (role middleware), `SendNotificationJob` (queued notifications), PHPUnit, Laravel Pint

---

### Task 1: Foundations — Enum cases + Repository methods

No tests needed here; these are verified through feature tests in Tasks 2–5.

**Files:**
- Modify: `app/Modules/Core/Enums/NotificationTypeEnum.php`
- Modify: `app/Modules/FoodListings/Repositories/ListingClaimRepository.php`
- Modify: `app/Modules/FoodListings/Repositories/FoodListingRepository.php`

- [ ] **Step 1: Add two new enum cases to NotificationTypeEnum**

Replace full content of `app/Modules/Core/Enums/NotificationTypeEnum.php`:

```php
<?php

namespace App\Modules\Core\Enums;

enum NotificationTypeEnum: string
{
    case CLAIM_RECEIVED = 'claim_received';
    case CLAIM_CONFIRMED = 'claim_confirmed';
    case CLAIM_REJECTED = 'claim_rejected';
    case PICKUP_COMPLETED = 'pickup_completed';
    case LISTING_EXPIRED_UNCOLLECTED = 'listing_expired_uncollected';
    case REQUEST_ACCEPTED = 'request_accepted';
    case ACCEPTANCE_CONFIRMED = 'acceptance_confirmed';
    case ACCEPTANCE_REJECTED = 'acceptance_rejected';
    case ACCEPTANCE_WITHDRAWN = 'acceptance_withdrawn';
    case REQUEST_FULFILLED = 'request_fulfilled';
    case LISTING_REOPENED = 'listing_reopened';
    case LISTING_CANCELLED = 'listing_cancelled';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

- [ ] **Step 2: Add bulk-update methods to ListingClaimRepository**

Replace full content of `app/Modules/FoodListings/Repositories/ListingClaimRepository.php`:

```php
<?php

namespace App\Modules\FoodListings\Repositories;

use App\Modules\Core\Enums\ClaimStatusEnum;
use App\Modules\Core\Repositories\BaseRepository;
use App\Modules\FoodListings\Entities\ListingClaim;

class ListingClaimRepository extends BaseRepository
{
    public function __construct(protected ListingClaim $listingClaim)
    {
        $this->model = $listingClaim;
        parent::__construct();
    }

    public function fetchClaimsForListing(string $listingId, array $params = []): object
    {
        $filters = $params['filter'] ?? [];
        $filters[] = ['filter_by' => 'food_listing_id', 'value' => $listingId];
        $params['filter'] = $filters;

        $rows = $this->model::query()->where('food_listing_id', $listingId);

        return $this->getFiltered($rows, $params, ['recipient']);
    }

    public function hasPendingClaim(string $listingId, string $recipientId): bool
    {
        return $this->model::query()
            ->where('food_listing_id', $listingId)
            ->where('recipient_id', $recipientId)
            ->where('status', 'pending')
            ->exists();
    }

    public function fetchByClaim(string $foodListingId, string $recipientId): ?object
    {
        return $this->model::query()
            ->where('food_listing_id', $foodListingId)
            ->where('recipient_id', $recipientId)
            ->where('status', 'pending')
            ->first();
    }

    public function resetAllClaimsForListing(string $listingId): void
    {
        $this->model::query()
            ->where('food_listing_id', $listingId)
            ->update(['status' => ClaimStatusEnum::PENDING->value]);
    }

    public function rejectAllClaimsForListing(string $listingId): void
    {
        $this->model::query()
            ->where('food_listing_id', $listingId)
            ->update(['status' => ClaimStatusEnum::REJECTED->value]);
    }
}
```

- [ ] **Step 3: Add getDonorStats() to FoodListingRepository**

Add this import after the existing `use` statements in `app/Modules/FoodListings/Repositories/FoodListingRepository.php`:

```php
use App\Modules\Core\Enums\ListingStatusEnum;
```

Add this method before the closing `}` of the class:

```php
public function getDonorStats(string $donorId): array
{
    $counts = $this->model::query()
        ->where('donor_id', $donorId)
        ->selectRaw('status, count(*) as total')
        ->groupBy('status')
        ->pluck('total', 'status')
        ->toArray();

    $uniqueRecipients = $this->model::query()
        ->where('donor_id', $donorId)
        ->where('status', ListingStatusEnum::COMPLETED->value)
        ->whereNotNull('claimed_by')
        ->distinct('claimed_by')
        ->count('claimed_by');

    return [
        'listings_completed'       => (int) ($counts[ListingStatusEnum::COMPLETED->value] ?? 0),
        'listings_active'          => (int) ($counts[ListingStatusEnum::ACTIVE->value] ?? 0),
        'listings_cancelled'       => (int) ($counts[ListingStatusEnum::CANCELLED->value] ?? 0),
        'listings_expired'         => (int) ($counts[ListingStatusEnum::EXPIRED->value] ?? 0),
        'unique_recipients_served' => $uniqueRecipients,
    ];
}
```

- [ ] **Step 4: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Modules/Core/Enums/NotificationTypeEnum.php \
        app/Modules/FoodListings/Repositories/ListingClaimRepository.php \
        app/Modules/FoodListings/Repositories/FoodListingRepository.php
git commit -m "feat: add LISTING_REOPENED/CANCELLED enum cases and claim/stats repo methods"
```

---

### Task 2: Impact Stats — GET /donor/stats (TDD)

**Files:**
- Create: `tests/Feature/FoodListings/DonorStatsTest.php`
- Modify: `app/Modules/FoodListings/Services/FoodListingService.php`
- Modify: `app/Modules/FoodListings/Controllers/DonorFoodListingController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/FoodListings/DonorStatsTest.php`:

```php
<?php

namespace Tests\Feature\FoodListings;

use App\Models\User;
use App\Modules\FoodListings\Entities\FoodListing;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DonorStatsTest extends TestCase
{
    protected User $donor;
    protected User $recipient1;
    protected User $recipient2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->donor = User::factory()->create(['email_verified_at' => now()]);
        $this->donor->assignRole('donor');

        $this->recipient1 = User::factory()->create(['email_verified_at' => now()]);
        $this->recipient1->assignRole('recipient');

        $this->recipient2 = User::factory()->create(['email_verified_at' => now()]);
        $this->recipient2->assignRole('recipient');
    }

    private function makeListing(array $overrides = []): void
    {
        FoodListing::create(array_merge([
            'donor_id'      => $this->donor->id,
            'title'         => 'Test Food',
            'quantity'      => '5 portions',
            'status'        => 'active',
            'expires_at'    => now()->addHours(2),
            'pickup_before' => now()->addHours(4),
            'latitude'      => 27.7172,
            'longitude'     => 85.3240,
            'address'       => 'Thamel, Kathmandu',
            'location'      => ['lat' => 27.7172, 'long' => 85.3240],
        ], $overrides));
    }

    public function test_donor_gets_correct_stats(): void
    {
        Passport::actingAs($this->donor);

        $this->makeListing(['status' => 'completed', 'claimed_by' => $this->recipient1->id]);
        $this->makeListing(['status' => 'completed', 'claimed_by' => $this->recipient2->id]);
        $this->makeListing(['status' => 'completed', 'claimed_by' => $this->recipient1->id]);
        $this->makeListing(['status' => 'active']);
        $this->makeListing(['status' => 'cancelled']);
        $this->makeListing(['status' => 'expired']);

        $response = $this->getJson('/api/donor/stats');

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('data.listings_completed', 3)
            ->assertJsonPath('data.listings_active', 1)
            ->assertJsonPath('data.listings_cancelled', 1)
            ->assertJsonPath('data.listings_expired', 1)
            ->assertJsonPath('data.unique_recipients_served', 2);
    }

    public function test_donor_with_no_listings_gets_all_zeros(): void
    {
        Passport::actingAs($this->donor);

        $response = $this->getJson('/api/donor/stats');

        $response->assertStatus(200)
            ->assertJsonPath('data.listings_completed', 0)
            ->assertJsonPath('data.listings_active', 0)
            ->assertJsonPath('data.unique_recipients_served', 0);
    }

    public function test_stats_are_scoped_to_authenticated_donor_only(): void
    {
        $otherDonor = User::factory()->create(['email_verified_at' => now()]);
        $otherDonor->assignRole('donor');

        FoodListing::create([
            'donor_id'      => $otherDonor->id,
            'title'         => 'Other food',
            'quantity'      => '5 portions',
            'status'        => 'completed',
            'claimed_by'    => $this->recipient1->id,
            'expires_at'    => now()->addHours(2),
            'pickup_before' => now()->addHours(4),
            'latitude'      => 27.7172,
            'longitude'     => 85.3240,
            'address'       => 'Thamel, Kathmandu',
            'location'      => ['lat' => 27.7172, 'long' => 85.3240],
        ]);

        Passport::actingAs($this->donor);

        $response = $this->getJson('/api/donor/stats');

        $response->assertStatus(200)
            ->assertJsonPath('data.listings_completed', 0)
            ->assertJsonPath('data.unique_recipients_served', 0);
    }

    public function test_recipient_cannot_access_stats(): void
    {
        Passport::actingAs($this->recipient1);

        $response = $this->getJson('/api/donor/stats');

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact tests/Feature/FoodListings/DonorStatsTest.php
```

Expected: FAIL — route not found (404).

- [ ] **Step 3: Add getDonorStats() to FoodListingService**

In `app/Modules/FoodListings/Services/FoodListingService.php`, add after `getListingsForDonor()`:

```php
public function getDonorStats(string $donorId): array
{
    return $this->foodListingRepository->getDonorStats($donorId);
}
```

- [ ] **Step 4: Add stats() to DonorFoodListingController**

In `app/Modules/FoodListings/Controllers/DonorFoodListingController.php`, add after `index()`:

```php
public function stats(): JsonResponse
{
    try {
        $stats = $this->foodListingService->getDonorStats(Auth::id());

        return $this->success('Stats retrieved', Response::HTTP_OK, $stats);
    } catch (Exception $exception) {
        return $this->handleException($exception);
    }
}
```

- [ ] **Step 5: Register the route**

In `routes/api.php`, inside the `donor` prefix group, add before the first `listings` route:

```php
Route::get('stats', [DonorFoodListingController::class, 'stats']);
```

- [ ] **Step 6: Run tests and verify they pass**

```bash
php artisan test --compact tests/Feature/FoodListings/DonorStatsTest.php
```

Expected: 4 tests pass.

- [ ] **Step 7: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Feature/FoodListings/DonorStatsTest.php \
        app/Modules/FoodListings/Services/FoodListingService.php \
        app/Modules/FoodListings/Controllers/DonorFoodListingController.php \
        routes/api.php
git commit -m "feat: add GET /donor/stats impact stats endpoint"
```

---

### Task 3: Quick Re-list — POST /donor/listings/{id}/relist (TDD)

**Files:**
- Create: `tests/Feature/FoodListings/DonorRelistTest.php`
- Modify: `app/Modules/FoodListings/Services/FoodListingService.php`
- Modify: `app/Modules/FoodListings/Controllers/DonorFoodListingController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/FoodListings/DonorRelistTest.php`:

```php
<?php

namespace Tests\Feature\FoodListings;

use App\Models\User;
use App\Modules\FoodListings\Entities\FoodListing;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DonorRelistTest extends TestCase
{
    protected User $donor;
    protected User $otherDonor;
    protected FoodListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->donor = User::factory()->create(['email_verified_at' => now()]);
        $this->donor->assignRole('donor');

        $this->otherDonor = User::factory()->create(['email_verified_at' => now()]);
        $this->otherDonor->assignRole('donor');

        $this->listing = FoodListing::create([
            'donor_id'            => $this->donor->id,
            'title'               => 'Leftover Dal Bhat',
            'description'         => 'Freshly cooked',
            'quantity'            => '15 portions',
            'pickup_instructions' => 'Call before coming',
            'photos'              => ['https://example.com/photo.jpg'],
            'status'              => 'completed',
            'expires_at'          => now()->subHours(1),
            'pickup_before'       => now()->subHours(1),
            'latitude'            => 27.7172,
            'longitude'           => 85.3240,
            'address'             => 'Thamel, Kathmandu',
            'location'            => ['lat' => 27.7172, 'long' => 85.3240],
        ]);
    }

    public function test_donor_gets_relist_template_with_correct_fields(): void
    {
        Passport::actingAs($this->donor);

        $response = $this->postJson("/api/donor/listings/{$this->listing->id}/relist");

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('data.title', 'Leftover Dal Bhat')
            ->assertJsonPath('data.description', 'Freshly cooked')
            ->assertJsonPath('data.quantity', '15 portions')
            ->assertJsonPath('data.pickup_instructions', 'Call before coming')
            ->assertJsonPath('data.address', 'Thamel, Kathmandu')
            ->assertJsonPath('data.latitude', 27.7172)
            ->assertJsonPath('data.longitude', 85.3240);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayNotHasKey('status', $data);
        $this->assertArrayNotHasKey('expires_at', $data);
        $this->assertArrayNotHasKey('pickup_before', $data);
        $this->assertArrayNotHasKey('confirmed_at', $data);
    }

    public function test_relist_does_not_create_a_new_listing(): void
    {
        Passport::actingAs($this->donor);

        $countBefore = FoodListing::count();

        $this->postJson("/api/donor/listings/{$this->listing->id}/relist");

        $this->assertSame($countBefore, FoodListing::count());
    }

    public function test_relist_works_for_any_listing_status(): void
    {
        Passport::actingAs($this->donor);

        foreach (['active', 'expired', 'cancelled', 'completed'] as $status) {
            $this->listing->update(['status' => $status]);

            $response = $this->postJson("/api/donor/listings/{$this->listing->id}/relist");

            $response->assertStatus(200);
        }
    }

    public function test_non_owner_cannot_relist(): void
    {
        Passport::actingAs($this->otherDonor);

        $response = $this->postJson("/api/donor/listings/{$this->listing->id}/relist");

        $response->assertStatus(403);
    }

    public function test_relist_returns_404_for_missing_listing(): void
    {
        Passport::actingAs($this->donor);

        $response = $this->postJson('/api/donor/listings/nonexistent-uuid/relist');

        $response->assertStatus(404);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact tests/Feature/FoodListings/DonorRelistTest.php
```

Expected: FAIL — route not found.

- [ ] **Step 3: Add getRelistTemplate() to FoodListingService**

In `app/Modules/FoodListings/Services/FoodListingService.php`, add after `getDonorStats()`:

```php
public function getRelistTemplate(string $id, string $donorId): array
{
    $listing = $this->foodListingRepository->fetchBy('id', $id, ['tags']);

    if (! $listing) {
        throw new Exception('Listing not found', 404);
    }

    if ($listing->donor_id !== $donorId) {
        throw new Exception('Unauthorized', 403);
    }

    return [
        'title'               => $listing->title,
        'description'         => $listing->description,
        'quantity'            => $listing->quantity,
        'tags'                => $listing->tags->pluck('slug')->toArray(),
        'photos'              => $listing->photos ?? [],
        'pickup_instructions' => $listing->pickup_instructions,
        'address'             => $listing->address,
        'latitude'            => (float) $listing->latitude,
        'longitude'           => (float) $listing->longitude,
    ];
}
```

- [ ] **Step 4: Add relist() to DonorFoodListingController**

In `app/Modules/FoodListings/Controllers/DonorFoodListingController.php`, add after `stats()`:

```php
public function relist(string $id): JsonResponse
{
    try {
        $template = $this->foodListingService->getRelistTemplate($id, Auth::id());

        return $this->success('Listing template retrieved', Response::HTTP_OK, $template);
    } catch (Exception $exception) {
        return $this->handleException($exception);
    }
}
```

- [ ] **Step 5: Register the route**

In `routes/api.php`, inside the `donor` prefix group, add after `DELETE listings/{id}`:

```php
Route::post('listings/{id}/relist', [DonorFoodListingController::class, 'relist']);
```

- [ ] **Step 6: Run tests and verify they pass**

```bash
php artisan test --compact tests/Feature/FoodListings/DonorRelistTest.php
```

Expected: 5 tests pass.

- [ ] **Step 7: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Feature/FoodListings/DonorRelistTest.php \
        app/Modules/FoodListings/Services/FoodListingService.php \
        app/Modules/FoodListings/Controllers/DonorFoodListingController.php \
        routes/api.php
git commit -m "feat: add POST /donor/listings/{id}/relist quick re-list endpoint"
```

---

### Task 4: Reopen Listing — POST /donor/listings/{id}/reopen (TDD)

**Files:**
- Create: `tests/Feature/FoodListings/DonorReopenListingTest.php`
- Modify: `app/Modules/FoodListings/Services/FoodListingService.php`
- Modify: `app/Modules/FoodListings/Controllers/DonorFoodListingController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/FoodListings/DonorReopenListingTest.php`:

```php
<?php

namespace Tests\Feature\FoodListings;

use App\Models\User;
use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\FoodListings\Entities\ListingClaim;
use App\Modules\Notifications\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DonorReopenListingTest extends TestCase
{
    protected User $donor;
    protected User $confirmedRecipient;
    protected User $otherRecipient;
    protected FoodListing $listing;
    protected ListingClaim $confirmedClaim;
    protected ListingClaim $rejectedClaim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->donor = User::factory()->create(['email_verified_at' => now()]);
        $this->donor->assignRole('donor');

        $this->confirmedRecipient = User::factory()->create(['email_verified_at' => now()]);
        $this->confirmedRecipient->assignRole('recipient');

        $this->otherRecipient = User::factory()->create(['email_verified_at' => now()]);
        $this->otherRecipient->assignRole('recipient');

        $this->listing = FoodListing::create([
            'donor_id'      => $this->donor->id,
            'title'         => 'Dal Bhat',
            'quantity'      => '10 portions',
            'status'        => 'claimed',
            'claimed_by'    => $this->confirmedRecipient->id,
            'confirmed_at'  => now(),
            'expires_at'    => now()->addHours(3),
            'pickup_before' => now()->addHours(5),
            'latitude'      => 27.7172,
            'longitude'     => 85.3240,
            'address'       => 'Thamel, Kathmandu',
            'location'      => ['lat' => 27.7172, 'long' => 85.3240],
        ]);

        $this->confirmedClaim = ListingClaim::create([
            'food_listing_id' => $this->listing->id,
            'recipient_id'    => $this->confirmedRecipient->id,
            'status'          => 'confirmed',
        ]);

        $this->rejectedClaim = ListingClaim::create([
            'food_listing_id' => $this->listing->id,
            'recipient_id'    => $this->otherRecipient->id,
            'status'          => 'rejected',
        ]);
    }

    public function test_donor_can_reopen_claimed_listing(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $response = $this->postJson("/api/donor/listings/{$this->listing->id}/reopen");

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('food_listings', [
            'id'           => $this->listing->id,
            'status'       => 'active',
            'claimed_by'   => null,
            'confirmed_at' => null,
        ]);
    }

    public function test_reopen_restores_all_claims_to_pending(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $this->postJson("/api/donor/listings/{$this->listing->id}/reopen");

        $this->assertDatabaseHas('listing_claims', [
            'id'     => $this->confirmedClaim->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('listing_claims', [
            'id'     => $this->rejectedClaim->id,
            'status' => 'pending',
        ]);
    }

    public function test_reopen_notifies_previously_confirmed_recipient(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $this->postJson("/api/donor/listings/{$this->listing->id}/reopen");

        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return $job->userId === $this->confirmedRecipient->id
                && $job->type === NotificationTypeEnum::LISTING_REOPENED->value;
        });
    }

    public function test_cannot_reopen_active_listing(): void
    {
        Passport::actingAs($this->donor);

        $this->listing->update(['status' => 'active', 'claimed_by' => null, 'confirmed_at' => null]);

        $response = $this->postJson("/api/donor/listings/{$this->listing->id}/reopen");

        $response->assertStatus(400);
    }

    public function test_non_owner_cannot_reopen_listing(): void
    {
        $otherDonor = User::factory()->create(['email_verified_at' => now()]);
        $otherDonor->assignRole('donor');
        Passport::actingAs($otherDonor);

        $response = $this->postJson("/api/donor/listings/{$this->listing->id}/reopen");

        $response->assertStatus(403);
    }

    public function test_reopen_returns_404_for_missing_listing(): void
    {
        Passport::actingAs($this->donor);

        $response = $this->postJson('/api/donor/listings/nonexistent-uuid/reopen');

        $response->assertStatus(404);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact tests/Feature/FoodListings/DonorReopenListingTest.php
```

Expected: FAIL — route not found.

- [ ] **Step 3: Inject ListingClaimRepository into FoodListingService**

At the top of `app/Modules/FoodListings/Services/FoodListingService.php`, add this import after the existing `use` statements:

```php
use App\Modules\FoodListings\Repositories\ListingClaimRepository;
```

Replace the constructor:

```php
public function __construct(
    public FoodListingRepository $foodListingRepository,
    public ListingClaimRepository $listingClaimRepository,
) {}
```

- [ ] **Step 4: Add reopenListing() to FoodListingService**

Add after `getRelistTemplate()`:

```php
public function reopenListing(string $id, string $donorId): object
{
    $listing = $this->foodListingRepository->fetchBy('id', $id, ['donor']);

    if (! $listing) {
        throw new Exception('Listing not found', 404);
    }

    if ($listing->donor_id !== $donorId) {
        throw new Exception('Unauthorized', 403);
    }

    if ($listing->status !== ListingStatusEnum::CLAIMED->value) {
        throw new Exception('Listing is not in claimed status', 400);
    }

    $previousRecipientId = $listing->claimed_by;
    $donorName = $listing->donor->name;

    $listing->update([
        'status'           => ListingStatusEnum::ACTIVE->value,
        'claimed_by'       => null,
        'confirmed_at'     => null,
        'listing_claim_id' => null,
    ]);

    $this->listingClaimRepository->resetAllClaimsForListing($id);

    if ($previousRecipientId) {
        SendNotificationJob::dispatch(
            $previousRecipientId,
            NotificationTypeEnum::LISTING_REOPENED->value,
            'Listing reopened',
            "{$donorName} has reopened '{$listing->title}' — your claim is back in the queue.",
            [
                'listing_id'    => $listing->id,
                'listing_title' => $listing->title,
            ]
        );
    }

    return $listing->fresh(['donor', 'tags']);
}
```

- [ ] **Step 5: Add reopen() to DonorFoodListingController**

Add after `relist()`:

```php
public function reopen(string $id): JsonResponse
{
    try {
        DB::beginTransaction();

        $listing = $this->foodListingService->reopenListing($id, Auth::id());

        DB::commit();

        return $this->success('Listing reopened successfully', Response::HTTP_OK, new FoodListingResource($listing));
    } catch (Exception $exception) {
        DB::rollBack();

        return $this->handleException($exception);
    }
}
```

- [ ] **Step 6: Register the route**

In `routes/api.php`, inside the `donor` prefix group, add after `listings/{id}/relist`:

```php
Route::post('listings/{id}/reopen', [DonorFoodListingController::class, 'reopen']);
```

- [ ] **Step 7: Run tests and verify they pass**

```bash
php artisan test --compact tests/Feature/FoodListings/DonorReopenListingTest.php
```

Expected: 6 tests pass.

- [ ] **Step 8: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Feature/FoodListings/DonorReopenListingTest.php \
        app/Modules/FoodListings/Services/FoodListingService.php \
        app/Modules/FoodListings/Controllers/DonorFoodListingController.php \
        routes/api.php
git commit -m "feat: add POST /donor/listings/{id}/reopen early listing recovery"
```

---

### Task 5: Cancel Claimed Listing — extend DELETE /donor/listings/{id} (TDD)

**Files:**
- Create: `tests/Feature/FoodListings/DonorCancelClaimedListingTest.php`
- Modify: `app/Modules/FoodListings/Services/FoodListingService.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/FoodListings/DonorCancelClaimedListingTest.php`:

```php
<?php

namespace Tests\Feature\FoodListings;

use App\Models\User;
use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\FoodListings\Entities\ListingClaim;
use App\Modules\Notifications\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DonorCancelClaimedListingTest extends TestCase
{
    protected User $donor;
    protected User $confirmedRecipient;
    protected FoodListing $listing;
    protected ListingClaim $confirmedClaim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->donor = User::factory()->create(['email_verified_at' => now()]);
        $this->donor->assignRole('donor');

        $this->confirmedRecipient = User::factory()->create(['email_verified_at' => now()]);
        $this->confirmedRecipient->assignRole('recipient');

        $this->listing = FoodListing::create([
            'donor_id'      => $this->donor->id,
            'title'         => 'Dal Bhat',
            'quantity'      => '10 portions',
            'status'        => 'claimed',
            'claimed_by'    => $this->confirmedRecipient->id,
            'confirmed_at'  => now(),
            'expires_at'    => now()->addHours(3),
            'pickup_before' => now()->addHours(5),
            'latitude'      => 27.7172,
            'longitude'     => 85.3240,
            'address'       => 'Thamel, Kathmandu',
            'location'      => ['lat' => 27.7172, 'long' => 85.3240],
        ]);

        $this->confirmedClaim = ListingClaim::create([
            'food_listing_id' => $this->listing->id,
            'recipient_id'    => $this->confirmedRecipient->id,
            'status'          => 'confirmed',
        ]);
    }

    public function test_donor_can_cancel_claimed_listing(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $response = $this->deleteJson("/api/donor/listings/{$this->listing->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('message', 'Listing cancelled successfully');

        $this->assertDatabaseHas('food_listings', [
            'id'     => $this->listing->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancel_claimed_rejects_all_claims(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $this->deleteJson("/api/donor/listings/{$this->listing->id}");

        $this->assertDatabaseHas('listing_claims', [
            'id'     => $this->confirmedClaim->id,
            'status' => 'rejected',
        ]);
    }

    public function test_cancel_claimed_notifies_confirmed_recipient(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $this->deleteJson("/api/donor/listings/{$this->listing->id}");

        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return $job->userId === $this->confirmedRecipient->id
                && $job->type === NotificationTypeEnum::LISTING_CANCELLED->value;
        });
    }

    public function test_cancel_active_listing_still_works_without_notification(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $this->listing->update(['status' => 'active', 'claimed_by' => null, 'confirmed_at' => null]);

        $response = $this->deleteJson("/api/donor/listings/{$this->listing->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('food_listings', [
            'id'     => $this->listing->id,
            'status' => 'cancelled',
        ]);

        Queue::assertNotPushed(SendNotificationJob::class);
    }

    public function test_cannot_cancel_completed_listing(): void
    {
        Passport::actingAs($this->donor);

        $this->listing->update(['status' => 'completed']);

        $response = $this->deleteJson("/api/donor/listings/{$this->listing->id}");

        $response->assertStatus(400);
    }
}
```

- [ ] **Step 2: Run test to verify claimed-cancel fails**

```bash
php artisan test --compact tests/Feature/FoodListings/DonorCancelClaimedListingTest.php
```

Expected: `test_donor_can_cancel_claimed_listing` FAIL (returns 400 — current code only accepts active).

- [ ] **Step 3: Extend cancelListing() in FoodListingService**

Replace the entire `cancelListing()` method:

```php
public function cancelListing(string $id, string $donorId): void
{
    $listing = $this->foodListingRepository->fetchBy('id', $id);

    if (! $listing) {
        throw new Exception('Listing not found', 404);
    }

    if ($listing->donor_id !== $donorId) {
        throw new Exception('Unauthorized', 403);
    }

    $allowedStatuses = [ListingStatusEnum::ACTIVE->value, ListingStatusEnum::CLAIMED->value];

    if (! in_array($listing->status, $allowedStatuses)) {
        throw new Exception('Can only cancel active or claimed listings', 400);
    }

    if ($listing->status === ListingStatusEnum::CLAIMED->value) {
        $previousRecipientId = $listing->claimed_by;

        $this->listingClaimRepository->rejectAllClaimsForListing($id);

        $this->foodListingRepository->update($id, [
            'status'       => ListingStatusEnum::CANCELLED->value,
            'cancelled_by' => $donorId,
        ]);

        if ($previousRecipientId) {
            SendNotificationJob::dispatch(
                $previousRecipientId,
                NotificationTypeEnum::LISTING_CANCELLED->value,
                'Listing cancelled',
                "'{$listing->title}' has been cancelled by the donor. Your pickup is no longer available.",
                [
                    'listing_id'    => $listing->id,
                    'listing_title' => $listing->title,
                ]
            );
        }

        return;
    }

    $this->foodListingRepository->update($id, [
        'status'       => ListingStatusEnum::CANCELLED->value,
        'cancelled_by' => $donorId,
    ]);
}
```

- [ ] **Step 4: Run all cancel tests and verify they pass**

```bash
php artisan test --compact tests/Feature/FoodListings/DonorCancelClaimedListingTest.php
```

Expected: 5 tests pass.

- [ ] **Step 5: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Feature/FoodListings/DonorCancelClaimedListingTest.php \
        app/Modules/FoodListings/Services/FoodListingService.php
git commit -m "feat: extend DELETE /donor/listings/{id} to cancel claimed listings with notification"
```

---

### Task 6: Documentation — API_DOC.md

No PHP files changed — no Pint needed.

**Files:**
- Modify: `API_DOC.md`

> **Note on CLAUDE.md:** No update needed. CLAUDE.md has no route list (defers to API_DOC.md) and no schema changes were made.

- [ ] **Step 1: Add three new routes to the Route List table**

In Section 2 (Route List), after the `DELETE /donor/listings/{id}` row, add:

```markdown
| GET | `/donor/stats` | Yes | donor |
| POST | `/donor/listings/{id}/relist` | Yes | donor |
| POST | `/donor/listings/{id}/reopen` | Yes | donor |
```

- [ ] **Step 2: Update the DELETE /donor/listings/{id} error cases**

Find this line in Section 4:
```
- `400` Can only cancel active listings
```
Replace with:
```
- `400` Can only cancel active or claimed listings
```

- [ ] **Step 3: Add GET /donor/stats endpoint documentation**

In Section 4 (Donor Endpoints), after `DELETE /donor/listings/{id}`, add:

```markdown
### GET `/donor/stats`
Get lifetime donation impact totals for the authenticated donor.

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Stats retrieved",
  "data": {
    "listings_completed": 38,
    "listings_active": 2,
    "listings_cancelled": 5,
    "listings_expired": 3,
    "unique_recipients_served": 12
  }
}
```
```

- [ ] **Step 4: Add POST /donor/listings/{id}/relist endpoint documentation**

After `GET /donor/stats`, add:

```markdown
### POST `/donor/listings/{id}/relist`
Get a pre-filled template from an existing listing. Does **not** create a new listing — use the response to pre-fill the create form, then submit normally via `POST /donor/listings`.

Valid for any listing status (active, expired, completed, cancelled).

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Listing template retrieved",
  "data": {
    "title": "Leftover Dal Bhat",
    "description": "Freshly cooked, enough for 15 people",
    "quantity": "15 portions",
    "tags": ["for_humans", "cooked"],
    "photos": ["https://res.cloudinary.com/.../abc.jpg"],
    "pickup_instructions": "Call before coming",
    "address": "Thamel, Kathmandu",
    "latitude": 27.7172,
    "longitude": 85.3240
  }
}
```

**Error cases:**
- `403` Not the owner of this listing
- `404` Listing not found
```

- [ ] **Step 5: Add POST /donor/listings/{id}/reopen endpoint documentation**

After the relist docs, add:

```markdown
### POST `/donor/listings/{id}/reopen`
Re-open a `claimed` listing when the confirmed recipient cannot make the pickup. Restores all claims (confirmed and previously auto-rejected) back to `pending` so the donor can re-pick. Sends a `listing_reopened` notification to the previously confirmed recipient.

**Response (200):** Full listing shape (same as `GET /donor/listings` item), `status: "active"`.

**Error cases:**
- `400` Listing is not in claimed status
- `403` Not the owner of this listing
- `404` Listing not found
```

- [ ] **Step 6: Add two new notification types to the notification types table**

In Section 7 (Enums), in the notification type table, add:

```markdown
| `listing_reopened` | confirmed recipient | donor calls `POST /donor/listings/{id}/reopen` on a claimed listing |
| `listing_cancelled` | confirmed recipient | donor calls `DELETE /donor/listings/{id}` on a claimed listing |
```

- [ ] **Step 7: Commit**

```bash
git add API_DOC.md
git commit -m "docs: update API_DOC.md with donor power features (stats, relist, reopen, cancel-claimed)"
```

---

### Final Verification

- [ ] **Run the full set of new tests together**

```bash
php artisan test --compact \
  tests/Feature/FoodListings/DonorStatsTest.php \
  tests/Feature/FoodListings/DonorRelistTest.php \
  tests/Feature/FoodListings/DonorReopenListingTest.php \
  tests/Feature/FoodListings/DonorCancelClaimedListingTest.php
```

Expected: 20 tests, all pass.

- [ ] **Ask the user to run the full test suite** to confirm no regressions:

```bash
php artisan test --compact
```
