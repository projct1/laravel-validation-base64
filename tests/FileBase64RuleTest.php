<?php

declare(strict_types=1);

namespace Projct1\LaravelValidationBase64\Tests;

use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Projct1\LaravelValidationBase64\Rules\FileBase64Rule;

class FileBase64RuleTest extends TestCase
{
    #[Test]
    public function passes_for_valid_image_payload(): void
    {
        $this->assertTrue($this->validateSrc(self::TINY_PNG_BASE64)->passes());
    }

    /** src уже сохранённого файла приходит его URL'ом — файловая валидация не применяется. */
    #[Test]
    #[DataProvider('provideSkippedUrls')]
    public function skips_validation_for_http_urls(string $value): void
    {
        $this->assertTrue($this->validateSrc($value, $this->undersizedImageRule())->passes());
    }

    public static function provideSkippedUrls(): array
    {
        return [
            'http' => ['http://example.com/stored/photo.png'],
            'https' => ['https://example.com/stored/photo.png'],
        ];
    }

    /** Ссылкой на сохранённый файл считается только http(s): прочие filter_var-валидные схемы идут через base64-валидацию. */
    #[Test]
    #[DataProvider('provideNonHttpSchemes')]
    public function does_not_skip_non_http_schemes(string $value): void
    {
        $validator = $this->validateSrc($value);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('must be a valid base64 string', $validator->errors()->first('photos.0.src'));
    }

    public static function provideNonHttpSchemes(): array
    {
        return [
            'ftp' => ['ftp://host/file.png'],
            'phar' => ['phar://archive/file.png'],
        ];
    }

    #[Test]
    public function substitutes_current_dimensions_when_image_is_undersized(): void
    {
        Lang::addLines(['validation.dimensions' => 'Got :cur_width x :cur_height, need :min_width x :min_height.'], 'en');

        $validator = $this->validateSrc(self::TINY_PNG_BASE64, $this->undersizedImageRule());

        $this->assertTrue($validator->fails());
        $this->assertSame('Got 1 x 1, need 100 x 100.', $validator->errors()->first('photos.0.src'));
    }

    /** У не-изображения нет размеров: dimensions-сообщение придерживается вместо утечки сырых :cur_*-плейсхолдеров. */
    #[Test]
    public function withholds_dimensions_message_for_undecodable_image(): void
    {
        Lang::addLines(['validation.dimensions' => 'Got :cur_width x :cur_height, need :min_width x :min_height.'], 'en');

        $validator = $this->validateSrc('data:image/png;base64,'.base64_encode('not an image'), $this->undersizedImageRule());

        $this->assertTrue($validator->fails());

        $messages = $validator->errors()->get('photos.0.src');
        $this->assertNotEmpty($messages);

        foreach ($messages as $message) {
            $this->assertStringNotContainsString(':cur_', $message);
        }
    }

    /** Регресс: легальный заголовок data-URI с доп. параметрами не должен ронять извлечение payload. */
    #[Test]
    public function handles_data_uri_header_parameters(): void
    {
        $validator = $this->validateSrc('data:image/png;charset=utf-8;base64,'.base64_encode('not an image'));

        $this->assertTrue($validator->fails());
    }

    /** Регресс: не-строковое значение проваливает валидацию, а не бросает TypeError. */
    #[Test]
    public function fails_with_base64_message_for_array_value(): void
    {
        $validator = $this->validateSrc([self::TINY_PNG_BASE64]);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('must be a valid base64 string', $validator->errors()->first('photos.0.src'));
    }

    /** Мусор, не являющийся base64, сообщается base64-провалом — а не вводящим в заблуждение image-провалом на пустом tmp-файле. */
    #[Test]
    public function fails_with_base64_message_for_non_base64_string(): void
    {
        $validator = $this->validateSrc('definitely not base64!');

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('must be a valid base64 string', $validator->errors()->first('photos.0.src'));
    }

    #[Test]
    public function substitutes_hint_from_sibling_field(): void
    {
        Lang::addLines(['validation.image' => '":hint" must be an image.'], 'en');

        $validator = validator(
            ['photos' => [['name' => 'Menu photo', 'src' => 'data:image/png;base64,'.base64_encode('not an image')]]],
            ['photos.*.src' => new FileBase64Rule(File::image(), 'photos.*.name')],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame('"Menu photo" must be an image.', $validator->errors()->first('photos.0.src'));
    }

    /** Отсутствующее hint-значение подставляется пустой строкой — не падением и не сырым :hint. */
    #[Test]
    public function resolves_missing_hint_to_empty_string(): void
    {
        Lang::addLines(['validation.image' => '":hint" must be an image.'], 'en');

        $validator = validator(
            ['photos' => [['src' => 'data:image/png;base64,'.base64_encode('not an image')]]],
            ['photos.*.src' => new FileBase64Rule(File::image(), 'photos.*.name')],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame('"" must be an image.', $validator->errors()->first('photos.0.src'));
    }

    /** Сквозной README-сценарий: кастомные messages/attributes наружного валидатора + пакетные :hint и :cur_* в одном сообщении. */
    #[Test]
    public function readme_usage_example_works(): void
    {
        $validator = validator(
            ['photos' => [['name' => 'Фото зала', 'src' => self::TINY_PNG_BASE64]]],
            ['photos.*.src' => new FileBase64Rule(
                File::image()->dimensions(Rule::dimensions()->minWidth(100)->minHeight(100)),
                'photos.*.name',
            )],
            ['dimensions' => ':attribute — :cur_width/:cur_height px, нужно от :min_width/:min_height px'],
            ['photos.*.src' => 'Фото галереи ":hint"'],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Фото галереи "Фото зала" — 1/1 px, нужно от 100/100 px',
            $validator->errors()->first('photos.0.src'),
        );
    }

    private function undersizedImageRule(): FileBase64Rule
    {
        return new FileBase64Rule(File::image()->dimensions(Rule::dimensions()->minWidth(100)->minHeight(100)));
    }

    private function validateSrc(mixed $src, ?FileBase64Rule $rule = null): Validator
    {
        return validator(
            ['photos' => [['src' => $src]]],
            ['photos.*.src' => $rule ?? new FileBase64Rule(File::image())],
        );
    }
}
