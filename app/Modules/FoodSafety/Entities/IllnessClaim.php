<?php

namespace App\Modules\FoodSafety\Entities;

use App\Models\User;
use App\Modules\Core\Entities\BaseModel;
use App\Modules\FoodListings\Entities\FoodListing;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IllnessClaim extends BaseModel
{
    protected $table = 'illness_claims';

    protected $fillable = [
        'reporter_id',
        'donor_id',
        'food_listing_id',
        'description',
        'reported_at',
        'status',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
    ];

    public const STATUS = ['pending', 'reviewed', 'archived'];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'donor_id');
    }

    public function foodListing(): BelongsTo
    {
        return $this->belongsTo(FoodListing::class, 'food_listing_id');
    }
}
