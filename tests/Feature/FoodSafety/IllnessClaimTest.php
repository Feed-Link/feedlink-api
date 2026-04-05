<?php

namespace Tests\Feature\FoodSafety;

use App\Models\User;
use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\FoodSafety\Entities\IllnessClaim;
use App\Modules\FoodSafety\Entities\DonorWarning;
use Tests\TestCase;

class IllnessClaimTest extends TestCase
{
    public function test_recipient_can_file_illness_claim()
    {
        $recipient = User::factory()->create();
        $donor = User::factory()->create();

        $claim = IllnessClaim::create([
            'reporter_id' => $recipient->id,
            'donor_id' => $donor->id,
            'description' => 'Got sick after eating',
            'reported_at' => now(),
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('illness_claims', [
            'reporter_id' => $recipient->id,
            'donor_id' => $donor->id,
            'status' => 'pending',
        ]);
    }

    public function test_claim_can_be_tied_to_listing()
    {
        $recipient = User::factory()->create();
        $donor = User::factory()->create();
        $listing = FoodListing::factory()->create(['donor_id' => $donor->id]);

        $claim = IllnessClaim::create([
            'reporter_id' => $recipient->id,
            'donor_id' => $donor->id,
            'food_listing_id' => $listing->id,
            'description' => 'Got sick',
            'reported_at' => now(),
        ]);

        $claim->refresh();
        $this->assertEquals($listing->id, $claim->food_listing_id);
    }

    public function test_donor_warning_tracks_claims()
    {
        $donor = User::factory()->create();
        $warning = DonorWarning::create([
            'donor_id' => $donor->id,
            'claim_count' => 0,
            'warning_active' => false,
        ]);

        $this->assertDatabaseHas('donor_warnings', [
            'donor_id' => $donor->id,
            'warning_active' => false,
        ]);
    }

    public function test_user_relationships_work()
    {
        $recipient = User::factory()->create();
        $donor = User::factory()->create();

        IllnessClaim::create([
            'reporter_id' => $recipient->id,
            'donor_id' => $donor->id,
            'description' => 'Got sick',
            'reported_at' => now(),
        ]);

        $recipient->refresh();
        $donor->refresh();

        $this->assertCount(1, $recipient->illnessClaims);
        $this->assertCount(1, $donor->claimsAgainstMe);
    }
}
