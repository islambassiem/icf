<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InsufficientQuestionsException extends RuntimeException
{
    public function __construct(
        public readonly int $available,
        public readonly int $needed,
        public readonly string $questionType = '',
    ) {
        parent::__construct("Not enough {$questionType} questions for this exam: {$available} available, {$needed} needed.");
    }
}
