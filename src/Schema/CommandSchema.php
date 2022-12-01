<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Schema;

use Garden\Cli\Cli;
use InvalidArgumentException;

/**
 * A data class for the information describing a command line command or subcommand.
 */
class CommandSchema {
    use MetaTrait;

    /**
     * @var OptSchema[]
     */
    private array $opts;

    /**
     * CommandSchema constructor.
     *
     * @param OptSchema[] $schema
     */
    public function __construct(array $schema = []) {
        if (!is_array($meta = $schema[Cli::META] ?? [])) {
            throw new InvalidArgumentException("The meta must be an array.", 400);
        }
        $this->meta = $meta;
        unset($schema[Cli::META]);
        ksort($schema);
        $this->opts = $schema;
    }

    /**
     * Get the command's description.
     *
     * @return string
     */
    public function getDescription(): string {
        $description = $this->meta['description'] ?? '';
        // The OptSchema::merge() function may create an array of strings
        // if a "main"/"global" description is supplied as well as a description
        // for individual commands. If this happens we return the last
        // of the descriptions.
        return is_array($description) ? $description[count($description)-1] : $description;
    }

    /**
     * Get the command's args.
     *
     * @return array|mixed
     */
    public function getArgs(): mixed
    {
        return $this->meta[Cli::ARGS] ?? [];
    }

    /**
     * Whether the command has args.
     *
     * @return bool
     */
    public function hasArgs(): bool
    {
        return !empty($this->meta[Cli::ARGS]);
    }

    /**
     * Get the opts for the command.
     *
     * @return OptSchema[]
     */
    public function getOpts(): array {
        return $this->opts;
    }

    /**
     * Get an opt by long name.
     *
     * @param string $name
     * @return OptSchema|null
     */
    public function getOpt(string $name): ?OptSchema {
        return $this->opts[$name] ?? null;
    }

    /**
     * Whether the command has an opt.
     *
     * @param string $name The long name of the opt.
     * @return bool
     */
    public function hasOpt(string $name): bool {
        return isset($this->opts[$name]);
    }

    /**
     * Whether the command has any opts.
     *
     * @return bool
     */
    public function hasOpts(): bool {
        return !empty($this->opts);
    }

    /**
     * Merge another command schema into this one.
     *
     * @param CommandSchema $schema
     */
    public function mergeSchema(CommandSchema $schema): void {
        $this->mergeMetaArray($schema->getMetaArray());

        /**
         * @var string $name
         * @var OptSchema $opt
         */
        foreach ($schema->getOpts() as $name => $opt) {
            if (!isset($this->opts[$name])) {
                $this->opts[$name] = $opt;
            } else {
                $this->opts[$name]->merge($opt);
            }
        }
    }

    /**
     * Add an option to the schema.
     *
     * @param OptSchema $opt
     * @return $this
     */
    public function addOpt(OptSchema $opt): static {
        $this->opts[$opt->getName()] = $opt;
        return $this;
    }
}
