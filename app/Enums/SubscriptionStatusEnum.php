<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatusEnum: string
{
    case PENDING = 'pending';

    case ACTIVE = 'active';

    case CANCELLED = 'cancelled';

    case EXPIRED = 'expired';
}
