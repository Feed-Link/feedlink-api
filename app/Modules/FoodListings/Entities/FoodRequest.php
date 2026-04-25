<?php

namespace App\Modules\FoodListings\Entities;

use App\Models\User;
use App\Modules\Core\Entities\BaseModel;
use App\Modules\Core\Entities\Tag;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FoodRequest extends BaseModel
{
    use HasFactory;

    protected $table = 'food_requests';

    protected $fillable = [
        'recipient_id',
        'title',
        'description',
        'quantity_needed',
        'needed_by',
        'status',
        'latitude',
        'longitude',
        'location',
        'address',
        'accepted_by',
        'accepted_at',
        'expires_at',
    ];

    protected $casts = [
        'needed_by' => 'datetime',
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
        'location' => Point::class,
    ];

    public const SEARCHABLE = ['title', 'description', 'address'];

    public const STATUS = [
        'open',
        'accepted',
        'fulfilled',
        'expired',
        'cancelled',
    ];

    protected function location(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value) => is_array($value)
                && isset($value['lat'], $value['long'])
                ? Point::makeGeodetic($value['lat'], $value['long'])
                : $value
        );
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function acceptances()
    {
        return $this->hasMany(RequestAcceptance::class, 'food_request_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            Tag::class,
            'food_request_tags',
            'food_request_id',
            'tag_id'
        );
    }
}
