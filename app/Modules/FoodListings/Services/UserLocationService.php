<?php

namespace App\Modules\FoodListings\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class UserLocationService
{
    /**
     * Update user's location.
     * Updates latitude and longitude columns.
     * If location PostGIS column exists, updates that too.
     */
    public function updateLocation(string $userId, array $data): void
    {
        $user = User::find($userId);

        if (! $user) {
            throw new Exception('User not found', 404);
        }

        $updates = [
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
        ];

        // Only update location PostGIS column if it exists
        $columns = DB::getSchemaBuilder()->getColumnListing('users');
        if (in_array('location', $columns)) {
            $user->update([
                'latitude' => $updates['latitude'],
                'longitude' => $updates['longitude'],
            ]);
            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'location' => DB::raw("ST_SetSRID(ST_MakePoint({$updates['longitude']}, {$updates['latitude']}), 4326)::geography"),
                ]);
        } else {
            $user->update($updates);
        }
    }
}
