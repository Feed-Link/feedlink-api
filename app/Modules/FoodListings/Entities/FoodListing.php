<?php

namespace App\Modules\FoodListings\Entities;

use App\Models\User;
use App\Modules\Core\Entities\BaseModel;
use App\Modules\Core\Entities\Tag;
use App\Modules\Core\Traits\Filterables;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\FoodListingFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FoodListing extends BaseModel
{
    use Filterables;
    use HasUuids;
    use HasFactory;

    protected $table = 'food_listings';

    protected $fillable = [
        'donor_id',
        'title',
        'description',
        'quantity',
        'photos',
        'expires_at',
        'pickup_before',
        'pickup_instructions',
        'status',
        'latitude',
        'longitude',
        'location',
        'address',
        'listing_claim_id',
        'cancelled_by',
        'claimed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'pickup_before' => 'datetime',
        'confirmed_at' => 'datetime',
        'photos' => 'array',
        'location' => Point::class,
    ];

    public const SEARCHABLE = ['title', 'description', 'address', 'status', 'donor_id'];

    public const STATUS = [
        'active',
        'cancelled',
        'pending',
        'approved',
        'rejected',
        'claimed',
        'completed',
        'expired',
    ];

    protected static function newFactory()
    {
        return FoodListingFactory::new();
    }

    protected function location(): Attribute
    {
        return Attribute::make(
            set: fn(mixed $value) => is_array($value)
                && isset($value['lat'], $value['long'])
                ? Point::make($value['long'], $value['lat'])
                : $value
        );
    }

    public function donor()
    {
        return $this->belongsTo(User::class, 'donor_id');
    }

    public function claimedRecipient()
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function cancelUser()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function claim()
    {
        return $this->belongsTo(ListingClaim::class, 'listing_claim_id');
    }

    public function claims()
    {
        return $this->hasMany(ListingClaim::class, 'food_listing_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            Tag::class,
            'food_listing_tags',
            'food_listing_id',
            'tag_id'
        );
    }
}
