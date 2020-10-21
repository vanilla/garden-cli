<?php
/**
 * @author Adam Charron <adam@charrondev.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Application;

/**
 * A placeholder for an arg in CliApplication call.
 */
class CliApplicationArg {

    /** @var string */
    private $arg;

    /**
     * Constructor.
     *
     * @param string $arg
     */
    public function __construct(string $arg) {
        $this->arg = $arg;
    }

    /**
     * @return string
     */
    public function getArg(): string {
        return $this->arg;
    }
}
