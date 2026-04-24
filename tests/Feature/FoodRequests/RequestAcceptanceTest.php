<?php

namespace Tests\Feature\FoodRequests;

use App\Models\User;
use App\Modules\Core\Enums\NotificationTypeEnum;
use App\Modules\FoodListings\Entities\FoodRequest;
use App\Modules\FoodListings\Entities\RequestAcceptance;
use App\Modules\Notifications\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RequestAcceptanceTest extends TestCase
{
    private function makeRecipient(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('recipient');

        return $user;
    }

    private function makeDonor(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('donor');

        return $user;
    }

    private function makeRequest(User $recipient, array $overrides = []): FoodRequest
    {
        return FoodRequest::create(array_merge([
            'recipient_id' => $recipient->id,
            'title' => 'Need rice',
            'quantity_needed' => '5 kg',
            'needed_by' => now()->addDays(3),
            'status' => 'open',
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'address' => 'Thamel, Kathmandu',
            'location' => ['lat' => 27.7172, 'long' => 85.3240],
        ], $overrides));
    }

    // ─── Recipient CRUD ──────────────────────────────────────────────────────

    public function test_recipient_can_create_food_request(): void
    {
        $recipient = $this->makeRecipient();

        $response = $this->actingAs($recipient, 'api')->postJson('/api/recipient/requests', [
            'title' => 'Need dal',
            'quantity_needed' => '3 kg',
            'tags' => ['for_humans'],
            'needed_by' => now()->addDays(2)->toISOString(),
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'address' => 'Thamel',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Need dal')
            ->assertJsonPath('data.status', 'open');
    }

    public function test_recipient_can_list_own_requests(): void
    {
        $recipient = $this->makeRecipient();
        $this->makeRequest($recipient);
        $this->makeRequest($recipient, ['title' => 'Need vegetables']);

        $response = $this->actingAs($recipient, 'api')->getJson('/api/recipient/requests');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_recipient_can_cancel_open_request(): void
    {
        $recipient = $this->makeRecipient();
        $foodRequest = $this->makeRequest($recipient);

        $response = $this->actingAs($recipient, 'api')
            ->deleteJson("/api/recipient/requests/{$foodRequest->id}");

        $response->assertOk();
        $this->assertDatabaseHas('food_requests', ['id' => $foodRequest->id, 'status' => 'cancelled']);
    }

    public function test_recipient_cannot_cancel_fulfilled_request(): void
    {
        $recipient = $this->makeRecipient();
        $foodRequest = $this->makeRequest($recipient, ['status' => 'fulfilled']);

        $response = $this->actingAs($recipient, 'api')
            ->deleteJson("/api/recipient/requests/{$foodRequest->id}");

        $response->assertStatus(400);
    }

    // ─── Donor accepts ────────────────────────────────────────────────────────

    public function test_donor_can_accept_open_request(): void
    {
        Queue::fake();

        $recipient = $this->makeRecipient();
        $donor = $this->makeDonor();
        $foodRequest = $this->makeRequest($recipient);

        $response = $this->actingAs($donor, 'api')
            ->postJson("/api/donor/requests/{$foodRequest->id}/accept", [
                'note' => 'I can deliver tomorrow',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('request_acceptances', [
            'food_request_id' => $foodRequest->id,
            'donor_id' => $donor->id,
            'status' => 'pending',
        ]);
    }

    public function test_accepting_notifies_recipient(): void
    {
        Queue::fake();

        $recipient = $this->makeRecipient();
        $donor = $this->makeDonor();
        $foodRequest = $this->makeRequest($recipient);

        $this->actingAs($donor, 'api')
            ->postJson("/api/donor/requests/{$foodRequest->id}/accept");

        Queue::assertPushed(SendNotificationJob::class, fn ($job) => $job->userId === $recipient->id
            && $job->type === NotificationTypeEnum::REQUEST_ACCEPTED->value
        );
    }

    public function test_donor_cannot_accept_non_open_request(): void
    {
        Queue::fake();

        $recipient = $this->makeRecipient();
        $donor = $this->makeDonor();
        $foodRequest = $this->makeRequest($recipient, ['status' => 'accepted']);

        $response = $this->actingAs($donor, 'api')
            ->postJson("/api/donor/requests/{$foodRequest->id}/accept");

        $response->assertStatus(400);
    }

    public function test_donor_cannot_accept_same_request_twice(): void
    {
        Queue::fake();

        $recipient = $this->makeRecipient();
        $donor = $this->makeDonor();
        $foodRequest = $this->makeRequest($recipient);

        $this->actingAs($donor, 'api')->postJson("/api/donor/requests/{$foodRequest->id}/accept");
        $response = $this->actingAs($donor, 'api')->postJson("/api/donor/requests/{$foodRequest->id}/accept");

        $response->assertStatus(400);
    }

    // ─── Donor withdraws ─────────────────────────────────────────────────────

    public function test_donor_can_withdraw_pending_acceptance(): void
    {
        Queue::fake();

        $recipient = $this->makeRecipient();
        $donor = $this->makeDonor();
        $foodRequest = $this->makeRequest($recipient);

        RequestAcceptance::create([
            'food_request_id' => $foodRequest->id,
            'donor_id' => $donor->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($donor, 'api')
            ->deleteJson("/api/donor/requests/{$foodRequest->id}/accept");

        $response->assertOk();
        $this->assertDatabaseMissing('request_acceptances', [
            'food_request_id' => $foodRequest->id,
            'donor_id' => $donor->id,
        ]);
    }

    public function test_withdrawing_notifies_recipient(): void
    {
        Queue::fake();

        $recipient = $this->makeRecipient();
        $donor = $this->makeDonor();
        $foodRequest = $this->makeRequest($recipient);

        RequestAcceptance::create([
            'food_request_id' => $foodRequest->id,
            'donor_id' => $donor->id,
            'status' => 'pending',
        ]);

        $this->actingAs($donor, 'api')
            ->deleteJson("/api/donor/requests/{$foodRequest->id}/accept");

        Queue::assertPushed(SendNotificationJob::class, fn ($job) => $job->userId === $recipient->id
            && $job->type === NotificationTypeEnum::ACCEPTANCE_WITHDRAWN->value
        );
    }

    // ─── Recipient confirms / rejects ────────────────────────────────────────

    public function test_recipient_can_confirm_acceptance(): void
    {
        Queue::fake();

        $recipient = $this->makeRecipient();
        $donor = $this->makeDonor();
        $foodRequest = $this->makeRequest($recipient);

        $acceptance = RequestAcceptance::create([
            'food_request_id' => $foodRequest->id,
            'donor_id' => $donor->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($recipient, 'api')
            ->postJson("/api/recipient/requests/{$foodRequest->id}/acceptances/{$acceptance->id}/confirm");

        $response->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('food_requests', [
            'id' => $foodRequest->id,
            'status' => 'accepted',
            'accepted_by' => $donor->id,
        ]);
        $this->assertDatabaseHas('request_acceptances', [
            'id' => $acceptance->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_confirming_auto_rejects_other_pending_acceptances(): void
    {
        Queue::fake();

        $recipient = $this->makeRecipient();
        $donorA = $this->makeDonor();
        $donorB = $this->makeDonor();
        $foodRequest = $this->makeRequest($recipient);

        $acceptanceA = RequestAcceptance::create([
            'food_request_id' => $foodRequest->id,
            'donor_id' => $donorA->id,
            'status' => 'pending',
        ]);
        $acceptanceB = RequestAcceptance::create([
            'food_request_id' => $foodRequest->id,
            'donor_id' => $donorB->id,
            'status' => 'pending',
        ]);

        $this->actingAs($recipient, 'api')
            ->postJson("/api/recipient/requests/{$foodRequest->id}/acceptances/{$acceptanceA->id}/confirm");

        $this->assertDatabaseHas('request_acceptances', ['id' => $acceptanceA->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('request_acceptances', ['id' => $acceptanceB->id, 'status' => 'rejected']);
    }

    public function test_confirming_notifies_confirmed_donor(): void
    {
        Queue::fake();

        $recipient = $this->makeRecipient();
        $donor = $this->makeDonor();
        $foodRequest = $this->makeRequest($recipient);

        $acceptance = RequestAcceptance::create([
            'food_request_id' => $foodRequest->id,
            'donor_id' => $donor->id,
            'status' => 'pending',
        ]);

        $this->actingAs($recipient, 'api')
            ->postJson("/api/recipient/requests/{$foodRequest->id}/acceptances/{$acceptance->id}/confirm");

        Queue::assertPushed(SendNotificationJob::class, fn ($job) => $job->userId === $donor->id
            && $job->type === NotificationTypeEnum::ACCEPTANCE_CONFIRMED->value
        );
    }

    public function test_recipient_can_reject_acceptance(): void
    {
        Queue::fake();

        $recipient = $this->makeRecipient();
        $donor = $this->makeDonor();
        $foodRequest = $this->makeRequest($recipient);

        $acceptance = RequestAcceptance::create([
            'food_request_id' => $foodRequest->id,
            'donor_id' => $donor->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($recipient, 'api')
            ->postJson("/api/recipient/requests/{$foodRequest->id}/acceptances/{$acceptance->id}/reject");

        $response->assertOk();
        $this->assertDatabaseHas('request_acceptances', ['id' => $acceptance->id, 'status' => 'rejected']);
    }

    public function test_rejecting_notifies_donor(): void
    {
        Queue::fake();

        $recipient = $this->makeRecipient();
        $donor = $this->makeDonor();
        $foodRequest = $this->makeRequest($recipient);

        $acceptance = RequestAcceptance::create([
            'food_request_id' => $foodRequest->id,
            'donor_id' => $donor->id,
            'status' => 'pending',
        ]);

        $this->actingAs($recipient, 'api')
            ->postJson("/api/recipient/requests/{$foodRequest->id}/acceptances/{$acceptance->id}/reject");

        Queue::assertPushed(SendNotificationJob::class, fn ($job) => $job->userId === $donor->id
            && $job->type === NotificationTypeEnum::ACCEPTANCE_REJECTED->value
        );
    }

    // ─── Complete ─────────────────────────────────────────────────────────────

    public function test_recipient_can_mark_accepted_request_as_fulfilled(): void
    {
        Queue::fake();

        $recipient = $this->makeRecipient();
        $donor = $this->makeDonor();
        $foodRequest = $this->makeRequest($recipient, [
            'status' => 'accepted',
            'accepted_by' => $donor->id,
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($recipient, 'api')
            ->postJson("/api/recipient/requests/{$foodRequest->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status', 'fulfilled');

        $this->assertDatabaseHas('food_requests', ['id' => $foodRequest->id, 'status' => 'fulfilled']);
    }

    public function test_completing_notifies_donor(): void
    {
        Queue::fake();

        $recipient = $this->makeRecipient();
        $donor = $this->makeDonor();
        $foodRequest = $this->makeRequest($recipient, [
            'status' => 'accepted',
            'accepted_by' => $donor->id,
            'accepted_at' => now(),
        ]);

        $this->actingAs($recipient, 'api')
            ->postJson("/api/recipient/requests/{$foodRequest->id}/complete");

        Queue::assertPushed(SendNotificationJob::class, fn ($job) => $job->userId === $donor->id
            && $job->type === NotificationTypeEnum::REQUEST_FULFILLED->value
        );
    }

    public function test_recipient_cannot_complete_open_request(): void
    {
        Queue::fake();

        $recipient = $this->makeRecipient();
        $foodRequest = $this->makeRequest($recipient);

        $response = $this->actingAs($recipient, 'api')
            ->postJson("/api/recipient/requests/{$foodRequest->id}/complete");

        $response->assertStatus(400);
    }

    // ─── Auth guards ──────────────────────────────────────────────────────────

    public function test_donor_cannot_access_recipient_request_routes(): void
    {
        $donor = $this->makeDonor();

        $this->actingAs($donor, 'api')->getJson('/api/recipient/requests')->assertStatus(403);
    }

    public function test_recipient_cannot_access_donor_request_routes(): void
    {
        $recipient = $this->makeRecipient();

        $this->actingAs($recipient, 'api')->getJson('/api/donor/requests?lat=27&lng=85')->assertStatus(403);
    }
}
