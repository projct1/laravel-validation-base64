<?php

namespace Projct1\LaravelValidationBase64\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Http\File;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File as FileRule;
use Illuminate\Validation\Validator;

class FileBase64Rule extends Base64Rule implements DataAwareRule, ValidatorAwareRule
{
    public array $data = [];

    public function __construct(protected FileRule $fileRule, protected ?string $hintPath = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return;
        }

        $file = $this->makeTmpFile($value);

        $this->fileRule->setData(Arr::undot([$attribute => $file]));

        if ($this->fileRule->passes($attribute, $file) === false) {
            if ($dimensions = $file->dimensions()) {
                $attrs = [
                    'cur_width' => $dimensions[0],
                    'cur_height' => $dimensions[1]
                ];
            }

            if ($this->hintPath) {
                $attrs['hint'] = $this->getHint($attribute);
            }

            foreach ($this->fileRule->message() as $message) {
                $fail($message)->translate($attrs ?? []);
            }
        }

        unlink($file->path());
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function setValidator(Validator $validator): self
    {
        $this->fileRule->setValidator($validator);

        return $this;
    }

    protected function getHint(string $attr):? string
    {
        return Arr::get(
            $this->data,
            Str::replaceArray('*', preg_match_all('/\d+/', $attr, $m) ? $m[0] : [], $this->hintPath)
        );
    }

    protected function makeTmpFile(string $base64): File
    {
        $tmp = tempnam(sys_get_temp_dir(), 'base64-file');

        file_put_contents($tmp, $this->getDecoded($this->extractEncoded($base64)));

        return new File($tmp);
    }
}
