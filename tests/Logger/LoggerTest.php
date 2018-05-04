<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests\Logger;

use Garden\Cli\Logger\Logger;
use Garden\Cli\Tests\CliTestCase;
use Garden\Cli\Tests\Fixtures\Logger\Writer\TestWriter;

/**
 * Includes tests for the {@link \Garden\Cli\Logger\Logger} class.
 */
class LoggerTest extends CliTestCase {

    /**
     * @var Logger An instantiated logger.
     */
    protected $logger;

    /**
     * @var TestWriter An instantiated writer.
     */
    protected $writer;

    protected function setUp() {
        parent::setUp();
        $this->writer = new TestWriter;
        $this->logger = new Logger;
        $this->logger->addWriter($this->writer);
    }

    public function testSetMaxLevelWithSubZeroValueThrowsException() {
        $this->expectException(\InvalidArgumentException::class);
        $this->logger->setMaxLevel(-1);
    }

    public function testSetGetMaxLevel() {
        $this->assertEquals(2, $this->logger->getMaxLevel()); // default value
        $this->logger->setMaxLevel(5);
        $this->assertEquals(5, $this->logger->getMaxLevel());
    }

    public function testBeginReturnsThis() {
        $result = $this->logger->begin('begin');
        $this->assertEquals($this->logger, $result);
    }

    public function testBeginTriggersWrite() {
        $this->logger->begin('begin');
        $this->assertCount(1, $this->writer->messages);
        $this->assertEquals('info', $this->writer->messages[0][1]); // log level
        $this->assertEquals('begin', $this->writer->messages[0][2]); // message
    }

    public function testEndCalledWithoutMatchingBeginTriggersError() {
        $this->logger->end('end');
        $this->assertErrorNumber(E_USER_NOTICE);
    }

    public function testEndTriggersWrite() {
        $this->logger->begin('begin')->end('end');
        $this->assertCount(2, $this->writer->messages);
        $this->assertEquals('info', $this->writer->messages[1][1]); // log level
        $this->assertEquals('begin end', $this->writer->messages[1][2]);
    }

    public function testErrorTriggersWrite() {
        $this->logger->error('ERR');
        $this->assertEquals('error', $this->writer->messages[0][1]); // log level
        $this->assertEquals('ERR', $this->writer->messages[0][2]); // message
    }

    public function testSuccessTriggersWrite() {
        $this->logger->success('SUCCESS');
        $this->assertEquals('success', $this->writer->messages[0][1]); // log level
        $this->assertEquals('SUCCESS', $this->writer->messages[0][2]); // message
    }

    public function testWarnTriggersWrite() {
        $this->logger->warn('WARN');
        $this->assertEquals('warning', $this->writer->messages[0][1]); // log level
        $this->assertEquals('WARN', $this->writer->messages[0][2]); // message
    }

    public function testMessageTriggersWrite() {
        $this->logger->message('INFO');
        $this->assertEquals('info', $this->writer->messages[0][1]); // log level
        $this->assertEquals('INFO', $this->writer->messages[0][2]); // message
    }

    public function testEndSuccessTriggersWrites() {
        $this->logger->begin('begin')->warn('warn')->endSuccess('end');

        // begin
        $this->assertEquals('info', $this->writer->messages[0][1]); // log level
        $this->assertEquals('begin', $this->writer->messages[0][2]); // message
        $this->assertEquals(1, $this->writer->messages[0][3]); // indent level

        // warn
        $this->assertEquals('warning', $this->writer->messages[1][1]); // log level
        $this->assertEquals('warn', $this->writer->messages[1][2]); // message
        $this->assertEquals(1, $this->writer->messages[1][3]); // indent level

        // end
        $this->assertEquals('success', $this->writer->messages[2][1]); // log level
        $this->assertEquals('begin end', $this->writer->messages[2][2]); // message
        $this->assertEquals(1, $this->writer->messages[2][3]); // indent level
    }

    public function testEndErrorTriggersWrites() {
        $this->logger->begin('begin')->warn('warn')->endError('end');

        // begin
        $this->assertEquals('info', $this->writer->messages[0][1]); // log level
        $this->assertEquals('begin', $this->writer->messages[0][2]); // message
        $this->assertEquals(1, $this->writer->messages[0][3]); // indent level

        // warn
        $this->assertEquals('warning', $this->writer->messages[1][1]); // log level
        $this->assertEquals('warn', $this->writer->messages[1][2]); // message
        $this->assertEquals(1, $this->writer->messages[1][3]); // indent level

        // end
        $this->assertEquals('error', $this->writer->messages[2][1]); // log level
        $this->assertEquals('begin end', $this->writer->messages[2][2]); // message
        $this->assertEquals(1, $this->writer->messages[2][3]); // indent level
    }

    public function testEndHttpStatusTriggersInfoWrite() {
        $this->logger->begin('begin')->endHttpStatus(100);

        $this->assertEquals('info', $this->writer->messages[1][1]); // log level
        $this->assertEquals('begin 100', $this->writer->messages[1][2]); // message
    }

    public function testEndHttpStatusTriggersSuccessWrite() {
        $this->logger->begin('begin')->endHttpStatus(200);

        $this->assertEquals('success', $this->writer->messages[1][1]); // log level
        $this->assertEquals('begin 200', $this->writer->messages[1][2]); // message
    }

    public function testEndHttpStatusTriggersErrorWrite() {
        $this->logger->begin('begin')->endHttpStatus(404);

        $this->assertEquals('error', $this->writer->messages[1][1]); // log level
        $this->assertEquals('begin 404', $this->writer->messages[1][2]); // message
    }

    public function testMultipleNestedMessages() {
        $this->logger
            ->message('start')      // 0
            ->begin('lvl 1')        // 1
            ->message('one a')      // 1
            ->begin('lvl 2')        // 2
            ->message('two a')      // 2
            ->begin('lvl 3')        // not output, beyond max level
            ->message('three a')    // not output, beyond max level
            ->begin('lvl 4')        // not output, beyond max level
            ->message('four a')     // not output, beyond max level
            ->end('end 4')          // not output, beyond max level
            ->message('four b')     // not output, beyond max level
            ->end('end 3')          // not output, beyond max level
            ->message('three b')    // 2
            ->end('end 2')          // 2
            ->message('two b')      // 1
            ->end('end 1')          // 1
            ->message('end');       // 0


        $expectedIndentLevels = [0, 1, 1, 2, 2, 2, 1, 1, 1, 0];
        $actualIndentLevels = array_column($this->writer->messages, 3);
        $this->assertEquals($expectedIndentLevels, $actualIndentLevels);
    }

    public function testMultipleNestedMessagesWithForce() {
        $this->logger
            ->message('start')      // 0
            ->begin('lvl 1')        // 1
            ->message('one a')      // 1
            ->begin('lvl 2')        // 2
            ->message('two a')      // 2
            ->begin('lvl 3')        // 3 - normally would not show, but is forced
            ->message('three a', 1) // 3 - normally would not show, but is forced
            ->begin('lvl 4')        // not output, beyond max level
            ->message('four a')     // not output, beyond max level
            ->end('end 4')          // not output, beyond max level
            ->message('four b')     // not output, beyond max level
            ->end('end 3')          // not output, beyond max level
            ->message('three b')    // 2
            ->end('end 2')          // 1
            ->message('two b')      // 1
            ->end('end 1')          // 1
            ->message('end');       // 0


        $expectedIndentLevels = [0, 1, 1, 2, 2, 3, 3, 2, 1, 1, 1, 0];
        $actualIndentLevels = array_column($this->writer->messages, 3);
        $this->assertEquals($expectedIndentLevels, $actualIndentLevels);
    }
}
