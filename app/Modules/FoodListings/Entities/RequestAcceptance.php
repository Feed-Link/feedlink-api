<?php

namespace App\Modules\FoodListings\Entities;

use App\Models\User;
use App\Modules\Core\Entities\BaseModel;

class RequestAcceptance extends BaseModel
{
    protected $table = 'request_acceptances';

    protected $fillable = [
        'food_request_id',
        'donor_id',
        'status',
        'note',
    ];

    public const SEARCHABLE = ['note'];

    public function foodRequest()
    {
        return $this->belongsTo(FoodRequest::class, 'food_request_id');
    }

    public function donor()
    {
        return $this->belongsTo(User::class, 'donor_id');
    }
}
