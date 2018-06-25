<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests\Fixtures;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

class TestLogger implements LoggerInterface {
    use LoggerTrait;

    public $log = [];


    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array()) {
        $this->log[] = [$level, $message, $context];
    }
}
