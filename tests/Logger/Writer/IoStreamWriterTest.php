<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests\Logger\Writer;

use Garden\Cli\Logger\Writer\IoStreamWriter;
use Garden\Cli\Tests\Fixtures\Logger\Formatter\TestFormatter;
use PHPUnit\Framework\TestCase;

/**
 * Includes tests for the {@link \Garden\Cli\Logger\Logger} class.
 */
class IoStreamWriterTest extends TestCase {

    /**
     * @var StreamWriter An instantiated writer.
     */
    protected $writer;

    protected function setUp() {
        parent::setUp();
        $stream = 'php://output';
        $this->writer = new IoStreamWriter($stream);
    }

    public function testAddFormatterReturnsThis() {
        $formatter = new TestFormatter;
        $result = $this->writer->addFormatter($formatter);
        $this->assertEquals($this->writer, $result);
    }

    public function testWriteSendsOutput() {
        ob_start(); // we need to capture output

        $timestamp = time();
        $logLevel = 'info';
        $indentLevel = 2;
        $message = 'abc';
        $duration = 0.002;

        // we need a formatter to properly exercise code
        $formatter = new TestFormatter;
        $this->writer->addFormatter($formatter)->write($timestamp, $logLevel, $message, $indentLevel, $duration);

        $expected = $timestamp . ' ' . strtoupper($logLevel) . '     -  ' . $message . ' ' . $duration . PHP_EOL;
        $this->assertEquals($expected, ob_get_clean());
    }
}
