<?php

namespace SwooleIO\Constants;

enum EioPacketType: int
{

    case open = 0;
    case close = 1;
    case ping = 2;
    case pong = 3;

    case message = 4;
    case upgrade = 5;
    case noop = 6;
}