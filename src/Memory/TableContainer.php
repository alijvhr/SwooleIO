<?php

namespace SwooleIO\Memory;

use SwooleIO\Exceptions\DuplicateTableNameException;

class TableContainer implements \Iterator, \Countable
{

    /**
     * @var Table[] $tables
     */
    protected array $tables;

    /**
     * @throws DuplicateTableNameException
     */
    public function __construct(array $structure = [])
    {
        foreach ($structure as $name => [$columns, $size]){
            if(!isset($size)) $size = Table::DefaultSize;
            $this->create($name, $columns, $size);
        }
    }

    /**
     * @return Table[]
     */
    public function all(): array
    {
        return $this->tables;
    }

    public function get(string $name): ?Table
    {
        return $this->tables[$name] ?? null;
    }

    /**
     * @param string $name
     * @return bool
     */

    public function del(string $name): Table
    {
        $table = $this->tables[$name];
        unset($this->tables[$name]);
        return $table;
    }

    /**
     * @param string $name
     * @param array<string, string> $columns
     * @param int $size
     * @return Table|null
     * @throws DuplicateTableNameException
     */
    public function create(string $name, array $columns, int $size = Table::DefaultSize): ?Table
    {
        $table = new Table($name, $columns, $size);
        $this->add($table);
        return $table;
    }

    /**
     * @param Table $table
     * @return bool
     * @throws DuplicateTableNameException
     */

    public function add(Table $table): bool
    {
        $name = $table->name();
        if (isset($this->tables[$name]))
            throw new DuplicateTableNameException();
        $this->tables[$name] = $table;
        return true;
    }

    public function destroy(string $name): bool
    {
        return $this->del($name)->destroy();
    }

    public function current(): Table
    {
        return current($this->tables);
    }

    public function next(): void
    {
        next($this->tables);
    }

    public function key(): string
    {
        return key($this->tables);
    }

    public function valid(): bool
    {
        return key($this->tables) !== null;
    }

    public function rewind(): void
    {
        prev($this->tables);
    }

    public function count(): int
    {
        return count($this->tables);
    }
}