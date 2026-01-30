<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Carbon\Carbon;

class MySqlDateTime implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!is_string($value)) {
            return false;
        }

        // Try to parse the value as MySQL datetime format (Y-m-d H:i:s)
        try {
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $value);
            
            // Verify the parsed date matches the input exactly (to catch invalid dates like 2025-13-45)
            return $date->format('Y-m-d H:i:s') === $value;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must be in MySQL datetime format (Y-m-d H:i:s), e.g., 2025-12-02 23:33:25.';
    }
}

