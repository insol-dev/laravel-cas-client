<?php

namespace CasSystem\LaravelClient\Facades;

use Illuminate\Support\Facades\Facade;

class CasClient extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'cas-client';
    }
}