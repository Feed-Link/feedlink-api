<?php

namespace App\Modules\Notifications\Repositories;

use App\Modules\Core\Repositories\BaseRepository;
use App\Modules\Notifications\Entities\Notification;

class NotificationRepository extends BaseRepository
{
    public function __construct(protected Notification $notification)
    {
        $this->model = $notification;
        parent::__construct();
    }
}
