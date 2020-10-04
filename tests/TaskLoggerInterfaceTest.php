<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests;

use Garden\Cli\TaskLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class TaskLoggerInterfaceTest extends StreamLoggerInterfaceTest {

    protected $taskLogger;

    public function setUp(): void {
        parent::setUp();

        $this->taskLogger = new TaskLogger($this->logger);
        $this->taskLogger->setMinLevel(LogLevel::DEBUG);
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger() {
        return $this->taskLogger;
    }
}
