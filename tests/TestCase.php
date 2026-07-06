<?php

declare(strict_types=1);

namespace Projct1\LaravelValidationBase64\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Projct1\LaravelValidationBase64\LaravelValidationBase64ServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /** Валидный однопиксельный PNG — для payload'ов, где содержимое не важно или размер должен провалить dimensions. */
    protected const string TINY_PNG_BASE64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkAAIAAAoAAv/lxKUAAAAASUVORK5CYII=';

    protected function getPackageProviders($app): array
    {
        return [LaravelValidationBase64ServiceProvider::class];
    }
}
