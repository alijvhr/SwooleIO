<?php

namespace SwooleIO;

use DirectoryIterator;
use SplFileInfo;
use Swoole\Event as SwEvent;
use SwooleIO\Lib\Builder;
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
        $this->io = IO::instance();
        if ($this->paths && function_exists('inotify_init')) {
            $this->inotify = inotify_init();
            foreach ($this->paths as $path) {
                $this->io->log->info("Adding $path to hot reload paths");
                $this->watch($path);
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
        if ($changes && !io()->timers->active('reload')) {
            $this->io->log->info('File modification detected. reloading...');
            $this->io->timers->after('reload', 1, io()->reload(...));
        }
        return $this;
    }

    protected function watch(string $path): static
    {
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
            }
        }
        return $this;
    }
}