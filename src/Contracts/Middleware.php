<?php

namespace SwooleIO\Contracts;

interface Middleware
{
    public function handle($request, $next);

}