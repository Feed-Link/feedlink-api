<?php

namespace App\Modules\Core\Enums;

enum NotificationTypeEnum: string
{
    case CLAIM_RECEIVED = 'claim_received';
    case CLAIM_CONFIRMED = 'claim_confirmed';
    case CLAIM_REJECTED = 'claim_rejected';
    case PICKUP_COMPLETED = 'pickup_completed';
    case LISTING_EXPIRED = 'listing_expired';
    case LISTING_EXPIRED_UNCOLLECTED = 'listing_expired_uncollected';
    case REQUEST_ACCEPTED = 'request_accepted';
    case ACCEPTANCE_CONFIRMED = 'acceptance_confirmed';
    case ACCEPTANCE_REJECTED = 'acceptance_rejected';
    case ACCEPTANCE_WITHDRAWN = 'acceptance_withdrawn';
    case REQUEST_FULFILLED = 'request_fulfilled';
    case LISTING_REOPENED = 'listing_reopened';
    case LISTING_CANCELLED = 'listing_cancelled';

    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
