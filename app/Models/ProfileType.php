<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProfileTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name_en',
    'name_ar',
    'description',
])]
class ProfileType extends Model
{
    /** @use HasFactory<ProfileTypeFactory> */
    use HasFactory;
}
