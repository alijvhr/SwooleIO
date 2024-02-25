<?php

namespace unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SwooleIO\Memory\Table;

class TableTest extends TestCase
{
    public static function tableProvider(): array
    {
        return [
            ['fd', ['fd'=>'int'], 0],
            [0, 1, 1],
            [1, 0, 1],
            [1, 1, 3],
        ];
    }

    #[DataProvider('tableProvider')]
    #[Test]
    public function testTableCreate(string $name, array $columns, int $size): void
    {

        $this->markTestIncomplete('This test is incomplete');
        $greeter = new Table();

        $greeting = $greeter->greet('Alice');

        $this->assertSame('Hello, Alice!', $greeting);

    }
}