# Laravel validation base64

Недостающие правила валидации Laravel для base64-строк и base64-файлов.

- `Base64Rule` — значение является каноничной base64-строкой: сырой либо завёрнутой в `data:<mediatype>;base64,<данные>`-URI.
- `FileBase64Rule` — декодированное содержимое проходит любые нативные ограничения [`Illuminate\Validation\Rules\File`](https://laravel.com/docs/validation#validating-files) (тип, размер, размеры изображения, ...).

## Требования

- PHP ^8.4
- Laravel 12 или 13

## Установка

```bash
composer require projct1/laravel-validation-base64
```

Сервис-провайдер (переводы сообщений) подключается автоматически через package discovery — регистрировать ничего не нужно.

## Валидация base64-строки

```php
use Projct1\LaravelValidationBase64\Rules\Base64Rule;

$request->validate([
    'signature' => ['required', new Base64Rule],
]);
```

Принимается как сырой base64 (`iVBORw0...`), так и data-URI (`data:image/png;base64,iVBORw0...`) — включая заголовки с дополнительными параметрами (`data:image/png;charset=utf-8;base64,...`) и в любом регистре.

## Валидация base64-файла

Первый аргумент конструктора — любой набор нативных файловых ограничений `Illuminate\Validation\Rules\File`. Второй (необязательный) — путь к соседнему полю, значение которого подставляется в сообщения как `:hint` (например, видимое пользователю имя файла); wildcards пути резолвятся из числовых сегментов провалившегося атрибута: `photos.2.src` + `photos.*.name` → `photos.2.name`.

```php
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Projct1\LaravelValidationBase64\Rules\FileBase64Rule;

$data = [
    'photos' => [
        [
            'name' => 'Фото зала',
            'src' => 'data:image/png;base64,iVBORw0...',
        ],
    ],
    'food' => [
        'tree' => [
            'items' => [
                [
                    'dish' => [
                        'id' => 1,
                        'name' => 'Стейк',
                    ],
                    'photo' => [
                        'src' => 'data:image/png;base64,iVBORw0...',
                    ],
                ],
            ],
        ],
    ],
];

// любые нативные файловые правила: https://laravel.com/docs/validation#validating-files
$fileRules = File::image()->dimensions(
    Rule::dimensions()->minWidth(1024)->minHeight(768)
);

$rules = [
    'photos.*.src' => new FileBase64Rule($fileRules, 'photos.*.name'),
    'food.tree.*.items.*.photo.src' => new FileBase64Rule($fileRules, 'food.tree.*.items.*.dish.name'),
];

$messages = [
    'dimensions' => ':Attribute имеет недостаточные размеры (:cur_width/:cur_height), необходимо не менее :min_width/:min_height пикселей',
];

$attributes = [
    'photos.*.src' => 'Фото галереи ":hint"',
    'food.tree.*.items.*.photo.src' => 'Фото блюда ":hint"',
];

$validator = validator($data, $rules, $messages, $attributes);
```

Кастомные `$messages` и `$attributes` наружного валидатора действуют и внутри файловых правил — как в нативной валидации.

## Плейсхолдеры сообщений

`FileBase64Rule` добавляет к заменам ядра свои плейсхолдеры:

- `:cur_width` / `:cur_height` — фактические размеры декодированного изображения. Подставляются только когда содержимое реально декодируется в изображение; для не-изображений сообщения с этими плейсхолдерами придерживаются — сырые `:cur_*` никогда не доходят до пользователя.
- `:hint` — значение соседнего поля по пути из второго аргумента конструктора. При отсутствии значения подставляется пустая строка.

## Поведение

- **Только канонический base64.** Проверка — строгий round-trip (`base64_encode(base64_decode($value)) === $value`): unpadded-варианты (`QQ` вместо `QQ==`) и url-safe-алфавит отклоняются.
- **`http(s)`-URL пропускают файловую валидацию.** Уже сохранённый файл приходит своим URL — декодировать нечего. Ссылкой считается только `http`/`https`; значения в любой другой схеме (`ftp`, `phar`, ...) идут через base64-валидацию и проваливаются.
- **Невалидный ввод не бросает исключений.** Не-строки (массивы, числа, null), битый base64 и кривые заголовки data-URI проваливают валидацию с base64-сообщением, а не роняют запрос `TypeError`'ом.
- **Пустая строка — валидный base64 нулевой длины.** Обязательность значения — зона ответственности `required`, не этого правила.

## Переводы

Сообщение о провале — `base64::validation.base64` (в комплекте `en` и `ru`). Переопределение:

```bash
php artisan vendor:publish --tag=base64-lang
```

после чего тексты правятся в `lang/vendor/base64/{locale}/validation.php`.

## Разработка

```bash
composer test   # phpunit (orchestra/testbench)
composer lint   # pint --test
composer fix    # pint
```

CI гоняет матрицу PHP 8.4/8.5 × Laravel 12/13 плюс отдельную pint-джобу.
