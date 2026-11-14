<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class NoActiveSubscriptionException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('An active subscription is required to start an exam.');
    }
}
