<?php

namespace SwooleIO\Constants;

enum SioPacketType: int
{
    case connect = 0;
    case disconnect = 1;

    case event = 2;
    case ack = 3;
    case connect_error = 4;
    case binary_event = 5;
    case binary_ack = 6;
}