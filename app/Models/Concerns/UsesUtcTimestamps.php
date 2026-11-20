<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

trait UsesUtcTimestamps
{
    public function freshTimestamp(): Carbon
    {
        return Date::now('UTC');
    }

    protected function createdAt(): Attribute
    {
        return Attribute::get(fn ($value) => $value === null ? null : Date::parse($value, 'UTC'))->withoutObjectCaching();
    }

    protected function updatedAt(): Attribute
    {
        return Attribute::get(fn ($value) => $value === null ? null : Date::parse($value, 'UTC'))->withoutObjectCaching();
    }

    protected function addDateAttributesToArray(array $attributes)
    {
        foreach ($this->getDates() as $key) {
            if (isset($attributes[$key])) {
                $attributes[$key] = $this->serializeDate(Date::parse($attributes[$key], 'UTC'));
            }
        }

        return $attributes;
    }
}
