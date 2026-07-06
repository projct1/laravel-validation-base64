<?php

declare(strict_types=1);

namespace Projct1\LaravelValidationBase64\Tests;

use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Projct1\LaravelValidationBase64\Rules\Base64Rule;

class Base64RuleTest extends TestCase
{
    #[Test]
    #[DataProvider('provideValidValues')]
    public function passes_for_valid_base64(string $value): void
    {
        $this->assertTrue($this->validate($value)->passes());
    }

    public static function provideValidValues(): array
    {
        return [
            'сырой base64' => ['QQ=='],
            'data-uri' => ['data:image/png;base64,QQ=='],
            'data-uri с доп. параметрами заголовка' => ['data:image/png;charset=utf-8;base64,QQ=='],
            'data-uri с заголовком в верхнем регистре' => ['DATA:IMAGE/PNG;BASE64,QQ=='],
        ];
    }

    #[Test]
    #[DataProvider('provideInvalidValues')]
    public function fails_with_translated_message_for_invalid_values(mixed $value): void
    {
        $validator = $this->validate($value);

        $this->assertTrue($validator->fails());
        $this->assertSame('The value field must be a valid base64 string.', $validator->errors()->first('value'));
    }

    public static function provideInvalidValues(): array
    {
        return [
            'вообще не base64' => ['definitely not base64!'],
            'неканоничный (unpadded) base64' => ['QQ'],
            'data-uri без разделителя payload' => ['data:image/png;base64'],
            'plain data-uri (без base64)' => ['data:text/plain,hello'],
            'массив' => [['QQ==']],
            'число' => [42],
            'null' => [null],
        ];
    }

    private function validate(mixed $value): Validator
    {
        return validator(['value' => $value], ['value' => new Base64Rule]);
    }
}
