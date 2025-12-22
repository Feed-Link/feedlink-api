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
        'location'
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
            'location' => Point::class
        ];
    }

    protected function location(): Attribute
    {
        return Attribute::make(
            set: fn($value) => is_array($value)
                && isset($value['lat'], $value['long'])
                ? Point::make($value['long'], $value['lat'])
                : $value
        );
    }

    public function food_lists(): HasMany
    {
        return $this->hasMany(
            related: FoodList::class,
            foreignKey: 'user_id',
            localKey: 'id'
        );
    }

    public function food_requests(): HasMany
    {
        return $this->hasMany(
            related: FoodRequest::class,
            foreignKey: 'user_id',
            localKey: 'id'
        );
    }
}
