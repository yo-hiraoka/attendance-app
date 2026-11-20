<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

trait UsesUtcTimestamps
{
    public function freshTimestamp(): Carbon
    {
        return Date::now('UTC');
    }

    public function getCreatedAtAttribute($value): ?Carbon
    {
        return $value === null ? null : Date::parse($value, 'UTC');
    }

    public function getUpdatedAtAttribute($value): ?Carbon
    {
        return $value === null ? null : Date::parse($value, 'UTC');
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
