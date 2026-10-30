<?php

declare(strict_types=1);

namespace App\Enums;

enum QuestionStatusEnum: string
{
    case DRAFT = 'draft';

    case PUBLISHED = 'published';

    case ARCHIVED = 'archived';
}
