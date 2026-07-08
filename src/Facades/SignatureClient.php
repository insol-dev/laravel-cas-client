<?php

namespace CasSystem\LaravelClient\Facades;

use Illuminate\Support\Facades\Facade;

class SignatureClient extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'cas-signature-client';
    }
}