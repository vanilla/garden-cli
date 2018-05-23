<?php
/**
 * @author Chris dePage <chris.d@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Logger\Writer;

use Garden\Cli\Logger\Formatter\FormatterInterface;

/**
 * Interface WriterInterface.
 *
 * The custom writer interface used by Garden loggers.
 */
interface WriterInterface {

    /**
     * Set the formatter used by the writer.
     *
     * @param FormatterInterface $formatter The formatter to use for the writer.
     *
     * @return $this
     */
    public function addFormatter(FormatterInterface $formatter);

    /**
     * Write a message.
     *
     * @param int $timestamp The unix timestamp of the log.
     * @param string $logLevel The level of the message (e.g. INFO, SUCCESS, WARNING, ERROR).
     * @param string $message The message.
     * @param int $indentLevel The nesting level of the message.
     * @param float|null $duration The duration to add to the message.
     *
     * @return $this
     */
    public function write(int $timestamp, string $logLevel, string $message, int $indentLevel = 0, $duration = null);
}
