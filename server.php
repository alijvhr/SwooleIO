<?php
include 'vendor/autoload.php';

use Swoole\Timer;
use SwooleIO\SocketIO\Event;

$swoole = io();
$swoole->listen('/run/swooleio/swooleio.sock', 0, SWOOLE_UNIX_STREAM);
$swoole->of('/')->on('connection', function (Event $event) use ($swoole) {
    $event->socket->on('ali', function (Event $event) use ($swoole) {
        $event->socket->emit('hello');
    });
    Timer::after(10000, static fn() => $event->socket->emit('bye'));
});
$swoole->start();
