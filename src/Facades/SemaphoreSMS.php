<?php

namespace Bitress\LaravelSemaphore\Facades;

use Illuminate\Support\Facades\Facade;

class SemaphoreSMS extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'semaphore-sms';
    }
}
