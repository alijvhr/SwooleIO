<?php

namespace SwooleIO\Cron;

use SplMinHeap;
use SplObjectStorage;
use SwooleIO\Cron;
use function SwooleIO\io;

class CronTab extends Cron
{
    protected SplMinHeap $queue;

    /** @var Cron[][] $table */
    protected array $table = [];
    protected SplObjectStorage $jobs;

    public function __construct()
    {
        parent::__construct();
        $this->queue = new SplMinHeap();
        $this->jobs = new SplObjectStorage();
        $this->callback = $this->run(...);
    }

    public function add(Cron $cron): static
    {
        if (!$timestamp = $cron->next()) return $this->remove($cron);
//        debug("Cron added to scheduler: $cron->id at " . date('H:i:s', $timestamp));
        $this->queue->insert($timestamp);
        $this->table[$timestamp][] = $cron;
        if (!isset($this->jobs[$cron]))
            $this->jobs[$cron] = $timestamp;
        if ($timestamp - time() < $this->timer?->remaining())
            $this->cancel();
        if (!$this->active)
            $this->schedule();
        return $this;
    }

    public function remove(Cron $cron): static
    {
        $this->jobs->detach($cron);
        return $this;
    }

    protected function next(int|null $time = null): ?int
    {
        try {
            return $this->queue->top();
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    public function run(): void
    {
        try {
            $time = time();
            do {
                $timestamp = $this->queue->extract();
//                debug('CronTab running at ' . date('H:i:s', $timestamp));
                if (isset($this->table[$timestamp])) {
//                    debug('CronTab found ' . count($this->table[$timestamp]) . ' jobs to run.');
                    foreach ($this->table[$timestamp] as $cron) {
                        if (isset($this->jobs[$cron])) {
//                            debug('running cron ' . $cron->id);
                            io()->defer($cron->callback);
                            $this->add($cron);
                        }
                    }
                    unset($this->table[$timestamp]);
                }
//                debug('CronTab next run at ' . date('H:i:s', $this->queue->top()));
            } while ($this->queue->top() < $time);
        } catch (\RuntimeException $e) {
            return;
        }
    }

}