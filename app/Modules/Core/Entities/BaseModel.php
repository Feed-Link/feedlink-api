<?php

namespace App\Modules\Core\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use HasUuids;

    public const SEARCHABLE = [];

    public static function getSearchable(): array
    {
        return static::SEARCHABLE;
    }
}
