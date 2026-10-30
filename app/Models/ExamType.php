<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExamTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name_en',
    'name_ar',
    'description',
])]
class ExamType extends Model
{
    /** @use HasFactory<ExamTypeFactory> */
    use HasFactory;
}
