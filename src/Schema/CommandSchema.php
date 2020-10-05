<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Schema;


use Garden\Cli\Cli;

class CommandSchema {
    use MetaTrait;

    /**
     * @var OptSchema[]
     */
    private $opts;

    public function __construct(array $schema = []) {
        $this->meta = $schema[Cli::META] ?? [];
        unset($schema[Cli::META]);
        ksort($schema);
        $this->opts = $schema;
    }

    public function getDescription(): string {
        return $this->meta['description'] ?? '';
    }

    public function getArgs() {
        return $this->meta[Cli::ARGS] ?? [];
    }

    public function hasArgs() {
        return !empty($this->meta[Cli::ARGS]);
    }

    /**
     * @return OptSchema[]
     */
    public function getOpts(): array {
        return $this->opts;
    }

    public function getOpt(string $name): ?OptSchema {
        return $this->opts[$name] ?? null;
    }

    public function hasOpt(string $name): bool {
        return isset($this->opts[$name]);
    }

    /**
     * @return bool
     */
    public function hasOpts(): bool {
        return !empty($this->opts);
    }

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
