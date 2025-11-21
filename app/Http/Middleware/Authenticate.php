<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Override default redirect to prevent referencing a non-existent route.
     */
    protected function redirectTo($request): ?string
    {
        // For API requests we simply return null so Laravel responds with 401 JSON
        return null;
    }
}

