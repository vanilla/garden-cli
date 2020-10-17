<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests;

use Garden\Cli\StreamLogger;
use Garden\Cli\TaskLogger;

class StreamLoggerTest extends AbstractCliTest {
    /**
     * @var StreamLogger
     */
    protected $log;

    /**
     * Create a new logger for each test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->log = new StreamLogger('php://output');
        $this->log
            ->setShowDurations(false)
            ->setTimeFormat('d');
    }

    /**
     * The log should throw an exception with an invalid path.
     */
    public function testInvalidStreamPath() {
        $this->expectException(\InvalidArgumentException::class);
        $log = new StreamLogger('');
    }

    /**
     * The log should throw an exception with an invalid resource.
     */
    public function testInvalidStreamResource() {
        $this->expectException(\InvalidArgumentException::class);
        $log = new StreamLogger(false);
    }

    /**
     * The EOL must include `\n`.
     */
    public function testInvalidEOL() {
        $this->expectException(\InvalidArgumentException::class);
        $this->log->setEol(",");
    }

    /**
     * Test changing the line endings.
     */
    public function testCustomEol() {
        $this->log->setEol("\r\n");
        $this->log->info('foo');

        $this->expectOutputString("[d] foo\r\n");
    }

    /**
     * Begin and ends should be on the same line if buffering is true (default).
     */
    public function testEndSameLine() {
        $this->log->info('begin', [TaskLogger::FIELD_BEGIN => true]);
        $this->log->info('end', [TaskLogger::FIELD_END => true]);

        $this->expectOutputString("[d] begin end\n");
    }

    /**
     * Begins and ends should be on the same line if buffering is false.
     */
    public function testEndSameLineSetting() {
        $this->log->setBufferBegins(false);
        $this->log->info('begin', [TaskLogger::FIELD_BEGIN => true]);
        $this->log->info('end', [TaskLogger::FIELD_END => true]);

        $this->expectOutputString("[d] begin\n[d] end\n");
    }

    /**
     * Logging after a buffered begin should undo the buffering.
     */
    public function testBeginLogEnd() {
        $this->log->info('begin', [TaskLogger::FIELD_BEGIN => true]);
        $this->log->info('log');
        $this->log->info('end', [TaskLogger::FIELD_END => true]);

        $this->expectOutputString("[d] begin\n[d] log\n[d] end\n");
    }

    /**
     * Logging after a buffered begin should undo the buffering.
     */
    public function testBeginLogEndNested() {
        $this->log->info('begin1', [TaskLogger::FIELD_BEGIN => true]);
        $this->log->info('begin2', [TaskLogger::FIELD_BEGIN => true]);
        $this->log->info('end1', [TaskLogger::FIELD_END => true]);
        $this->log->info('end2', [TaskLogger::FIELD_END => true]);

        $this->expectOutputString("[d] begin1\n[d] begin2 end1\n[d] end2\n");
    }

    /**
     * An end with no beginning should output to its own line.
     */
    public function testEndNoBeginning() {
        $this->log->info('log');
        $this->log->info('end', [TaskLogger::FIELD_END => true]);

        $this->expectOutputString("[d] log\n[d] end\n");
    }

    /**
     * Test log indents.
     */
    public function testIndentField() {
        $this->log->info('a', [TaskLogger::FIELD_INDENT => 1]);
        $this->log->info('b', [TaskLogger::FIELD_INDENT => 2]);

        $this->expectOutputString("[d] - a\n[d]   - b\n");
    }

    /**
     * Test log durations.
     */
    public function testDurationField() {
        $this->log
            ->setShowDurations(true)
            ->info('a', [TaskLogger::FIELD_DURATION => 1]);

        $this->expectOutputString("[d] a 1s\n");
    }

    /**
     * Test log times.
     */
    public function testTimeField() {
        $this->log
            ->setTimeFormat('%F %T')
            ->info('foo', [TaskLogger::FIELD_TIME => strtotime('jan 31 2001 3pm')]);


        $this->expectOutputString("[2001-01-31 15:00:00] foo\n");
    }

    /**
     * Newlines should be expanded into multiple lines.
     */
    public function testNewlineExpansion() {
        $this->log->info("a\nb\nc", [TaskLogger::FIELD_INDENT => 1]);

        $this->expectOutputString("[d] - a\n[d] - b\n[d] - c\n");
    }

    /**
     * Alternate newlines should be normalized.
     */
    public function testNewlineExpansionNormalization() {
        $this->log->info("a\r\nb");
        $this->expectOutputString("[d] a\n[d] b\n");
    }

    /**
     * Newlines on buffered begin/ends should also expand.
     */
    public function testNewlineExpansionWithBuffering() {
        $this->log->info("a\nb", [TaskLogger::FIELD_BEGIN => true]);
        $this->log->info('c', [TaskLogger::FIELD_END => true]);

        $this->expectOutputString("[d] a\n[d] b c\n");
    }

    /**
     * Newlines on buffered begin/ends should also expand. Ends should work.
     */
    public function testNewlineExpansionWithBufferingEnd() {
        $this->log->info("a", [TaskLogger::FIELD_BEGIN => true]);
        $this->log->info("b\nc", [TaskLogger::FIELD_END => true]);

        $this->expectOutputString("[d] a\n[d] b\n[d] c\n");
    }

    /**
     * Test the lower bounds of the various duration types.
     */
    public function testFormatDurationMinimums() {
        $fn = \Closure::bind(function ($dur) {
            return $this->formatDuration($dur);
        }, $this->log, StreamLogger::class);

        $this->assertSame('1μs', $fn(1e-6));
        $this->assertSame('1ms', $fn(1e-3));
        $this->assertSame('1s', $fn(1));
        $this->assertSame('1m', $fn(60));
        $this->assertSame('1h', $fn(strtotime('1 hour', 0)));
        $this->assertSame('1d', $fn(strtotime('1 day', 0)));
    }

    /**
     * Test logging to a custom file.
     */
    public function testCustomFile() {
        $path = __DIR__.'/testCustomFile.log';

        $log = new StreamLogger($path);
        $log->setTimeFormat('d')
            ->info('a');
        unset($log);

        $str = file_get_contents($path);
        $this->assertEquals("[d] a\n", $str);
        unlink($path);
    }

    /**
     * Trying to write to a closed file should emit a warning.
     */
    public function testFileClosed() {
        $path = __DIR__.'/testCustomFile.log';
        $fp = fopen($path, 'w+');

        $log = new StreamLogger($fp);
        fclose($fp);
        unlink($path);

        $this->expectWarning();
        $log->info('a');
    }

    /**
     * Output strings should be wrapped in format codes when output formatting is on.
     */
    public function testBasicFormatWrapping() {
        $this->log->setColorizeOutput(true);
        $this->log->debug('debug');

        $this->expectOutputString("\033[0;37m[d] debug\033[0m\n");
    }

    /**
     * Test the level formatter.
     */
    public function testLevelFormatter() {
        $this->log
            ->setLineFormat('{level} {message}')
            ->setLevelFormat('strtoupper')
            ->info('foo');

        $this->expectOutputString("INFO foo\n");
    }

    /**
     * Test the time format callback.
     */
    public function testTimeFormatCallback() {
        $this->log
            ->setTimeFormat(function ($t) {
                return (string)(int)$t;
            })
            ->info('foo', [TaskLogger::FIELD_TIME => 1]);

        $this->expectOutputString("[1] foo\n");
    }

    /**
     * Reproduce a bug with empty end messages and a duration was missing a space.
     */
    public function testEndEmptyEndMessageDuration() {
        $this->log->setShowDurations(true);
        $this->log->info('a', [TaskLogger::FIELD_BEGIN => true]);
        $this->log->info('', [TaskLogger::FIELD_END => true, TaskLogger::FIELD_DURATION => 1]);

        $this->expectOutputString("[d] a 1s\n");
    }
}
