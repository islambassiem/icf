<?php

declare(strict_types=1);

namespace App\Enums;

enum ExamAttemptStatusEnum: string
{
    case IN_PROGRESS = 'in progress';

    case COMPLETED = 'completed';

    case TIMED_OUT = 'timed out';

    case ABANDONDED = 'abandoned';
}
