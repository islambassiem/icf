<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InsufficientQuestionsException extends RuntimeException
{
    public function __construct(public readonly int $available, public readonly int $needed)
    {
        parent::__construct("Not enough questions for this exam: {$available} available, {$needed} needed.");
    }
}
