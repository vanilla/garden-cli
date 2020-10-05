<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\App;

class Identifier {
    private $parts = [];

    public function __construct(string ...$parts) {
        $this->parts = $parts;
    }

    /**
     * @return string[]
     */
    public function getParts(): array {
        return $this->parts;
    }

    public static function fromCamel(string $name) {
        $parts = preg_split('`(?<=[a-z])(?=[A-Z0-9])`x', $name);
        $parts = array_map('strtolower', $parts);
        return new self(...$parts);
    }

    public function toCamel(): string {
        $parts = array_map('ucfirst', $this->parts);
        return lcfirst(implode('', $parts));
    }

    public static function fromPascal(string $name) {
        return static::fromCamel($name);
    }

    public function toPascal(): string {
        $parts = array_map('ucfirst', $this->parts);
        return implode('', $parts);
    }

    public static function fromSnake(string $name): Identifier {
        $parts = explode('_', $name);
        $parts = array_map('strtolower', $parts);
        return new self(...$parts);
    }

    public function toSnake(): string {
        return implode('_', $this->parts);
    }

    public static function fromKebab(string $name): Identifier {
        $parts = explode('-', $name);
        $parts = array_map('strtolower', $parts);
        return new self(...$parts);
    }

    public function toKebab(): string {
        return implode('-', $this->parts);
    }

    public static function fromMixed(string $name) {
        if (strpos($name, '_')) {
            return self::fromSnake($name);
        } elseif (strpos($name, '-')) {
            return self::fromKebab($name);
        } else {
            return self::fromCamel($name);
        }
    }
}
