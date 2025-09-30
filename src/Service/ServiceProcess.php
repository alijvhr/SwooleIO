<?php

namespace SwooleIO\Service;

use Swoole\Event;
use SwooleIO\Exceptions\DuplicateServiceID;
use SwooleIO\Exceptions\ServiceNotFound;
use SwooleIO\Lib\Process;
use SwooleIO\Service;
use SwooleIO\Service\Packet\Call;
use SwooleIO\Service\Packet\Exception;
use SwooleIO\Service\Packet\ServicePacket;
use SwooleIO\Service\Packet\Value;
use SwooleIO\SocketIO\RemoteSocket;
use SwooleIO\SocketIO\Socket;
use Throwable;
use function SwooleIO\debug;
use function SwooleIO\io;

/**
 *
 */
class ServiceProcess extends Process
{

    /** @var Async[] $queue */
    protected array $queue = [];

    protected ServiceProxy $from;


    /**
     * @param class-string<Service> $service
     */
    public function __construct(public readonly string $service, protected readonly int|string|null $init = null, public readonly string|int $alias = '')
    {
        parent::__construct();
    }

    public function onExit(): void
    {
        if (!$this->service::$no_save) {
            $this->service::saveAll();
        }
        $name = $this->alias ?: $this->service;
        $this->io->log->info("Service $name stopped!");
    }

    /**
     * @throws ServiceNotFound|DuplicateServiceID
     */
    public function onStart(): void
    {
        mt_srand();
        $name = $this->alias ?: $this->service;
        $this->io->log->info("Service $name started");
        $this->io->id(service: $name, worker: '');
        if (!is_a($this->service, Service::class, true)) {
            throw new ServiceNotFound("Constant service($this->service) should be FQDN of a 'Service' class child");
        }
        if (method_exists($this->service, 'run')) {
            go(fn() => $this->service::run($this));
        }
        go(fn() => $this->service::loadAll());
        if (isset($this->init)) {
            go(function () {
                try {
                    $this->service::create($this->init);
                } catch (DuplicateServiceID $e) {

                }
            });
        }
        $this->listen();

    }

    protected function listen(): void
    {
        Event::add($this, fn() => go(fn() => $this->send($this->receive($this->read()))));
    }

    protected function send(?ServicePacket $packet): void
    {
        if (is_null($packet)) {
            return;
        }
        foreach ($packet->data as &$data) {
            if ($data instanceof Socket) {
                $data = RemoteSocket::from($data);
            }
//            elseif ($data instanceof Service) {
//                $data = ServiceProxy::for($this->alias, $data->id ?? null);
//            }
        }
        go(function () use ($packet) {
            try {
                $serialized = serialize($packet);
                if ($packet->to->service === 'worker' && $packet->to->id >= 0) {
                    io()->server->sendMessage($serialized, $packet->to->id);
                } else {
                    $this->write($serialized);
                }
            } catch (Throwable $throwable) {
                io()->log->error($throwable->getMessage());
                io()->log->error($throwable->getTraceAsString());
            }
        });
    }

    public function call(ServiceProxy $to, string $name, array $args = []): ?Async
    {
        $packet = new Call($to, $name, $args);
        $return = Async::wait($packet->id, io()->config('service.return.timeout') ?? 0.1);
        $this->send($packet);
        return $return;
    }

    protected function receive(string $message): Value|Exception|null
    {
        /** @var ServicePacket $packet */
        /** @noinspection UnserializeExploitsInspection */
        $packet = @unserialize($message);
        if ($packet instanceof Call) {
            try {
                $data = $packet->to->{$packet->method}(...$packet->data);
                if (!$packet->to->response) {
                    return null;
                }
                $return = Value::for($packet, $data);
            } catch (Throwable $e) {
                io()->log->error($e);
                debug($e->getTraceAsString());
                $return = Exception::for($packet, $e);
            }
            return $return;
        }

        if (is_object($packet)) {
            Async::setById($packet->id, $packet);
        }
        return null;
    }


    /**
     * @param class-string<Service> $service
     */
    public static function register(string $service, int|string|null $init = null, string $alias = ''): ServiceProxy
    {
        $process = new static($service, $init, $alias);
        if ($alias) {
            return io()->services->add($process, $alias, $init);
        }
        return new ServiceProxy($service, $init);
    }

    public static function current(): ?ServiceProcess
    {
        return self::$current ?? null;
    }

}