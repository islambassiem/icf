<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatusEnum: string
{
    case PENDING = 'pending';

    case SUCCEEDED = 'succeeded';

    case FAILD = 'failed';

    case REFUNDED = 'refunded';

    case PAID = 'paid';
}
