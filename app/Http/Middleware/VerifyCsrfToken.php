<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '/5054123156:AAFIAGIdbKqjTMdPRhafvu4LFZi-16IR6OU/webhook',
        '/webhook'
    ];
}
