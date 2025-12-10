<?php

namespace App\Modules\User\Repositories;

use App\Models\User;
use App\Modules\Core\Repositories\BaseRepository;

class UserRepository extends BaseRepository
{
    public function __construct(protected User $user)
    {
        $this->model = $user;
        parent::__construct();
    }
}
