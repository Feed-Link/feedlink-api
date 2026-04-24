<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Notifications\Entities\Notification;
use App\Modules\Notifications\Jobs\SendNotificationJob;
use App\Modules\Notifications\Services\PushNotificationService;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    public function test_user_can_list_notifications(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->create(['user_id' => $user->id]);
        Passport::actingAs($user, ['*']);

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'items' => [['id', 'type', 'title', 'body', 'data', 'read_at', 'created_at']],
                    'unread_count',
                    'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);
    }

    public function test_unread_count_is_correct(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(2)->create(['user_id' => $user->id, 'read_at' => null]);
        Notification::factory()->create(['user_id' => $user->id, 'read_at' => now()]);
        Passport::actingAs($user, ['*']);

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('data.unread_count', 2);
    }

    public function test_user_can_mark_single_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'read_at' => null,
        ]);
        Passport::actingAs($user, ['*']);

        $response = $this->putJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->create(['user_id' => $user->id, 'read_at' => null]);
        Passport::actingAs($user, ['*']);

        $response = $this->putJson('/api/notifications/read-all');

        $response->assertStatus(200);
        $this->assertEquals(
            0,
            Notification::where('user_id', $user->id)->whereNull('read_at')->count()
        );
    }

    public function test_user_cannot_see_other_users_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Notification::factory()->count(2)->create(['user_id' => $other->id]);
        Passport::actingAs($user, ['*']);

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('data.unread_count', 0)
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_send_notification_job_is_dispatched(): void
    {
        Queue::fake();

        SendNotificationJob::dispatch('some-user-id', 'claim_received', 'New Claim', 'You have a new claim.');

        Queue::assertPushed(SendNotificationJob::class, function (SendNotificationJob $job) {
            return $job->userId === 'some-user-id'
                && $job->type === 'claim_received';
        });
    }

    public function test_job_creates_in_app_notification_and_skips_push_without_token(): void
    {
        $pushMock = $this->mock(PushNotificationService::class);
        $pushMock->shouldNotReceive('send');

        $donor = User::factory()->create(['fcm_token' => null]);

        dispatch(new SendNotificationJob(
            userId: $donor->id,
            type: 'claim_received',
            title: 'New claim on your listing',
            body: 'Asha Shelter has claimed your listing.',
            data: ['listing_id' => 'listing-uuid', 'claim_id' => 'claim-uuid'],
        ));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $donor->id,
            'type' => 'claim_received',
            'title' => 'New claim on your listing',
        ]);
    }

    public function test_job_logs_error_when_push_fails_but_still_saves_notification(): void
    {
        $pushMock = $this->mock(PushNotificationService::class);
        $pushMock->shouldReceive('send')->once()->andThrow(new \Exception('FCM error'));

        $donor = User::factory()->create(['fcm_token' => 'some-token']);

        dispatch(new SendNotificationJob(
            userId: $donor->id,
            type: 'claim_received',
            title: 'New claim on your listing',
            body: 'NGO Helper has claimed your listing.',
            data: ['listing_id' => 'listing-uuid-2', 'claim_id' => 'claim-uuid-2'],
        ));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $donor->id,
            'type' => 'claim_received',
        ]);
    }
}
