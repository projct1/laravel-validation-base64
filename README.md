# Laravel validation base64
Missing laravel validation rules for base64 strings and files.

# Usage

```php
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Projct1\LaravelValidationBase64\Rules\FileBase64Rule;

$data = [
    'photos' => [
        [
            'name' => 'Some photo name',
            'src' => 'data:image/png;base64,iVBORw0...'            
        ]
    ],
    'food' => [
        'tree' => [
            'items' => [
                [
                    'dish' => [
                        'id' => 1
                        'name' => 'Beef'            
                    ],
                    'photo' => [
                        'src' => 'data:image/png;base64,iVBORw0...'  
                    ]
                ]
            ]
        ]
    ]
];

//any validation rules like in native laravel https://laravel.com/docs/12.x/validation#validating-files
$fileRules = File::image()->dimensions(
    Rule::dimensions()->minWidth(1024)->minHeight(768)
);

$rules = [
    'photos.*.src' => new FileBase64Rule($fileRules, 'photos.*.name'),
    'food.tree.*.items.*.photo.src' => new FileBase64Rule($fileRules, 'food.tree.*.items.*.dish.name')
];

$messages = [
    'dimensions' => ':Attribute имеет недостаточные размеры (:cur_width/:cur_height), необходимо не менее :min_width/:min_height пикселей'
];

$attributes = [
    'photos.*.src' => 'Фото галереи ":hint"',
    'food.tree.*.items.*.photo.src' => 'Фото блюда ":hint"'
];

$validator = validator($data, $rules, $messages, $attributes);

dump($validator->fails() ? current($validator->getMessageBag()->getMessages()) : 'ok');
```