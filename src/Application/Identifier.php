<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Application;

/**
 * A utility data object for converting identifiers between different casing schemes.
 */
class Identifier
{
    private array $parts = [];

    /**
     * Identifier constructor.
     *
     * @param string[] $parts The words in the identifier.
     */
    public function __construct(string ...$parts)
    {
        $this->parts = $parts;
    }

    /**
     * Get the words in the Identifier.
     *
     * @return string[]
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    /**
     * Create an Identifier from a camelCase name.
     *
     * @param string $name
     * @return Identifier
     */
    public static function fromCamel(string $name): Identifier
    {
        $parts = preg_split("`(?<=[a-z])(?=[A-Z0-9])`x", $name);
        $parts = array_map("strtolower", $parts);
        return new self(...$parts);
    }

    /**
     * Convert this Identifier to camelCase.
     *
     * @return string
     */
    public function toCamel(): string
    {
        $parts = array_map("ucfirst", $this->parts);
        return lcfirst(implode("", $parts));
    }

    /**
     * Create an Identifier from a PascalCase string.
     *
     * @param string $name
     * @return Identifier
     */
    public static function fromPascal(string $name): Identifier
    {
        return static::fromCamel($name);
    }

    /**
     * Convert this Identifier to PascalCase.
     *
     * @return string
     */
    public function toPascal(): string
    {
        $parts = array_map("ucfirst", $this->parts);
        return implode("", $parts);
    }

    /**
     * Create an Identifier from a snake_case string.
     *
     * @param string $name
     * @return Identifier
     */
    public static function fromSnake(string $name): Identifier
    {
        $parts = explode("_", $name);
        $parts = array_map("strtolower", $parts);
        return new self(...$parts);
    }

    /**
     * Convert this Identifier to snake_case.
     *
     * @return string
     */
    public function toSnake(): string
    {
        return implode("_", $this->parts);
    }

    /**
     * Create an Identifer from a kebab-case string.
     *
     * @param string $name
     * @return Identifier
     */
    public static function fromKebab(string $name): Identifier
    {
        $parts = explode("-", $name);
        $parts = array_map("strtolower", $parts);
        return new self(...$parts);
    }

    /**
     * Convert this Identifier to kebab-case.
     *
     * @return string
     */
    public function toKebab(): string
    {
        return implode("-", $this->parts);
    }

    /**
     * Create an Identifier from a string that is one of the cases supported in this class.
     *
     * @param string $name A camelCase, PascalCase, snake_case, or kebab-case string.
     * @return Identifier
     */
    public static function fromMixed(string $name): Identifier
    {
        if (strpos($name, "_")) {
            return static::fromSnake($name);
        } elseif (strpos($name, "-")) {
            return static::fromKebab($name);
        } else {
            return static::fromCamel($name);
        }
    }

    /**
     * Create an Identifier from a class, using it's basename.
     *
     * @param string|object $class A class name or instance.
     * @return Identifier
     */
    public static function fromClassBasename($class): Identifier
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        if (($i = strrpos($class, "\\")) !== false) {
            $basename = substr($class, $i + 1);
        } elseif (($i = strrpos($class, "_")) !== false) {
            $basename = substr($class, $i + 1);
        } else {
            $basename = $class;
        }
        return static::fromMixed($basename);
    }
}
