<?php

namespace App\Modules\FoodSafety\Entities;

use App\Models\User;
use App\Modules\Core\Entities\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAcceptance extends BaseModel
{
    public const SEARCHABLE = ['terms_version', 'terms_type'];

    public const CURRENT_TERMS_VERSION = '2026-04-05';

    protected $table = 'user_acceptances';

    protected $fillable = [
        'user_id',
        'terms_version',
        'terms_type',
        'ip_address',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
