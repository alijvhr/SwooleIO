<?php

namespace SwooleIO;

use DirectoryIterator;
use SplFileInfo;
use Swoole\Event as SwEvent;
use SwooleIO\Lib\Builder;
use SwooleIO\Time\Timer;
use Throwable;

/**
 * @method static Watcher init()
 * @method static Watcher add()
 * @method static Watcher reload()
 * @method static Watcher watch()
 */
class Watcher extends Builder
{

    protected array $paths = [];

    /** @var resource $inotify */
    private $inotify;
    private IO $io;

    public function start(): static
    {
        $this->io ??= IO::instance();
        if ($this->paths && function_exists('inotify_init')) {
            if (isset($this->inotify)) {
                SwEvent::del($this->inotify);
            }
            $this->inotify = inotify_init();
            foreach ($this->paths as $path) {
                if ($this->watch($path)) {
                    $this->io->log->info("Adding $path to hot reload paths");
                }
            }
            SwEvent::add($this->inotify, $this->reload(...));
        }
        return $this;
    }

    public function add(string $path): static
    {
        if (file_exists($path)) {
            $this->paths[] = $path;
        }
        return $this;
    }

    protected function reload(): static
    {
        $changes = inotify_read($this->inotify);
        if ($changes && !$this->io->reloading) {
            $this->io->reload(false, 1);
            Timer::after(1, $this->start(...));
            $this->io->log->info('File modification detected. reloading...');
        }
        return $this;
    }

    protected function watch(string $path): bool
    {
        if (!file_exists($path)) {
            $this->io->log->warning("Path $path not found");
            return false;
        }
        inotify_add_watch($this->inotify, $path, IN_MODIFY | IN_CREATE);
        if (is_dir($path)) {
            try {
                $dir = new DirectoryIterator($path);
                /** @var SplFileInfo $file */
                foreach ($dir as $file) {
                    $sub = $file->getRealPath();
                    if ($file->isDir() && str_starts_with($sub, "$path/")) {
                        $this->watch($sub);
                    }
                }
            } catch (Throwable $e) {
                $this->io->log->error("Failed to watch directory $path: " . $e->getMessage());
                return false;
            }
        }
        return true;
    }
}