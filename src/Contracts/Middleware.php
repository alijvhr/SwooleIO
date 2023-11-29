<?php

namespace SwooleIO\Psr\Middleware\Psr\Middleware\Contracts;

interface Middleware
{
    public function handle($request, $next);

}