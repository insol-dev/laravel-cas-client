<?php

namespace CasSystem\LaravelClient\Traits;

trait CasUserTrait
{
    /**
     * Initialize the trait
     * 
     * @return void
     */
    public function initializeCasUserTrait()
    {
        $this->mergeFillable([
            'cas_user',
            'cas_username', 
            'cas_token', 
            'cas_token_expires_at'
        ]);
        
        $this->mergeCasts([
            'cas_user' => 'boolean',
            'cas_token_expires_at' => 'datetime',
        ]);
    }
}
