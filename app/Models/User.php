<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;
use Spatie\Permission\Traits\HasRoles;
use App\Modules\FoodSafety\Entities\UserAcceptance;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory;
    use Notifiable;
    use HasUuids;
    use HasApiTokens;
    use HasRoles;
    use HasOneTimePasswords;

    protected $fillable = [
        'name',
        'email',
        'contact',
        'password',
        'latitude',
        'longitude',
        'location',
        'is_verified',
        'profile_photo'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verified_at',
        'created_at',
        'updated_at'
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
            if ($user->latitude && $user->longitude && !$user->location) {
                $user->location = Point::makeGeodetic($user->latitude, $user->longitude);
            }
        });
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(UserAcceptance::class);
    }

}
