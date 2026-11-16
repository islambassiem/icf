<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** The exam type has no (valid) structure in exam_parts / exam_part_sections. */
class ExamNotConfiguredException extends RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct("This exam is not configured: {$reason}");
    }
}
