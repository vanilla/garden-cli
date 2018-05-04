<?php
/**
 * @author Chris dePage <chris.d@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Logger\Writer;

use Garden\Cli\Logger\Formatter\FormatterInterface;

/**
 * Log writer to output to a stream.
 */
class StreamWriter implements WriterInterface {

    /**
     * @var resource The stream resource.
     */
    protected $stream;

    /**
     * @var FormatterInterface[] An array of formatters.
     */
    protected $formatters = [];

    /**
     * StreamWriter constructor.
     *
     * @param string $stream The stream to write to.
     */
    public function __construct(string $stream) {
        $this->stream = fopen($stream, 'w');
    }

    /**
     * Add a formatter to be used by the writer.
     *
     * @param FormatterInterface $formatter A formatter to use for the writer.
     *
     * @return $this
     */
    public function addFormatter(FormatterInterface $formatter) {
        array_push($this->formatters, $formatter);

        return $this;
    }

    /**
     * Write the stream.
     *
     * @param int $timestamp The unix timestamp of the log.
     * @param string $logLevel The level of the message (e.g. INFO, SUCCESS, WARNING, ERROR).
     * @param string $message The message.
     * @param int $indentLevel The nesting level of the message.
     * @param float|null $duration The duration to add to the message.
     */
    public function write(int $timestamp, string $logLevel, string $message, int $indentLevel = 0, $duration = null) {
        // ensure our stream hasn't died
        if (feof($this->stream)) {
            // @codeCoverageIgnoreStart
            trigger_error('Called '.__CLASS__.'::write() but file handle was closed.', E_USER_WARNING);
            return;
            // @codeCoverageIgnoreEnd
        }

        // apply each of the formatters we've attached to this writer (will usually only be one)
        foreach($this->formatters as $formatter) {
            $message = $formatter->format($timestamp, $logLevel, $indentLevel, $message, $duration);
        }

        // write the message
        fwrite($this->stream, $message . PHP_EOL);
    }
}
