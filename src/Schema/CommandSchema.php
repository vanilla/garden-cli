<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Schema;

use Garden\Cli\Cli;

/**
 * A data class for the information describing a command line command or subcommand.
 */
class CommandSchema {
    use MetaTrait;

    /**
     * @var OptSchema[]
     */
    private $opts;

    /**
     * CommandSchema constructor.
     *
     * @param OptSchema[] $schema
     */
    public function __construct(array $schema = []) {
        if (!is_array($meta = $schema[Cli::META] ?? [])) {
            throw new \InvalidArgumentException("The meta must be an array.", 400);
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
        return $this->meta['description'] ?? '';
    }

    /**
     * Get the command's args.
     *
     * @return array|mixed
     */
    public function getArgs() {
        return $this->meta[Cli::ARGS] ?? [];
    }

    /**
     * Whether or not the command has args.
     *
     * @return bool
     */
    public function hasArgs() {
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
     * Whether or not the command has an opt.
     *
     * @param string $name The long name of the opt.
     * @return bool
     */
    public function hasOpt(string $name): bool {
        return isset($this->opts[$name]);
    }

    /**
     * Whether or not the command has any opts.
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
    public function mergeSchema(CommandSchema $schema) {
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
    public function addOpt(OptSchema $opt) {
        $this->opts[$opt->getName()] = $opt;
        return $this;
    }
}
