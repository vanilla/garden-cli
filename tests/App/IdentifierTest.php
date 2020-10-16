<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests\App;

use Garden\Cli\Application\Identifier;
use PHPUnit\Framework\TestCase;

class IdentifierTest extends TestCase {
    /**
     * @param array $parts
     * @dataProvider provideIdentifiers
     */
    public function testFrom(string $camel, string $pascal, string $snake, string $kebab): void {
        $camelID = Identifier::fromCamel($camel);
        $pascalID = Identifier::fromPascal($pascal);
        $snakeID = Identifier::fromSnake($snake);
        $kebabID = Identifier::fromKebab($kebab);

        $this->assertSame($camelID->getParts(), $pascalID->getParts(), "camel !== pascal");
        $this->assertSame($pascalID->getParts(), $snakeID->getParts(), "pascal !== snake");
        $this->assertSame($snakeID->getParts(), $kebabID->getParts(), "snake !== kebab");
    }

    /**
     * @param array $parts
     * @dataProvider provideIdentifiers
     */
    public function testTo(string $camel, string $pascal, string $snake, string $kebab): void {
        $id = Identifier::fromCamel($camel);

        $this->assertSame($camel, $id->toCamel());
        $this->assertSame($pascal, $id->toPascal());
        $this->assertSame($snake, $id->toSnake());
        $this->assertSame($kebab, $id->toKebab());
    }

    public function provideIdentifiers(): array {
        $r = [
            ['one', 'One', 'one', 'one'],
            ['addFoo', 'AddFoo', 'add_foo', 'add-foo'],
            ['addFoo10', 'AddFoo10', 'add_foo_10', 'add-foo-10'],
        ];

        return array_column($r, null, 0);
    }
}
