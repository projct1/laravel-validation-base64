<?php

declare(strict_types=1);

namespace Projct1\LaravelValidationBase64\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Base64Rule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->decodable($value)) {
            $this->failBase64($fail);
        }
    }

    protected function failBase64(Closure $fail): void
    {
        $fail('base64::validation.base64')->translate();
    }

    /** Принимается только канонический base64 (строгий round-trip): unpadded и url-safe варианты отклоняются. */
    protected function decodable(string $value): bool
    {
        $encoded = $this->extractEncoded($value);
        $decoded = $this->getDecoded($encoded);

        return $decoded !== false && base64_encode($decoded) === $encoded;
    }

    /** Payload из data-URI ("data:<mediatype>[;параметры];base64,<данные>"); сама строка, если это не data-URI. */
    protected function extractEncoded(string $value): string
    {
        return preg_match('/^data:[^,]*;base64,(.*)$/is', $value, $matches) ? $matches[1] : $value;
    }

    protected function getDecoded(string $data): string|false
    {
        return base64_decode($data, true);
    }
}
