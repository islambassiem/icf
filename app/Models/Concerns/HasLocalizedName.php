<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/** For tables with name_en / name_ar columns. Exposes $model->name in the current locale. */
trait HasLocalizedName
{
    /** @return Attribute<string, never> */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => app()->getLocale() === 'ar'
            ? ($this->name_ar ?: $this->name_en)
            : ($this->name_en ?: $this->name_ar));
    }
}
