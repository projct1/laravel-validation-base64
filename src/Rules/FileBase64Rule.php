<?php

declare(strict_types=1);

namespace Projct1\LaravelValidationBase64\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Http\File;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File as FileRule;
use Illuminate\Validation\Validator;
use RuntimeException;

class FileBase64Rule extends Base64Rule implements DataAwareRule, ValidatorAwareRule
{
    protected array $data = [];

    public function __construct(protected FileRule $fileRule, protected ?string $hintPath = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && $this->isUrl($value)) {
            return;
        }

        if (! is_string($value) || ! $this->decodable($value)) {
            $this->failBase64($fail);

            return;
        }

        $file = $this->makeTmpFile($value);

        try {
            $this->fileRule->setData(Arr::undot([$attribute => $file]));

            if ($this->fileRule->passes($attribute, $file) === false) {
                $this->failWithMessages($attribute, $file, $fail);
            }
        } finally {
            unlink($file->path());
        }
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function setValidator(Validator $validator): static
    {
        $this->fileRule->setValidator($validator);

        return $this;
    }

    /** Уже сохранённый файл приходит своим URL — валидировать нечего. Ссылкой считается только http(s): произвольные схемы (ftp, phar, ...) — нет. */
    protected function isUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * Пакетные плейсхолдеры поверх замен ядра: :cur_width/:cur_height (известны
     * только у декодируемых изображений) и :hint. При неизвестных размерах
     * сообщения с :cur_* придерживаются — жалоба на размеры бессмысленна для
     * не-изображения и утекла бы пользователю сырыми плейсхолдерами.
     */
    protected function failWithMessages(string $attribute, File $file, Closure $fail): void
    {
        if ($dimensions = $file->dimensions()) {
            $attrs = [
                'cur_width' => $dimensions[0],
                'cur_height' => $dimensions[1],
            ];
        }

        if ($this->hintPath) {
            $attrs['hint'] = (string) $this->getHint($attribute);
        }

        foreach ($this->fileRule->message() as $message) {
            if ($dimensions || ! str_contains($message, ':cur_')) {
                $fail($message)->translate($attrs ?? []);
            }
        }
    }

    /**
     * Значение по hint-пути (например, видимое пользователю имя файла) — для
     * подстановки :hint в сообщения. Wildcards пути резолвятся из числовых
     * сегментов провалившегося атрибута: photos.2.src + photos.*.name -> photos.2.name.
     */
    protected function getHint(string $attribute): ?string
    {
        preg_match_all('/(?:^|\.)(\d+)(?=\.|$)/', $attribute, $matches);

        return Arr::get($this->data, Str::replaceArray('*', $matches[1], $this->hintPath));
    }

    protected function makeTmpFile(string $base64): File
    {
        $tmp = tempnam(sys_get_temp_dir(), 'base64-file');

        if ($tmp === false) {
            throw new RuntimeException('Unable to create a temporary file for base64 validation.');
        }

        file_put_contents($tmp, $this->getDecoded($this->extractEncoded($base64)));

        return new File($tmp);
    }
}
