<?php

namespace Projct1\LaravelValidationBase64\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Base64Rule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = $this->extractEncoded($value);
        $decoded = $this->getDecoded($value);

        if ($decoded === false || base64_encode($decoded) !== $value) {
            $fail('validation.base64')->translate();
        }
    }

    protected function extractEncoded(string $value): string
    {
        if (str_contains($value, ';base64')) {
            [, $value] = explode(';', $value);
            [, $value] = explode(',', $value);
        }

        return $value;
    }

    protected function getDecoded(string $data): string|false
    {
        return base64_decode($data, true);
    }
}
