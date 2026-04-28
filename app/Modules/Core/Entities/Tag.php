<?php

namespace App\Modules\Core\Entities;

use App\Modules\FoodListings\Entities\FoodListing;
use App\Modules\FoodListings\Entities\FoodRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $table = 'tags';

    protected $fillable = [
        'name',
        'slug',
        'category',
    ];

    public function foodListings(): BelongsToMany
    {
        return $this->belongsToMany(
            FoodListing::class,
            'food_listing_tags',
            'tag_id',
            'food_listing_id'
        );
    }

    public function foodRequests(): BelongsToMany
    {
        return $this->belongsToMany(
            FoodRequest::class,
            'food_request_tags',
            'tag_id',
            'food_request_id'
        );
    }

    public static function getAllTags()
    {
        return self::all()->groupBy('category');
    }
}
