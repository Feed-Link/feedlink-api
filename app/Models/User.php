<?php

namespace App\Models;

use App\Modules\FoodSafety\Entities\DonorWarning;
use App\Modules\FoodSafety\Entities\IllnessClaim;
use App\Modules\FoodSafety\Entities\UserAcceptance;
use App\Modules\Notifications\Entities\Notification;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasOneTimePasswords;
    use HasRoles;
    use HasUuids;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'contact',
        'password',
        'latitude',
        'longitude',
        'location',
        'is_verified',
        'profile_photo',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verified_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Boot the model to automatically create Point geometry when lat/long are set
     */
    protected static function booted()
    {
        static::saving(function ($user) {
            if ($user->latitude && $user->longitude && ! $user->location) {
                $user->location = Point::makeGeodetic($user->latitude, $user->longitude);
            }
        });
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(UserAcceptance::class);
    }

    public function illnessClaims(): HasMany
    {
        return $this->hasMany(IllnessClaim::class, 'reporter_id');
    }

    public function claimsAgainstMe(): HasMany
    {
        return $this->hasMany(IllnessClaim::class, 'donor_id');
    }

    public function warning(): HasOne
    {
        return $this->hasOne(DonorWarning::class, 'donor_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }
}
