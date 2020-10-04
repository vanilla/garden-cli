<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests;

use Garden\Cli\TaskLogger;
use Garden\Cli\Tests\Fixtures\TestLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

class TaskLoggerTest extends AbstractCliTest {
    /**
     * @var TestLogger
     */
    protected $testLogger;

    /**
     * @var TaskLogger
     */
    protected $log;

    /**
     * Create a new logger for each test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->testLogger = new TestLogger();
        $this->log = new TaskLogger($this->testLogger);
    }

    /**
     * Test a basic call to the log.
     */
    public function testBasicLog() {
        $this->log->log(LogLevel::INFO, 'foo', ['foo' => 'bar']);

        $this->assertLogLevel(LogLevel::INFO);
        $this->assertLogMessage('foo');
        $this->assertLogHasContext([TaskLogger::FIELD_INDENT => 0, 'foo' => 'bar']);
    }

    /**
     * I should be able to override the time with the context.
     */
    public function testTimeOverride() {
        $this->log->log(LogLevel::INFO, 'foo', [TaskLogger::FIELD_TIME => 1]);
        $this->assertLogHasContext([TaskLogger::FIELD_TIME => 1]);

        $this->log->begin(LogLevel::INFO, 'foo', [TaskLogger::FIELD_TIME => 2]);
        $this->assertLogHasContext([TaskLogger::FIELD_TIME => 2]);

        $this->log->end('foo', [TaskLogger::FIELD_TIME => 3]);
        $this->assertLogHasContext([TaskLogger::FIELD_TIME => 3]);
    }

    /**
     * A level under the min level should not be logged.
     */
    public function testUnderMinLevel() {
        $this->log->log(LogLevel::DEBUG, 'foo');
        $this->assertLogCount(0);
    }

    /**
     * Test an indent with begin/end.
     */
    public function testSimpleIndent() {
        $this->log->beginNotice('a');
        $this->log->info('b');
        $this->log->end('c');

        $this->assertLogShape([
            [LogLevel::NOTICE, 'a', [TaskLogger::FIELD_INDENT => 0]],
            [LogLevel::INFO, 'b', [TaskLogger::FIELD_INDENT => 1]],
            [LogLevel::NOTICE, 'c', [TaskLogger::FIELD_INDENT => 0]],
        ]);
    }

    /**
     * The end log should be the max level of any item.
     */
    public function testEndLevelIncrease() {
        $this->log->beginInfo('a');
        $this->log->error('b');
        $this->log->end('c');

        $this->assertLogShape([
            [LogLevel::INFO, 'a', [TaskLogger::FIELD_INDENT => 0]],
            [LogLevel::ERROR, 'b', [TaskLogger::FIELD_INDENT => 1]],
            [LogLevel::ERROR, 'c', [TaskLogger::FIELD_INDENT => 0]],
        ]);
    }

    /**
     * The special **endHttpStatus()** method should log expected levels.
     *
     * @param int $status The HTTP status to test.
     * @param string $level The expected log level or an empty string.
     * @dataProvider provideHttpStatusTests
     */
    public function testEndHttpStatus(int $status, string $level) {
        $this->log->begin(LogLevel::NOTICE, 'http');

        $this->log->endHttpStatus($status);
        $this->assertLogLevel($level ?: LogLevel::NOTICE);
        $this->assertLogMessage(sprintf('%03d', $status));
    }

    /**
     * Provide HTTP statuses and expected log levels.
     *
     * @return array Returns a data provider array.
     */
    public function provideHttpStatusTests() {
        $r = [
            [200, ''],
            [400, LogLevel::ERROR],
            [500, LogLevel::CRITICAL],
            [0, LogLevel::CRITICAL],
        ];

        return array_column($r, null, 0);
    }

    /**
     * Beginning a task below the min should still output.
     */
    public function testBeginBelowMinLevel() {
        $this->log->beginDebug('a');
        $this->log->notice('b1');
        $this->log->critical('b2');
        $this->log->end('c');

        $this->assertLogShape([
            [LogLevel::DEBUG, 'a', [TaskLogger::FIELD_INDENT => 0]],
            [LogLevel::NOTICE, 'b1', [TaskLogger::FIELD_INDENT => 1]],
            [LogLevel::CRITICAL, 'b2', [TaskLogger::FIELD_INDENT => 1]],
            [LogLevel::CRITICAL, 'c', [TaskLogger::FIELD_INDENT => 0]],
        ]);
    }

    /**
     * Test nesting tasks with printing promotion.
     */
    public function testNested() {
        $this->log->beginDebug('a');
        $this->log->beginDebug('aa');
        $this->log->debug('aaa');
        $this->log->alert('aab');
        $this->log->end('b');
        $this->log->end('c');

        $this->assertLogShape([
            [LogLevel::DEBUG, 'a', [TaskLogger::FIELD_INDENT => 0, TaskLogger::FIELD_BEGIN => true]],
            [LogLevel::DEBUG, 'aa', [TaskLogger::FIELD_INDENT => 1, TaskLogger::FIELD_BEGIN => true]],
            [LogLevel::ALERT, 'aab', [TaskLogger::FIELD_INDENT => 2]],
            [LogLevel::ALERT, 'b', [TaskLogger::FIELD_INDENT => 1, TaskLogger::FIELD_END => true]],
            [LogLevel::ALERT, 'c', [TaskLogger::FIELD_INDENT => 0, TaskLogger::FIELD_END => true]],
        ]);
    }

    /**
     * Logging an end with no begin should work, with exceptions.
     */
    public function testEndNoBegin() {
        @$this->log->end('foo');
        $this->assertLogLevel(LogLevel::INFO);
        $this->assertLogHasContext([TaskLogger::FIELD_END => true]);
        $this->assertLogMessage('foo');

        $this->expectNotice();
        $this->log->end('foo');
    }

    /**
     */
    public function testInvalidLevelBegin() {
        $this->expectException(InvalidArgumentException::class);
        $this->log->begin('invalid', 'a');
    }

    /**
     *
     */
    public function testInvalidLevelEnd() {
        $this->expectNotice();
        $this->log->end('a', [TaskLogger::FIELD_LEVEL => 'invalid']);
    }

    /**
     * The task logger should have specific **begin*()** methods to begin a specific level.
     *
     * @param string $level The log level to test.
     * @dataProvider provideLogLevels
     */
    public function testSpecificBegins(string $level) {
        $this->log->setMinLevel($level);

        $method = "begin{$level}";
        call_user_func([$this->log, $method], 'foo');
        $this->assertLogLevel($level);
        $this->assertLogMessage('foo');
    }

    /**
     * Provide PSR log levels for various tests.
     *
     * @return array Returns a data provider array.
     */
    public function provideLogLevels() {
        $r = [
            [LogLevel::DEBUG],
            [LogLevel::INFO],
            [LogLevel::NOTICE],
            [LogLevel::WARNING],
            [LogLevel::ERROR],
            [LogLevel::CRITICAL],
            [LogLevel::ALERT],
            [LogLevel::EMERGENCY],
        ];

        return array_column($r, null, 0);
    }

    /**
     * Assert that the log has a number of items in it.
     *
     * @param int $count The expected count.
     */
    protected function assertLogCount(int $count) {
        $this->assertCount($count, $this->testLogger->log);
    }

    /**
     * Assert that the last log entry has a given context.
     *
     * @param array $expected The expected context.
     */
    protected function assertLogHasContext(array $expected) {
        if (empty($this->testLogger->log)) {
            $this->fail("The log is empty");
        }
        list($_, $_, $context) = end($this->testLogger->log);
        $this->assertArraySubsetRecursive($expected, $context);
    }

    /**
     * Assert that the last log entry has a given level.
     *
     * @param string $expected The expected log level.
     */
    protected function assertLogLevel(string $expected) {
        if (empty($this->testLogger->log)) {
            $this->fail("The log is empty");
        }
        list($level, $_, $_) = end($this->testLogger->log);
        $this->assertEquals($expected, $level);
    }

    /**
     * Assert that the last log has a given message.
     *
     * @param string $expected The expected message.
     */
    protected function assertLogMessage(string $expected) {
        if (empty($this->testLogger->log)) {
            $this->fail("The log is empty");
        }
        list($_, $msg, $_) = end($this->testLogger->log);
        $this->assertEquals($expected, $msg);
    }

    /**
     * Assert that the log contains given items.
     *
     * @param array $expected The expected log entries.
     */
    protected function assertLogShape(array $expected) {
        $this->assertArraySubsetRecursive($expected, $this->testLogger->log);
    }
}
