<?php

namespace SwooleIO\Memory;

use JetBrains\PhpStorm\ArrayShape;
use OpenSwoole\Table as sTable;

class Table implements \Iterator, \Countable
{

    const Types = [
        'int' => [sTable::TYPE_INT, 8],
        'float' => [sTable::TYPE_FLOAT, 0],
        'char' => [sTable::TYPE_STRING, 16],
        'str-s' => [sTable::TYPE_STRING, 64],
        'str' => [sTable::TYPE_STRING, 256],
        'str-b' => [sTable::TYPE_STRING, 1024],
        'text' => [sTable::TYPE_STRING, 8192],
        'arr-2' => [sTable::TYPE_STRING, 2048],
        'arr-4' => [sTable::TYPE_STRING, 2048],
        'arr' => [sTable::TYPE_STRING, 2048],
        'list' => [sTable::TYPE_STRING, 4096],
        'json' => [sTable::TYPE_STRING, 8192],
        'phps' => [sTable::TYPE_STRING, 8192],
    ];

    const Castables = ['arr-2', 'arr-4', 'arr', 'json', 'list', 'phps'];

    const DefaultSize = 1000;
    public int $size;
    public int $memorySize;
    protected sTable $table;

    protected array $columns;
    protected array $casts = [];
    protected string $name;

    /**
     * @param string $name
     * @param array<string,string> $columns
     * @param int $size
     */

    public function __construct(string $name, array $columns, int $size = self::DefaultSize)
    {
        $this->name = $name;
        $this->columns = $columns;
        $table = $this->table = new sTable($size);
        foreach ($columns as $col => $type) {
            if (in_array($type, self::Castables))
                $this->casts[$col] = $type;
            $type = self::Types[$type] ?? self::Types['int'];
            $table->column($col, $type[0], $type[1]);
        }
        $table->column('_ttl', sTable::TYPE_INT, 8);
        $table->create();

        $this->size = &$this->table->size;
        $this->memorySize = &$this->table->memorySize;
    }

    /**
     * @param string $name
     * @param array<string,string> $columns
     * @param int $size
     * @return Table
     */

    public static function create(string $name, array $columns, int $size = self::DefaultSize): Table
    {
        return new self($name, $columns, $size);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function destroy(): bool
    {
        return $this->table->destroy();
    }

    public function ttl(string $key, int $ttl): bool
    {
        return $this->table->set($key, ['_ttl' => $ttl == 0 ? 0 : time() + $ttl]);
    }

    public function set(string $key, array $row, ?int $ttl = null): bool
    {
        foreach ($this->casts as $col => $type)
            if (isset($row[$col])) $row[$col] = $this->castFrom($row[$col], $this->casts[$col]);
        if (isset($ttl)) $row['_ttl'] = $ttl == 0 ? 0 : time() + $ttl;
        return $this->table->set($key, $row);
    }

    protected function castFrom(array $data, string $type): string
    {
        return match ($type) {
            'json' => json_encode($data),
            'phps' => serialize($data),
            'arr-2' => pack('s*', ...$data),
            'arr-4' => pack('l*', ...$data),
            'arr' => pack('q*', ...$data),
            'list' => implode('|', $data),
        };
    }

    public function GC(): int
    {
        $expired = 0;
        foreach ($this->table as $key => $row)
            if ($row['_ttl'] < time()) $expired += $this->table->del($key);
        return $expired;
    }

    public function del(string $key): bool
    {
        return $this->table->del($key);
    }

    public function sizeOf(string $key, string $column): int
    {
        $data = $this->rawCol($key, $column);
        return strlen($data / $this->castSize($column));
    }

    protected function rawCol(string $key, string $column): false|string
    {
        if ($this->expired($key)) return false;
        return $this->table->get($key, $column);
    }

    public function expired(string $key): bool
    {
        $ttl = $this->table->get($key, '_ttl');
        if ($ttl && $ttl < time()) {
            $this->table->del($key);
            $ttl = false;
        }
        return $ttl === false;
    }

    public function get(string $key, string $column = ''): mixed
    {
        if ($this->expired($key)) return null;
        if ($column) {
            $data = $this->table->get($key, $column);
            return isset($this->casts[$column]) ? $this->castTo($data, $this->casts[$column]) : $data;
        } else {
            $data = $this->table->get($key);
            foreach ($this->casts as $col => $type)
                $data[$col] = $this->castTo($data[$col], $this->casts[$col]);
            return $data;
        }
    }

    protected function castTo(string $data, ?string $type): mixed
    {
        return match ($type) {
            'json' => json_decode($data),
            'phps' => unserialize($data),
            'arr-2' => unpack('s*', $data, 2),
            'arr-4' => unpack('l*', $data, 2),
            'arr' => unpack('q*', $data, 2),
            'list' => explode('|', $data),
            default => $data
        };
    }

    protected function castSize(string $type): int
    {
        return match ($type) {
            'arr-2' => 2,
            'arr-4' => 4,
            'arr' => 8,
            default => 0
        };
    }

    /**
     * @param string $key
     * @param string $column
     * @param int|string $value
     * @return int|string
     * @throws WrongTypeColumn
     */
    public function push(string $key, string $column, int|string $value): int|string
    {
        return $this->update($key, $column, fn($data, $size, $type) => match ($type) {
            'list' => ["$data|$value", substr_count($data, '|') + 1, $value],
            'arr', 'arr-2', 'arr-4' => [$data . $this->castFrom([$value], $type), strlen($data) / $size + 1, $value],
            'json', 'phps' => $this->push_json($data, $value),
        })[1];
    }

    /**
     * @param string $key
     * @param string $column
     * @param callable $func
     * @return array
     * @throws WrongTypeColumn
     */
    #[ArrayShape(['string', 'int', 'string', 'string'])]
    protected function update(string $key, string $column, callable $func): array
    {
        $type = $this->columns[$column];
        if (!preg_match('/^(arr|list|json|phps)/', $type)) throw new WrongTypeColumn();
        $size = $this->castSize($type);
        $data = $this->rawCol($key, $column);
        [$data, $count, $item] = $func($data, $size, $type);
        $this->table->set($key, [$column => $data]);
        return [$data, $count, $type, $item];
    }

    /**
     * @param string $key
     * @param string $column
     * @param string $value
     * @return string
     * @throws WrongTypeColumn
     */
    public function append(string $key, string $column, string $value): string
    {
        $type = $this->columns[$column];
        if (!preg_match('/^(str|text)/', $type)) throw new WrongTypeColumn();
        $data = $this->rawCol($key, $column) . $value;
        $this->table->set($key, [$column => $data]);
        return $data;
    }

    /**
     * @param string $key
     * @param string $column
     * @return int|string
     * @throws WrongTypeColumn
     */
    public function pop(string $key, string $column): int|string
    {
        return $this->update($key, $column, fn($data, $size, $type) => match ($type) {
            'arr', 'arr-2', 'arr-4' => [substr($data, 0, -$size), strlen($data) / $size - 1, substr($data, -$size)],
            'json', 'list' => $this->pop_json($data),
        })[3];
    }

    public function exists(string $key): bool
    {
        return $this->expired($key);
    }

    public function incr(string $key, string $column, int $incrBy = 1): int
    {
        return $this->table->incr($key, $column, $incrBy);
    }

    public function decr(string $key, string $column, int $decrBy = 1): int
    {
        return $this->table->decr($key, $column, $decrBy);
    }

    public function getSize(): int
    {
        return $this->table->getSize();
    }

    public function getMemorySize(): int
    {
        return $this->table->getMemorySize();
    }

    public function current(): ?array
    {
        return $this->table->current();
    }

    public function next(): void
    {
        $this->table->next();
    }

    public function key(): ?string
    {
        return $this->table->key();
    }

    public function valid(): bool
    {
        return $this->table->valid();
    }

    public function rewind(): void
    {
        $this->table->rewind();
    }

    public function count(): int
    {
        return $this->table->count();
    }

    #[ArrayShape(['string', 'string', 'int'])]
    protected function pop_list($data): array
    {
        $item = substr($data, strrpos($data, '|') + 1);
        return [substr($data, 0, -strlen($item)), substr_count($data, '|') - 1, $item];
    }

    #[ArrayShape(['string', 'string', 'int'])]
    protected function pop_json($data): array
    {
        $data = $this->castTo($data, 'json');
        $item = array_pop($data);
        return [$this->castFrom($data, 'json'), count($data), $item];
    }

    #[ArrayShape(['string', 'string', 'int'])]
    protected function push_json(string $data, string $item): array
    {
        $data = $this->castTo($data, 'json');
        $data[] = $item;
        return [$this->castFrom($data, 'json'), count($data), $item];
    }
}