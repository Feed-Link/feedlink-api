<?php

namespace App\Modules\FoodListings\Entities;

use App\Models\User;
use App\Modules\Core\Entities\BaseModel;

class ListingClaim extends BaseModel
{
    protected $table = 'listing_claims';

    protected $fillable = [
        'food_listing_id',
        'recipient_id',
        'claim_user_id',
        'status',
        'note',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const SEARCHABLE = ['note'];

    public const STATUS = [
        'active',
        'cancelled',
        'pending',
        'approved',
        'rejected',
        'pending',
        'accepted',
        'claimed',
        'completed',
        'expired',
        'cancelled',
        'rejected',
    ];

    public function listing()
    {
        return $this->belongsTo(FoodListing::class, 'food_listing_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function claimUser()
    {
        return $this->belongsTo(User::class, 'claim_user_id');
    }
}
