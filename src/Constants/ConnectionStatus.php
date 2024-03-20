<?php

namespace SwooleIO\Constants;
enum ConnectionStatus
{
    case disconnected;
    case connected;
    case upgrading;
    case upgraded;
    case closing;
    case closed;
}