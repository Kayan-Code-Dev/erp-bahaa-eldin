<?php

namespace App\Models\Traits;

use DateTimeInterface;

trait SerializesDates
{
    /**
     * Prepare a date for array / JSON serialization.
     * Override to use MySQL datetime format (Y-m-d H:i:s) instead of ISO 8601
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}

