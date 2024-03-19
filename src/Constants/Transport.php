<?php

namespace SwooleIO\Constants;
enum Transport: int
{
    case polling = 0;
    case websocket = 1;
    case webtransport = 2;
}