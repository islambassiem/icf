<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuestionAreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name_en',
    'name_ar',
    'description',
])]
class QuestionArea extends Model
{
    /** @use HasFactory<QuestionAreaFactory> */
    use HasFactory;
}
