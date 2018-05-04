<?php
/**
 * @author Chris dePage <chris.d@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Tests\Fixtures\Logger\Writer;

use Garden\Cli\Logger\Writer\WriterInterface;
use Garden\Cli\Logger\Formatter\FormatterInterface;

/**
 * Implementation of a log writer for testing use only.
 */
class TestWriter implements WriterInterface {

    /**
     * @var array Array of messages.
     */
    public $messages = [];

    /**
     * @var array Array of formatters.
     */
    public $formatters = [];

    /**
     * Set the formatter used by the writer.
     *
     * @param FormatterInterface $formatter The formatter to use for the writer.
     *
     * @return $this Returns `$this` for fluent calls.
     */
    public function addFormatter(FormatterInterface $formatter) {
        array_push($this->formatters, $formatter);
        return $this;
    }

    /**
     * Capture all writes and store them in an array.
     *
     * @param int $timestamp The unix timestamp of the log.
     * @param string $logLevel The level of the message (e.g. INFO, SUCCESS, WARNING, ERROR).
     * @param string $message The message.
     * @param int $indentLevel The nesting level of the message.
     * @param float|null $duration The duration to add to the message.
     *
     * @return $this Returns `$this` for fluent calls.
     */
    public function write(int $timestamp, string $logLevel, string $message, int $indentLevel = 0, $duration = null) {
        array_push($this->messages, [$timestamp, $logLevel, $message, $indentLevel, $duration]);
        return $this;
    }
}
