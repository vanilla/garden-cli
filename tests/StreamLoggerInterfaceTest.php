<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests;

use Garden\Cli\StreamLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\Test\LoggerInterfaceTest;

class StreamLoggerInterfaceTest extends LoggerInterfaceTest {
    /**
     * @var StreamLogger
     */
    protected $logger;

    protected $stream;

    public function setUp(): void {
        $this->stream = fopen('php://memory', 'w+');

        $this->logger = new StreamLogger($this->stream);
        $this->logger
            ->setLineFormat('{level} {message}')
            ->setShowDurations(false);

        parent::setUp();
    }

    public function tearDown(): void {
        parent::tearDown();
        fclose($this->stream);
    }


    /**
     * @return LoggerInterface
     */
    public function getLogger() {
        return $this->logger;
    }

    /**
     * This must return the log messages in order.
     *
     * The simple formatting of the messages is: "<LOG LEVEL> <MESSAGE>".
     *
     * Example ->error('Foo') would yield "error Foo".
     *
     * @return string[]
     */
    public function getLogs() {
        $pos = ftell($this->stream);

        rewind($this->stream);
        $str = trim(stream_get_contents($this->stream));
        $logs = empty($str) ? [] : explode($this->logger->getEol(), $str);
        fseek($this->stream, $pos, SEEK_SET);

        return $logs;
    }
}
