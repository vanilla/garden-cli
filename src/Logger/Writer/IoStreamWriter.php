<?php
/**
 * @author Chris dePage <chris.d@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Logger\Writer;

use Garden\Cli\Logger\Formatter\ColorizerFormatter;
use Garden\Cli\Logger\Formatter\FormatterInterface;

/**
 * Log writer to output to a stream.
 */
class IoStreamWriter implements WriterInterface {

    /**
     * @var resource The stream resource.
     */
    protected $stream;

    /**
     * @var FormatterInterface[] An array of formatters.
     */
    protected $formatters = [];

    /**
     * @var bool Whether or not to show durations for tasks.
     */
    protected $showDurations = true;

    /**
     * IoStreamWriter constructor.
     *
     * @param string $stream The stream to write to.
     *
     * @throws \Exception
     *
     * @see http://php.net/manual/en/wrappers.php.php
     */
    public function __construct(string $stream) {
        // this writer is only for php streams
        if (substr($stream, 0, 6) !== 'php://') {
            throw new \Exception('Stream must start with "php://"');
        }

        $this->stream = fopen($stream, 'w');

        // ensure the stream opened
        if ($this->stream === false) {
            throw new \Exception('Stream "'.$stream.'" could not be opened');
        }
    }

    /**
     * Set the showDurations.
     *
     * @param boolean $showDurations Flag to indicate if durations should be displayed.
     *
     * @return $this
     */
    public function setShowDurations(bool $showDurations) {
        $this->showDurations = $showDurations;

        return $this;
    }

    /**
     * Get the showDurations.
     *
     * @return boolean Returns the showDurations.
     */
    public function getShowDurations() {
        return $this->showDurations;
    }

    /**
     * Add a formatter to be used by the writer.
     *
     * @param FormatterInterface $formatter A formatter to use for the writer.
     *
     * @return $this
     */
    public function addFormatter(FormatterInterface $formatter) {
        // edge case: the ColorizerFormatter cannot be used if the output terminal doesn't support it
        if ($formatter instanceof ColorizerFormatter && !$this->doesStreamSupportColors($this->stream)) {
            return $this;
        }

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

        // apply each of the formatters we've attached to this writer
        foreach($this->formatters as $formatter) {
            list($timestamp, $logLevel, $indentLevel, $message, $duration) = $formatter->format($timestamp, $logLevel, $indentLevel, $message, $duration);
        }

        // convert the indent levels to a visual representation
        $indentation = $indentLevel ? str_repeat('  ', $indentLevel).'- ' : '';

        // build the log entry we'll write to the file
        $logEntry = $timestamp . ' ' . strtoupper($logLevel) . ' ' . $indentation . ' ' .$message;
        if ($this->showDurations) {
            $logEntry .= ' '.$duration;
        }

        // write the message
        fwrite($this->stream, trim($logEntry).PHP_EOL);
    }

    /**
     * Guess whether or not to format the output with colors for a provided stream.
     *
     * Windows machines and some terminals do not support colors, so perform a best-guess effort to determine if it
     * the current environment does.
     *
     * @param Resource $stream An open php://**** stream
     *
     * @return bool Returns **true** if the output can be formatter or **false** otherwise.
     *
     * @see Garden\Cli::guessFormatOutput
     */
    private function doesStreamSupportColors($stream) {
        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            return false;
        } elseif (function_exists('posix_isatty')) {
            return posix_isatty($stream);
        } else {
            return true;
        }
    }
}
