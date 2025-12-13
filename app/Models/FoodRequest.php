<?php

namespace App\Models;

use App\Modules\Core\Entities\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodRequest extends BaseModel
{
    protected $fillable = [
        'foodlist_id',
        'user_id',
        'status',
    ];

    public const SEARCHABLE = [
        'foodlist_id',
        'user_id',
        'status',
    ];

    public function users(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey:'user_id',
            ownerKey:'id',
        );
    }

    public function food_list(): BelongsTo
    {
        return $this->belongsTo(
            related: FoodList::class,
            foreignKey:'foodlist_id',
            ownerKey:'id',
        );
    }
}
