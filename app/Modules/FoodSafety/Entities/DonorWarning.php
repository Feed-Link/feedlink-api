<?php

namespace App\Modules\FoodSafety\Entities;

use App\Models\User;
use App\Modules\Core\Entities\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonorWarning extends BaseModel
{
    protected $table = 'donor_warnings';

    protected $fillable = [
        'donor_id',
        'claim_count',
        'warning_active',
        'last_claim_at',
    ];

    protected $casts = [
        'last_claim_at' => 'datetime',
        'warning_active' => 'boolean',
    ];

    public function donor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'donor_id');
    }
}
