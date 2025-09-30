<?php

namespace SwooleIO;

use BadMethodCallException;
use DateMalformedStringException;
use DateTime;
use Exception;
use SwooleIO\Cron\UnitValue;
use SwooleIO\Time\Timer;
use TypeError;

/**
 * Cron class for scheduling tasks dynamically based on time intervals.
 *
 * This class allows for dynamic method invocation using the `__call` and `__callStatic` magic methods.
 * The methods follow the patterns `everyX` and `atX`, where `X` can be Minute, Hour, Day, DayOfWeek, DayOfMonth, or Month.
 *
 *  Example usage:
 *
 * ```PHP
 * $cron = new Cron();
 * $cron->everyMinute(5);
 * $cron->atHour(15);
 * ```
 *
 * The supported dynamic methods include:
 *
 * @method self everySecond(int $step = 1, int $from = 0, int|null $through = null) Schedule a task to run every X seconds.
 * @method self everyMinute(int $step = 1, int $from = 0, int|null $through = null) Schedule a task to run every X minutes.
 * @method self everyHour(int $step = 1, int $from = 0, int|null $through = null) Schedule a task to run every X hours.
 * @method self everyDay(int $step = 1, int $from = 0, int|null $through = null) Schedule a task to run every X days.
 * @method self everyDayOfWeek(int $step = 1, int $from = 0, int|null $through = null) Schedule a task to run every X days of the week.
 * @method self everyDayOfMonth(int $step = 1, int $from = 0, int|null $through = null) Schedule a task to run every X days of the month.
 * @method self everyMonth(int $step = 1, int $from = 0, int|null $through = null) Schedule a task to run every X months.
 *
 * @method self atSecond(int $from = 0) Schedule a task to run at a specific second.
 * @method self atMinute(int $from = 0) Schedule a task to run at a specific minute.
 * @method self atHour(int $from = 0) Schedule a task to run at a specific hour.
 * @method self atDay(int $from = 0) Schedule a task to run at a specific day.
 * @method self atDayOfWeek(int $from = 0) Schedule a task to run at a specific day of the week.
 * @method self atDayOfMonth(int $from = 0) Schedule a task to run at a specific day of the month.
 * @method self atMonth(int $from = 0) Schedule a task to run at a specific month.
 *
 * @method static self everySecond(int $step = 1, int $from = 0, int|null $through = null) Schedule a task to run every X seconds.
 * @method static self everyMinute(int $step = 1, int $from = 0, int|null $through = null) Schedule a task to run every X minutes.
 * @method static self everyHour(int $step = 1, int $from = 0, int|null $through = null) Schedule a task to run every X hours.
 * @method static self everyDay(int $step = 1, int $from = 0, int|null $through = null) Schedule a task to run every X days.
 * @method static self everyDayOfWeek(int $step = 1, int $from = 0, int|null $through = null) Schedule a task to run every X days of the week.
 * @method static self everyDayOfMonth(int $step = 1, int $from = 0, int|null $through = null) Schedule a task to run every X days of the month.
 * @method static self everyMonth(int $step = 1, int $from = 0, int|null $through = null) Schedule a task to run every X months.
 *
 * @method static self atSecond(int $from = 0) Schedule a task to run at a specific second.
 * @method static self atMinute(int $from = 0) Schedule a task to run at a specific minute.
 * @method static self atHour(int $from = 0) Schedule a task to run at a specific hour.
 * @method static self atDay(int $from = 0) Schedule a task to run at a specific day.
 * @method static self atDayOfWeek(int $from = 0) Schedule a task to run at a specific day of the week.
 * @method static self atDayOfMonth(int $from = 0) Schedule a task to run at a specific day of the month.
 * @method static self atMonth(int $from = 0) Schedule a task to run at a specific month.
 *
 */
class Cron
{

    protected UnitValue $dayOfMonth, $dayOfWeek, $month, $hour, $minute, $second;

    protected DateTime $once;

    protected ?Timer $timer = null;

    /** @var callable $callback */
    protected $callback;

    protected bool $active = false;

    protected const array UNITS = ['second' => 's', 'minute' => 'i', 'hour' => 'H', 'day' => 'd', 'dayOfWeek' => 'w', 'dayOfMonth' => 'j'];
    protected int $id;


    public function __construct()
    {
        $this->id = spl_object_id($this);
    }

    /**
     * Dynamically handle method calls for scheduling.
     *
     * @param string $name The method name being called.
     * @param array $arguments The arguments passed to the method.
     * @return self
     * @throws BadMethodCallException If the method does not exist.
     */
    public function __call(string $name, array $arguments): self
    {
        if (preg_match('/^(every|at)(Second|Minute|Hour|Month|Day(OfWeek|OfMonth))$/', $name, $match)) {
            return $this->{$match[1]}(lcfirst($match[2]), ...$arguments);
        }
        throw new BadMethodCallException("Method $name does not exist.");
    }

    /**
     * Dynamically handle static method calls for scheduling.
     *
     * @param string $name The static method name being called.
     * @param array $arguments The arguments passed to the method.
     * @return self
     */
    public static function __callStatic(string $name, array $arguments): self
    {
        return (new static)->{lcfirst($name)}(...$arguments);
    }

    /**
     * Schedule a task to run at specific intervals.
     *
     * @param string $name The time unit (Minute, Hour, Day, DayOfWeek, DayOfMonth, Month).
     * @param int $step The step interval.
     * @param int $from The starting time.
     * @param int|null $through The ending time.
     * @return self
     * @throws Exception
     */
    protected function every(string $name, int $step = 1, int $from = 0, int|null $through = null): static
    {
        if ($this->active)
            throw new Exception('Cannot modify schedule while timer is active.');
        $this->$name = new UnitValue($name, $from, $through, $step);
        return $this;
    }

    /**
     * Schedule a task to run at a specific time or range of times.
     *
     * @param string $name The time unit (Minute, Hour, Day, DayOfWeek, DayOfMonth, Month).
     * @param int $from The specific time to start.
     * @param int|null $through The specific time to end.
     * @return self
     */
    protected function at(string $name, int $from = 0): static
    {
        if ($this->active)
            throw new Exception('Cannot modify schedule while timer is active.');
        $this->$name = new UnitValue($name, $from);
        return $this;
    }

    /**
     * @throws TypeError
     */
    public function job(string|array|callable $callback): static
    {
        if (!is_callable($callback))
            throw new TypeError('Invalid callback provided.');
        $this->callback = $callback;
        return $this;
    }

    protected function once(DateTime $datetime): static
    {
        $this->once = $datetime;
        return $this;
    }

    public function cancel(): void
    {
        $this->active = false;
        $this->timer?->stop();
    }

    public function schedule(): void
    {
        if (!$this->active) {
            $this->active = true;
            if ($next = $this->next()) {
                $after = max($next - microtime(true) + .5, 0);
//                debug(get_class($this) . "($this->id) scheduled to run in $after seconds.");
                $this->timer = Timer::after(
                    $after,
                    function () {
                        ($this->callback)();
                        $this->active = false;
                        if (!isset($this->once)) $this->schedule();
                    }
                );
            }
        }
    }


    /**
     * Calculates the next run time based on the current DateTime, schedule, and unit.
     *
     * @return int|null The next run time as a DateTime object, or null if not applicable.
     */

    protected function next(int|null $time = null): ?int
    {
        try {
            $time = ($time ?? time()) + 1;
            $next = new DateTime("@$time +1 second");
            if (isset($this->once)) {
                return $this->once > $next ? $this->once->getTimestamp() : null;
            }
            // Check each unit type and find the next run time.
            foreach (self::UNITS as $name => $symbol) {
                if (isset($this->$name)) {
                    $unit = $this->$name;
                    $step = $unit->step;
                    $u_current = +$next->format(self::UNITS[$name]);
                    $u_next = $step ? $step - ($u_current - $unit->from) % $step : 0;
                    if ($u_next && $u_next != $step && $u_current > $unit->from) {
                        $next->modify("$u_next $name");
//                        debug("Cron($this->id) time added $u_next $name resulting in " . $next->format('H:i:s'));
                    }
                    $u_current = +$next->format(self::UNITS[$name]);
                    $overflow = isset($unit->through) && $unit->through < $u_current;
                    if (!$step || $step > $u_current || $unit->from > $u_current || $overflow) {
                        $next->modify("-$u_next $name");
                        $next = $this->reset($next, $unit);
                    }
                }
            }
        } catch (DateMalformedStringException $e) {
            io()->log->error($e);
            return null;
        }
        return $next->getTimestamp();
    }

    private function reset(DateTime $time, UnitValue $unit): DateTime
    {
        $u = $unit->name;
        $current = +$time->format(self::UNITS[$u]);
        if ($current <= $unit->from) $time->modify(($unit->from - $current) . " $u");
        else {
            $max = $current + 1;
            $time->modify("-$max $u");
            $max = +$time->format(self::UNITS[$u]) + 1;
            $time->modify(($max + 1 + $unit->from) . " $u");
        }
//        debug("Cron($this->id) time reset to $unit->from $u resulting in " . $time->format('H:i:s'));
        return $time;
    }

    public function __toString(): string
    {
        return $this->id;
    }

    public function __invoke()
    {
        return ($this->callback)();
    }
}
