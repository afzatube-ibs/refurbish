<?php

namespace App\Http\Requests\Settings\Concerns;

trait NormalizesConnectionFields
{
    protected function prepareForValidation(): void
    {
        $isActive = $this->input('is_active');

        if (is_array($isActive)) {
            $isActive = end($isActive);
        }

        $this->merge([
            'is_active' => filter_var($isActive, FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
