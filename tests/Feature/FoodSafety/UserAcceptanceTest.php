<?php

namespace Tests\Feature\FoodSafety;

use App\Models\User;
use App\Modules\FoodSafety\Entities\UserAcceptance;
use Tests\TestCase;

class UserAcceptanceTest extends TestCase
{
    public function test_user_can_create_acceptance()
    {
        $user = User::factory()->create();

        $acceptance = UserAcceptance::create([
            'user_id' => $user->id,
            'terms_version' => '2026-04-05',
            'terms_type' => 'mutual',
            'ip_address' => '127.0.0.1',
            'accepted_at' => now(),
        ]);

        $this->assertDatabaseHas('user_acceptances', [
            'user_id' => $user->id,
            'terms_version' => '2026-04-05',
        ]);

        $this->assertTrue($user->acceptances()->where('terms_version', '2026-04-05')->exists());
    }

    public function test_user_acceptance_relation()
    {
        $user = User::factory()->create();
        UserAcceptance::create([
            'user_id' => $user->id,
            'terms_version' => '2026-04-05',
            'terms_type' => 'mutual',
            'accepted_at' => now(),
        ]);

        $user->refresh();
        $this->assertCount(1, $user->acceptances);
        $this->assertEquals('2026-04-05', $user->acceptances->first()->terms_version);
    }
}
