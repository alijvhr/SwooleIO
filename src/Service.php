<?php

namespace SwooleIO;

use Swoole\Event;
use SwooleIO\Exceptions\DuplicateServiceID;
use SwooleIO\Service\ServiceProxy;
use SwooleIO\SocketIO\Packet;
use SwooleIO\SocketIO\SocketInterface;
use SwooleIO\Time\TimeManager;

abstract class Service
{

    public const string name = 'srv';
    protected static array $joined = [];
    protected static array $instances = [];
    public static bool $no_save = false;
    /** @var ServiceProxy[] $observers */
    public array $observers = [];
    /** @var SocketInterface[][] $subscribers */
    protected array $subscribers = [];
    /** @var SocketInterface[] $sockets */
    protected array $sockets = [];
    public readonly int|string $id;
    protected TimeManager $timers;

    protected function __construct()
    {

    }

    public static function get(int|string $id = 0): ?static
    {
        return self::$instances[$id] ?? null;
    }

    public static function exists(int|string $id = 0): bool
    {
        return isset(self::$instances[$id]);
    }

    public static function disconnect(SocketInterface $socket, string $reason = ''): void
    {
        $services = self::$joined[$socket->sid()] ?? [];
        foreach ($services as $service) {
            $service->unsubscribe($socket, $reason);
        }
        unset(self::$joined[$socket->sid()]);
    }

    /**
     * @param null|int|string $id
     * @param mixed ...$args
     * @return Service
     * @throws DuplicateServiceID
     */
    public static function create(null|int|string $id = 0, ...$args): static
    {
        if (is_null($id)) {
            $id = static::name . '-' . date('YmdHis');
            /** @noinspection ALL */
            for ($i = 0; isset(self::$instances[$id . $i]); $i++) {
            }
            $id .= $i;
        } elseif (isset(self::$instances[$id])) {
            throw new DuplicateServiceID('Service ' . self::class . " #$id already exists.");
        }
        $instance = self::$instances[$id] = new static(...$args);
        $instance->id = $id;
        $instance->setup();
        return $instance;

    }

    /**
     * @param ServiceProxy|ServiceProxy[] $observers
     * @throws DuplicateServiceID
     */
    public static function createObserved(Service|ServiceProxy|array $observers, array $properties, int|string|null $id = null): ?static
    {
        $service = self::create($id, ...$properties);
        if (!is_array($observers))
            $observers = [$observers];
        $list = [];
        foreach ($observers as $observer) {
            if ($observer instanceof Service)
                $observer = ServiceProxy::from($observer);
            if ($observer instanceof ServiceProxy) $list[] = $observer;
        }
        $service->observers = $list;
        return $service;
    }

    public function unsubscribe(SocketInterface $socket, string $reason = ''): void
    {
        unset($this->sockets[$socket->sid()], $this->subscribers[$socket->auth()->id][$socket->sid()]);
        if (count($this->subscribers[$socket->auth()->id] ?? [1]) == 0)
            unset($this->subscribers[$socket->auth()->id]);
    }

    protected function setup(): void
    {
        $this->timers = new TimeManager();
    }

    protected function destroy(): void
    {
        if (isset($this->timers)) $this->timers->clear();
        if (isset($this->id))
            unset(self::$instances[$this->id]);
        io()->defer($this->cleanup(...));
    }

    public function subscribe(SocketInterface $socket, array $data = []): void
    {
        $sub = &$this->subscribers[$socket->auth()->id];
        $sid = $socket->sid();
        if (isset($sub))
            $sub[$sid] = $socket;
        else
            $sub = [$sid => $socket];
        $this->sockets[$sid] = $socket;
        $joined = &self::$joined[$sid];
        if (isset($joined))
            $joined[] = $this;
        else
            $joined = [$this];
    }

    abstract public function receive(SocketInterface $socket, string $event, array $data = []);

    public function __destruct()
    {
        $this->destroy();
    }

    abstract protected function cleanup(): void;

    public static function saveAll(): void
    {
        if (self::name !== 'srv') {
            debug('Saving ' . static::name . ' to disk');
        }
    }

    public static function loadAll(): void
    {
        if (self::name !== 'srv') {
            debug('loading ' . static::name . ' from disk');
        }
    }

    public static function reload(bool $force = false): void
    {
        self::$no_save = $force;
        Event::exit();
    }

    protected function broadcast(array|Packet $message, array $exclude = []): void
    {
        foreach ($this->sockets as $socket) {
            if (in_array($socket->auth()->id, $exclude)) continue;
            if ($message instanceof Packet)
                $socket->push($message);
            else
                $socket->emit('command', $message);
        }
    }

    protected function emitUser(string $user, array $message): void
    {
        if (isset($this->subscribers[$user]))
            foreach ($this->subscribers[$user] as $socket)
                $socket->emit('command', $message);
    }

    protected function emit(SocketInterface $socket, array $message): void
    {
        $socket->emit('command', $message);
    }
}
