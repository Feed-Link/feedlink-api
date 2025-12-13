<?php

namespace App\Models;

use App\Modules\Core\Entities\BaseModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Clickbar\Magellan\Data\Geometries\Point;

class FoodList extends BaseModel
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'type',
        'quantity',
        'weight',
        'pickup_within',
        'instructions',
        'location',
        'address',
    ];

    public const SEARCHABLE = [
        'user_id',
        'title',
        'type',
        'pickup_within',
        'location',
    ];

    protected $casts = [
        'location' => Point::class,
    ];

    protected function location(): Attribute
    {
        return Attribute::make(
            set: fn($value) => is_array($value)
                && isset($value['lat'], $value['long'])
                ? Point::make($value['long'], $value['lat'])
                : $value
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey: 'user_id',
            ownerKey: 'id',
        );
    }

    public function orders(): HasMany
    {
        return $this->hasMany(
            related: FoodRequest::class,
            foreignKey: 'foodlist_id',
            localKey: 'id'
        );
    }
}
