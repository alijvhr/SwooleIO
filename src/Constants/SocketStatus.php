<?php

namespace SwooleIO\Constants;
enum SocketStatus
{
    case disconnected;
    case connected;
    case upgrading;
    case upgraded;
    case closing;
    case closed;
}