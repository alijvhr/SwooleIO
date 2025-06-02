<?php

namespace SwooleIO\Constants;

enum TimerType: string
{
    case timeout  = 'after';
    case interval = 'tick';
    case defer    = 'defer';
    case hybrid   = '';
}