# Pickup Completion Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add claim status notifications to recipients (confirmed/rejected), a pickup completion endpoint for recipients, and a pickup_completed notification to donors — replacing the one-off `SendClaimNotificationJob` with a generic `SendNotificationJob`.

**Architecture:** A generic `SendNotificationJob(userId, type, title, body, data)` replaces `SendClaimNotificationJob`. Notification dispatches are added to existing `FoodListingService::confirmClaim()` and `rejectClaim()`. A new `CompleteListingService` handles the `POST /recipient/listings/{id}/complete` endpoint, which sets listing status to `completed` and fires `pickup_completed` to the donor.

**Tech Stack:** Laravel 11, Laravel Passport, PHPUnit, Laravel Queue (database driver), `kreait/firebase-php` (already installed)

---

## File Map

**Create:**
- `app/Modules/Notifications/Jobs/SendNotificationJob.php` — generic job, replaces `SendClaimNotificationJob`
- `app/Modules/FoodListings/Services/CompleteListingService.php` — handles pickup completion
- `tests/Feature/FoodListings/CompleteListingTest.php` — tests for complete endpoint
- `tests/Feature/Notifications/ClaimStatusNotificationsTest.php` — tests for claim_confirmed / claim_rejected / pickup_completed

**Modify:**
- `app/Modules/Core/Enums/NotificationTypeEnum.php` — add `CLAIM_CONFIRMED`, `CLAIM_REJECTED`, `PICKUP_COMPLETED`
- `app/Modules/FoodListings/Services/ListingClaimService.php` — replace `SendClaimNotificationJob` with `SendNotificationJob`
- `app/Modules/FoodListings/Services/FoodListingService.php` — dispatch `claim_confirmed`/`claim_rejected` in `confirmClaim()` and `rejectClaim()`; add `SendNotificationJob` and `NotificationTypeEnum` imports
- `app/Modules/FoodListings/Controllers/RecipientFoodListingController.php` — add `complete()` method, inject `CompleteListingService`
- `app/Modules/FoodListings/Resources/FoodListingResource.php` — add `contact` to donor shape
- `routes/api.php` — register complete route
- `API_DOC.md` — document new endpoint and resource change

**Delete:**
- `app/Modules/Notifications/Jobs/SendClaimNotificationJob.php` — replaced by `SendNotificationJob`

---

## Task 1: Add new NotificationTypeEnum cases

**Files:**
- Modify: `app/Modules/Core/Enums/NotificationTypeEnum.php`

- [ ] **Step 1: Update the enum**

Replace the entire file content:

```php
<?php

namespace App\Modules\Core\Enums;

enum NotificationTypeEnum: string
{
    case CLAIM_RECEIVED  = 'claim_received';
    case CLAIM_CONFIRMED = 'claim_confirmed';
    case CLAIM_REJECTED  = 'claim_rejected';
    case PICKUP_COMPLETED = 'pickup_completed';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint app/Modules/Core/Enums/NotificationTypeEnum.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add app/Modules/Core/Enums/NotificationTypeEnum.php
git commit -m "feat: add claim_confirmed, claim_rejected, pickup_completed notification types"
```

---

## Task 2: Create generic SendNotificationJob

**Files:**
- Create: `app/Modules/Notifications/Jobs/SendNotificationJob.php`
- Delete: `app/Modules/Notifications/Jobs/SendClaimNotificationJob.php`

- [ ] **Step 1: Create `SendNotificationJob.php`**

```php
<?php

namespace App\Modules\Notifications\Jobs;

use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Services\PushNotificationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $userId,
        public readonly string $type,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = []
    ) {}

    public function handle(NotificationService $notificationService, PushNotificationService $pushService): void
    {
        $notificationService->create([
            'user_id' => $this->userId,
            'type'    => $this->type,
            'title'   => $this->title,
            'body'    => $this->body,
            'data'    => $this->data,
        ]);

        $user = User::find($this->userId);

        if (! $user?->fcm_token) {
            return;
        }

        try {
            $pushService->send($user->fcm_token, $this->title, $this->body, $this->data);
        } catch (Exception $e) {
            Log::error('FCM push failed', [
                'user_id' => $this->userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint app/Modules/Notifications/Jobs/SendNotificationJob.php --format agent
```

- [ ] **Step 3: Delete the old job**

```bash
rm app/Modules/Notifications/Jobs/SendClaimNotificationJob.php
```

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Notifications/Jobs/SendNotificationJob.php
git add app/Modules/Notifications/Jobs/SendClaimNotificationJob.php
git commit -m "feat: add generic SendNotificationJob, remove SendClaimNotificationJob"
```

---

## Task 3: Migrate ListingClaimService to SendNotificationJob

**Files:**
- Modify: `app/Modules/FoodListings/Services/ListingClaimService.php`

- [ ] **Step 1: Update the `claim()` method and imports**

Replace the file with:

```php
<?php

namespace App\Modules\FoodListings\Services;

use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\FoodListings\Entities\ListingClaim;
use App\Modules\FoodListings\Repositories\ListingClaimRepository;
use App\Modules\Notifications\Jobs\SendNotificationJob;
use Exception;

class ListingClaimService
{
    public function __construct(
        protected ListingClaimRepository $listingClaimRepository
    ) {}

    public function claim(string $listingId, string $recipientId, string $note): object
    {
        if ($this->listingClaimRepository->hasPendingClaim($listingId, $recipientId)) {
            throw new Exception('You already have a pending claim on this listing', 400);
        }

        $claim = $this->listingClaimRepository->store([
            'food_listing_id' => $listingId,
            'recipient_id'    => $recipientId,
            'status'          => 'pending',
            'note'            => $note,
        ]);

        $claim->load(['listing.donor', 'recipient']);
        $listing = $claim->listing;

        SendNotificationJob::dispatch(
            $listing->donor_id,
            NotificationTypeEnum::CLAIM_RECEIVED->value,
            'New claim on your listing',
            "{$claim->recipient->name} wants to claim {$listing->title}",
            [
                'listing_id'    => $listing->id,
                'claim_id'      => $claim->id,
                'listing_title' => $listing->title,
            ]
        );

        return $claim;
    }

    public function cancelClaim(string $claimId, string $recipientId): void
    {
        $claim = $this->listingClaimRepository->fetchBy('id', $claimId);

        if (! $claim) {
            throw new Exception('Claim not found', 404);
        }

        if ($claim->recipient_id !== $recipientId) {
            throw new Exception('Unauthorized', 403);
        }

        if ($claim->status !== 'pending') {
            throw new Exception('Cannot cancel a non-pending claim', 400);
        }

        $this->listingClaimRepository->delete($claimId);
    }

    public function cancelClaimByListing(string $foodListingId, string $recipientId): void
    {
        $claim = $this->listingClaimRepository->fetchByClaim($foodListingId, $recipientId);

        if (! $claim) {
            throw new Exception('Claim not found', 404);
        }

        if ($claim->status !== 'pending') {
            throw new Exception('Cannot cancel a non-pending claim', 400);
        }

        $this->listingClaimRepository->delete($claim->id);
    }

    public function fetchMyClaims(string $recipientId, array $params = []): object
    {
        $filters          = $params['filter'] ?? [];
        $filters[]        = ['filter_by' => 'recipient_id', 'value' => $recipientId];
        $params['filter'] = $filters;

        $rows = $this->listingClaimRepository->model::query()
            ->where('recipient_id', $recipientId);

        return $this->listingClaimRepository->getFiltered($rows, $params, ['listing.donor']);
    }

    public function getStatuses(): array
    {
        return array_slice(ListingClaim::STATUS, 5, 5);
    }

    public function formatClaimResponse(object $claim): array
    {
        return [
            'id'              => $claim->id,
            'food_listing_id' => $claim->food_listing_id,
            'note'            => $claim->note,
            'listing'         => $claim->relationLoaded('listing') && $claim->listing ? [
                'id'    => $claim->listing->id,
                'title' => $claim->listing->title,
            ] : null,
            'claimed_by'      => $claim->relationLoaded('claimUser') && $claim->claimUser ? [
                'id'   => $claim->claimUser->id,
                'name' => $claim->claimUser->name,
            ] : null,
            'status'     => $claim->status,
            'created_at' => $claim->created_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint app/Modules/FoodListings/Services/ListingClaimService.php --format agent
```

- [ ] **Step 3: Run existing recipient claim tests to verify nothing regressed**

```bash
php artisan test --compact tests/Feature/RecipientFoodListingTest.php
```

Expected: all existing tests pass.

- [ ] **Step 4: Commit**

```bash
git add app/Modules/FoodListings/Services/ListingClaimService.php
git commit -m "refactor: migrate ListingClaimService to use SendNotificationJob"
```

---

## Task 4: Add claim_confirmed / claim_rejected notifications to FoodListingService

**Files:**
- Modify: `app/Modules/FoodListings/Services/FoodListingService.php`
- Create: `tests/Feature/Notifications/ClaimStatusNotificationsTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Notifications/ClaimStatusNotificationsTest.php`:

```php
<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\FoodListings\Entities\ListingClaim;
use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\Notifications\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ClaimStatusNotificationsTest extends TestCase
{
    protected User $donor;
    protected User $recipient;
    protected FoodListing $listing;
    protected ListingClaim $claim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->donor = User::factory()->create(['email_verified_at' => now()]);
        $this->donor->assignRole('donor');

        $this->recipient = User::factory()->create(['email_verified_at' => now()]);
        $this->recipient->assignRole('recipient');

        $this->listing = FoodListing::create([
            'donor_id'    => $this->donor->id,
            'title'       => 'Dal Bhat',
            'quantity'    => '10 portions',
            'status'      => 'active',
            'expires_at'  => now()->addHours(3),
            'pickup_before' => now()->addHours(5),
            'latitude'    => 27.7172,
            'longitude'   => 85.3240,
            'address'     => 'Thamel, Kathmandu',
            'location'    => ['lat' => 27.7172, 'long' => 85.3240],
        ]);

        $this->claim = ListingClaim::create([
            'food_listing_id' => $this->listing->id,
            'recipient_id'    => $this->recipient->id,
            'status'          => 'pending',
        ]);
    }

    public function test_confirming_claim_dispatches_claim_confirmed_to_recipient(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $this->postJson("/api/donor/listings/{$this->listing->id}/claims/{$this->claim->id}/confirm");

        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return $job->userId === $this->recipient->id
                && $job->type === NotificationTypeEnum::CLAIM_CONFIRMED->value;
        });
    }

    public function test_confirming_claim_dispatches_claim_rejected_to_other_pending_claimants(): void
    {
        Queue::fake();

        $otherRecipient = User::factory()->create(['email_verified_at' => now()]);
        $otherRecipient->assignRole('recipient');

        $otherClaim = ListingClaim::create([
            'food_listing_id' => $this->listing->id,
            'recipient_id'    => $otherRecipient->id,
            'status'          => 'pending',
        ]);

        Passport::actingAs($this->donor);

        $this->postJson("/api/donor/listings/{$this->listing->id}/claims/{$this->claim->id}/confirm");

        Queue::assertPushed(SendNotificationJob::class, function ($job) use ($otherRecipient) {
            return $job->userId === $otherRecipient->id
                && $job->type === NotificationTypeEnum::CLAIM_REJECTED->value;
        });
    }

    public function test_rejecting_claim_dispatches_claim_rejected_to_recipient(): void
    {
        Queue::fake();
        Passport::actingAs($this->donor);

        $this->postJson("/api/donor/listings/{$this->listing->id}/claims/{$this->claim->id}/reject");

        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return $job->userId === $this->recipient->id
                && $job->type === NotificationTypeEnum::CLAIM_REJECTED->value;
        });
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test --compact tests/Feature/Notifications/ClaimStatusNotificationsTest.php
```

Expected: 3 failures — `SendNotificationJob` not dispatched.

- [ ] **Step 3: Add `SendNotificationJob` and `NotificationTypeEnum` imports to FoodListingService**

At the top of `app/Modules/FoodListings/Services/FoodListingService.php`, add after existing imports:

```php
use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\Notifications\Jobs\SendNotificationJob;
```

- [ ] **Step 4: Update `confirmClaim()` in FoodListingService**

Replace the `confirmClaim` method (currently lines 119–156) with:

```php
public function confirmClaim(string $listingId, string $claimId, string $donorId): object
{
    $listing = $this->foodListingRepository->fetchBy('id', $listingId);

    if (! $listing) {
        throw new Exception('Listing not found', 404);
    }

    if ($listing->donor_id !== $donorId) {
        throw new Exception('Unauthorized', 403);
    }

    $claim = $listing->claims()->where('id', $claimId)->first();

    if (! $claim) {
        throw new Exception('Claim not found', 404);
    }

    if ($claim->status !== ClaimStatusEnum::PENDING->value) {
        throw new Exception('Claim is not pending', 400);
    }

    $otherPendingClaims = $listing->claims()
        ->where('id', '!=', $claimId)
        ->where('status', ClaimStatusEnum::PENDING->value)
        ->get();

    $claim->update(['status' => ClaimStatusEnum::CONFIRMED->value]);

    $listing->update([
        'listing_claim_id' => $claimId,
        'status'           => ListingStatusEnum::CLAIMED->value,
        'claimed_by'       => $claim->recipient_id,
        'confirmed_at'     => now(),
    ]);

    $listing->claims()
        ->where('id', '!=', $claimId)
        ->where('status', ClaimStatusEnum::PENDING->value)
        ->update(['status' => ClaimStatusEnum::REJECTED->value]);

    SendNotificationJob::dispatch(
        $claim->recipient_id,
        NotificationTypeEnum::CLAIM_CONFIRMED->value,
        'Your claim was accepted!',
        "Get ready to pick up {$listing->title}",
        [
            'listing_id'    => $listing->id,
            'claim_id'      => $claim->id,
            'listing_title' => $listing->title,
        ]
    );

    foreach ($otherPendingClaims as $rejectedClaim) {
        SendNotificationJob::dispatch(
            $rejectedClaim->recipient_id,
            NotificationTypeEnum::CLAIM_REJECTED->value,
            'Claim not accepted',
            "Your claim on {$listing->title} was not accepted",
            [
                'listing_id'    => $listing->id,
                'claim_id'      => $rejectedClaim->id,
                'listing_title' => $listing->title,
            ]
        );
    }

    return $listing->fresh(['donor', 'claimedRecipient', 'tags']);
}
```

- [ ] **Step 5: Update `rejectClaim()` in FoodListingService**

Replace the `rejectClaim` method (currently lines 158–181) with:

```php
public function rejectClaim(string $listingId, string $claimId, string $donorId): void
{
    $listing = $this->foodListingRepository->fetchBy('id', $listingId);

    if (! $listing) {
        throw new Exception('Listing not found', 404);
    }

    if ($listing->donor_id !== $donorId) {
        throw new Exception('Unauthorized', 403);
    }

    $claim = $listing->claims()->where('id', $claimId)->first();

    if (! $claim) {
        throw new Exception('Claim not found', 404);
    }

    if ($claim->status !== ClaimStatusEnum::PENDING->value) {
        throw new Exception('Claim cannot be rejected', 400);
    }

    $claim->update(['status' => ClaimStatusEnum::REJECTED->value]);

    SendNotificationJob::dispatch(
        $claim->recipient_id,
        NotificationTypeEnum::CLAIM_REJECTED->value,
        'Claim not accepted',
        "Your claim on {$listing->title} was not accepted",
        [
            'listing_id'    => $listing->id,
            'claim_id'      => $claim->id,
            'listing_title' => $listing->title,
        ]
    );
}
```

- [ ] **Step 6: Run pint**

```bash
vendor/bin/pint app/Modules/FoodListings/Services/FoodListingService.php --format agent
```

- [ ] **Step 7: Run the new tests**

```bash
php artisan test --compact tests/Feature/Notifications/ClaimStatusNotificationsTest.php
```

Expected: 3 tests pass.

- [ ] **Step 8: Run donor listing tests to verify no regressions**

```bash
php artisan test --compact tests/Feature/DonorFoodListingTest.php
```

Expected: all pass.

- [ ] **Step 9: Commit**

```bash
git add app/Modules/FoodListings/Services/FoodListingService.php
git add tests/Feature/Notifications/ClaimStatusNotificationsTest.php
git commit -m "feat: dispatch claim_confirmed and claim_rejected notifications on donor confirm/reject"
```

---

## Task 5: Add donor contact to FoodListingResource

**Files:**
- Modify: `app/Modules/FoodListings/Resources/FoodListingResource.php`

- [ ] **Step 1: Update donor shape in `toArray()`**

In `FoodListingResource.php`, replace the `donor` key block:

```php
'donor' => $this->when(
    $this->relationLoaded('donor') && $this->donor,
    fn () => [
        'id'          => $this->donor->id,
        'name'        => $this->donor->name,
        'is_verified' => (bool) ($this->donor->is_verified ?? false),
        'contact'     => $this->donor->contact,
    ]
),
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint app/Modules/FoodListings/Resources/FoodListingResource.php --format agent
```

- [ ] **Step 3: Run existing listing tests**

```bash
php artisan test --compact tests/Feature/DonorFoodListingTest.php tests/Feature/RecipientFoodListingTest.php
```

Expected: all pass.

- [ ] **Step 4: Commit**

```bash
git add app/Modules/FoodListings/Resources/FoodListingResource.php
git commit -m "feat: include donor contact in listing resource response"
```

---

## Task 6: Create CompleteListingService

**Files:**
- Create: `app/Modules/FoodListings/Services/CompleteListingService.php`

- [ ] **Step 1: Create the service**

```php
<?php

namespace App\Modules\FoodListings\Services;

use App\Modules\Core\Enums\ClaimStatusEnum;
use App\Modules\Core\Enums\ListingStatusEnum;
use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\FoodListings\Repositories\FoodListingRepository;
use App\Modules\Notifications\Jobs\SendNotificationJob;
use Exception;

class CompleteListingService
{
    public function __construct(
        protected FoodListingRepository $foodListingRepository
    ) {}

    public function complete(string $listingId, string $recipientId): object
    {
        $listing = $this->foodListingRepository->fetchBy('id', $listingId, ['donor', 'claimedRecipient', 'tags']);

        if (! $listing) {
            throw new Exception('Listing not found', 404);
        }

        if ($listing->status !== ListingStatusEnum::CLAIMED->value) {
            throw new Exception('Listing is not in claimed status', 400);
        }

        $hasConfirmedClaim = $listing->claims()
            ->where('recipient_id', $recipientId)
            ->where('status', ClaimStatusEnum::CONFIRMED->value)
            ->exists();

        if (! $hasConfirmedClaim) {
            throw new Exception("You don't have a confirmed claim on this listing", 403);
        }

        $listing->update(['status' => ListingStatusEnum::COMPLETED->value]);

        $recipientName = $listing->claimedRecipient?->name ?? 'A recipient';

        SendNotificationJob::dispatch(
            $listing->donor_id,
            NotificationTypeEnum::PICKUP_COMPLETED->value,
            'Food collected!',
            "{$recipientName} picked up {$listing->title}",
            [
                'listing_id'    => $listing->id,
                'listing_title' => $listing->title,
            ]
        );

        return $listing->fresh(['donor', 'tags']);
    }
}
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint app/Modules/FoodListings/Services/CompleteListingService.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add app/Modules/FoodListings/Services/CompleteListingService.php
git commit -m "feat: add CompleteListingService for pickup completion"
```

---

## Task 7: Wire complete endpoint — controller, route, tests, API doc

**Files:**
- Modify: `app/Modules/FoodListings/Controllers/RecipientFoodListingController.php`
- Modify: `routes/api.php`
- Modify: `API_DOC.md`
- Create: `tests/Feature/FoodListings/CompleteListingTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/FoodListings/CompleteListingTest.php`:

```php
<?php

namespace Tests\Feature\FoodListings;

use App\Models\User;
use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\FoodListings\Entities\ListingClaim;
use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\Notifications\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CompleteListingTest extends TestCase
{
    protected User $donor;
    protected User $recipient;
    protected FoodListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->donor = User::factory()->create(['email_verified_at' => now()]);
        $this->donor->assignRole('donor');

        $this->recipient = User::factory()->create(['email_verified_at' => now()]);
        $this->recipient->assignRole('recipient');

        $this->listing = FoodListing::create([
            'donor_id'      => $this->donor->id,
            'title'         => 'Dal Bhat',
            'quantity'      => '10 portions',
            'status'        => 'claimed',
            'claimed_by'    => $this->recipient->id,
            'confirmed_at'  => now(),
            'expires_at'    => now()->addHours(3),
            'pickup_before' => now()->addHours(5),
            'latitude'      => 27.7172,
            'longitude'     => 85.3240,
            'address'       => 'Thamel, Kathmandu',
            'location'      => ['lat' => 27.7172, 'long' => 85.3240],
        ]);

        ListingClaim::create([
            'food_listing_id' => $this->listing->id,
            'recipient_id'    => $this->recipient->id,
            'status'          => 'confirmed',
        ]);
    }

    public function test_recipient_can_mark_pickup_as_complete(): void
    {
        Queue::fake();
        Passport::actingAs($this->recipient);

        $response = $this->postJson("/api/recipient/listings/{$this->listing->id}/complete");

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('food_listings', [
            'id'     => $this->listing->id,
            'status' => 'completed',
        ]);

        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return $job->userId === $this->donor->id
                && $job->type === NotificationTypeEnum::PICKUP_COMPLETED->value;
        });
    }

    public function test_recipient_without_confirmed_claim_cannot_complete(): void
    {
        $otherRecipient = User::factory()->create(['email_verified_at' => now()]);
        $otherRecipient->assignRole('recipient');
        Passport::actingAs($otherRecipient);

        $response = $this->postJson("/api/recipient/listings/{$this->listing->id}/complete");

        $response->assertStatus(403);
    }

    public function test_cannot_complete_listing_that_is_not_claimed(): void
    {
        Passport::actingAs($this->recipient);

        $this->listing->update(['status' => 'active']);

        $response = $this->postJson("/api/recipient/listings/{$this->listing->id}/complete");

        $response->assertStatus(400);
    }

    public function test_returns_404_for_nonexistent_listing(): void
    {
        Passport::actingAs($this->recipient);

        $response = $this->postJson('/api/recipient/listings/nonexistent-uuid/complete');

        $response->assertStatus(404);
    }

    public function test_donor_cannot_access_complete_endpoint(): void
    {
        Passport::actingAs($this->donor);

        $response = $this->postJson("/api/recipient/listings/{$this->listing->id}/complete");

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test --compact tests/Feature/FoodListings/CompleteListingTest.php
```

Expected: failures — route not found.

- [ ] **Step 3: Add `complete()` to RecipientFoodListingController**

Update `app/Modules/FoodListings/Controllers/RecipientFoodListingController.php`:

Add `CompleteListingService` import at top with existing imports:
```php
use App\Modules\FoodListings\Services\CompleteListingService;
use App\Modules\FoodListings\Resources\FoodListingResource;
```

Update constructor:
```php
public function __construct(
    protected FoodListingService $foodListingService,
    protected ListingClaimService $listingClaimService,
    protected CompleteListingService $completeListingService
) {}
```

Add method at end of class (before the closing `}`):
```php
public function complete(string $listingId): JsonResponse
{
    try {
        $listing = $this->completeListingService->complete($listingId, Auth::id());

        return $this->success(
            'Pickup marked as complete',
            Response::HTTP_OK,
            new FoodListingResource($listing)
        );
    } catch (Exception $exception) {
        return $this->handleException($exception);
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/api.php`, inside the `recipient` prefix group (alongside the existing claim routes), add:

```php
Route::post('listings/{listingId}/complete', [RecipientFoodListingController::class, 'complete']);
```

- [ ] **Step 5: Run pint**

```bash
vendor/bin/pint app/Modules/FoodListings/Controllers/RecipientFoodListingController.php routes/api.php --format agent
```

- [ ] **Step 6: Run the tests**

```bash
php artisan test --compact tests/Feature/FoodListings/CompleteListingTest.php
```

Expected: 5 tests pass.

- [ ] **Step 7: Update API_DOC.md**

In `API_DOC.md`, add the following under **Section 5 (Recipient Endpoints)**, after `DELETE /recipient/listings/{listingId}/claim`:

```markdown
### POST `/recipient/listings/{listingId}/complete`
Mark a claimed listing as collected. Only callable by the recipient who has a confirmed claim on it.

**Response (200):**
```json
{
  "status_code": 200,
  "message": "Pickup marked as complete",
  "data": { ...full listing shape with status: "completed"... }
}
```

**Error cases:**
- `404` Listing not found
- `403` You don't have a confirmed claim on this listing
- `400` Listing is not in claimed status
```

Also update the donor shape in the **Frontend Integration Notes** (Section 9) to mention `contact` is now included:

```markdown
- Listing resource `donor` shape now includes `contact` (phone number) — use this on the confirmed-claim detail screen so the recipient can call before arriving.
```

- [ ] **Step 8: Run full test suite**

```bash
php artisan test --compact
```

Expected: all tests pass.

- [ ] **Step 9: Final commit**

```bash
git add app/Modules/FoodListings/Controllers/RecipientFoodListingController.php
git add routes/api.php
git add API_DOC.md
git add tests/Feature/FoodListings/CompleteListingTest.php
git commit -m "feat: add POST /recipient/listings/{id}/complete endpoint with pickup_completed notification"
```
